<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { task, item, list, all } from '../../api'
import { inr } from '../../utils/currency'
import { today, addDays } from '../../utils/date'
import { calcInvoice } from '../../utils/invoice'

const router = useRouter()
const route  = useRoute()
const emit   = defineEmits(['refresh'])

const clients      = ref([])
const products     = ref([])
const taxRates     = ref([])
const loading      = ref(false)
const clientSearch = ref('')

const filteredClients = computed(() => {
  const q = clientSearch.value.trim().toLowerCase()
  if (!q) return clients.value
  return clients.value.filter(c =>
    c.name?.toLowerCase().includes(q) ||
    c.mobile?.includes(q) ||
    c.company?.toLowerCase().includes(q)
  )
})
const error    = ref('')

// Quick-add client modal
const showAddClient = ref(false)
const addingClient = ref(false)
const addClientError = ref('')
const newClient = ref({ name: '', mobile: '', email: '' })

async function saveNewClient() {
  addClientError.value = ''
  if (!newClient.value.name) return addClientError.value = 'Customer name is required.'
  addingClient.value = true
  try {
    const res = await task('Client', 'create', newClient.value)
    const created = res.data?.data
    clients.value.push(created)
    clients.value.sort((a, b) => (a.name || '').localeCompare(b.name || ''))
    form.value.client_id = created.id
    showAddClient.value = false
    clientSearch.value = ''
    newClient.value = { name: '', mobile: '', email: '' }
  } catch (e) {
    addClientError.value = e.response?.data?.message || 'Failed to save customer.'
  }
  addingClient.value = false
}

function openAddClient() {
  addClientError.value = ''
  newClient.value = { name: clientSearch.value, mobile: '', email: '' }
  showAddClient.value = true
}

// Quick-add product modal
const showAddProduct   = ref(false)
const addingProduct    = ref(false)
const addProductError  = ref('')
const pendingItemIndex = ref(null)
const newProduct = ref({ type: 'service', name: '', price: '', unit: 'Nos', gst_rate: 18 })

async function saveNewProduct() {
  addProductError.value = ''
  if (!newProduct.value.name) return addProductError.value = 'Product name is required.'
  if (!newProduct.value.price) return addProductError.value = 'Price is required.'
  addingProduct.value = true
  try {
    const res = await task('Product', 'create', {
      type:     newProduct.value.type,
      name:     newProduct.value.name,
      price:    parseFloat(newProduct.value.price),
      unit:     newProduct.value.unit,
      gst_rate: newProduct.value.gst_rate,
    })
    const created = res.data?.data
    products.value.push(created)
    products.value.sort((a, b) => a.name.localeCompare(b.name))
    if (pendingItemIndex.value !== null) {
      form.value.items[pendingItemIndex.value].product_id = created.id
      pickProduct(pendingItemIndex.value, created.id)
    }
    showAddProduct.value = false
    newProduct.value = { type: 'service', name: '', price: '', unit: 'Nos', gst_rate: 18 }
  } catch (e) {
    addProductError.value = e.response?.data?.message || 'Failed to save product.'
  }
  addingProduct.value = false
}

function openAddProduct(i) {
  pendingItemIndex.value = i
  addProductError.value = ''
  newProduct.value = { type: 'service', name: '', price: '', unit: 'Nos', gst_rate: 18 }
  showAddProduct.value = true
}

const isEdit      = computed(() => !!route.params.id)
const isDuplicate = computed(() => !!route.query.duplicate)

const blankItem = () => ({ description: '', hsn_sac: '', unit: 'Nos', quantity: 1, unit_price: '', discount_pct: 0, gst_rate: 18, product_id: null })

const form = ref({
  client_id:    route.query.client || route.query.client_id || '',
  invoice_type: 'tax_invoice',
  issue_date:   today(),
  due_date:     addDays(today(), 30),
  notes: '', terms: '',
  is_recurring:  false,
  recur_every:   1,
  recur_period:  'month',
  recur_ends_at: '',
  items: [blankItem()],
})

const units        = ['Nos', 'Kg', 'Ltr', 'Hrs', 'Pcs', 'Mtr', 'Box', 'Set', 'Pair']
const gstRates     = [0, 5, 12, 18, 28]
const recurPeriods = ['day', 'week', 'month', 'year']
const totals       = computed(() => calcInvoice(form.value.items))

const selectedClient = computed(() => clients.value.find(c => c.id == form.value.client_id))

function applySourceInvoice(inv) {
  form.value.client_id    = inv.client_id
  form.value.invoice_type = inv.invoice_type
  form.value.notes        = inv.notes  || ''
  form.value.terms        = inv.terms  || ''
  form.value.is_recurring = !!inv.is_recurring
  form.value.recur_every  = inv.recur_every  || 1
  form.value.recur_period = inv.recur_period || 'month'
  form.value.recur_ends_at= inv.recur_ends_at || ''
  if (!isDuplicate.value) {
    form.value.issue_date = inv.issue_date
    form.value.due_date   = inv.due_date
  }
  if (inv.items?.length) {
    form.value.items = inv.items.map(it => ({
      description:  it.description,
      hsn_sac:      it.hsn_sac      || '',
      unit:         it.unit         || 'Nos',
      quantity:     it.quantity,
      unit_price:   it.unit_price,
      discount_pct: it.discount_pct || 0,
      gst_rate:     parseFloat(it.gst_rate || 0),
      product_id:   it.product_id   || null,
    }))
  }
}

onMounted(async () => {
  const [cRes, pRes, tRes] = await Promise.all([all('Client'), all('Product'), all('TaxRate')])
  clients.value  = cRes.data?.data  || []
  products.value = pRes.data?.data  || []
  taxRates.value = tRes.data?.data  || []

  const sourceId = isEdit.value ? route.params.id : route.query.duplicate
  if (sourceId) {
    try {
      const res = await item('Invoice', { id: sourceId })
      const inv = res.data?.data
      if (inv) applySourceInvoice(inv)
    } catch { error.value = 'Could not load bill data. Please try again.' }
  }

  // Prefill from delivery challan
  const challanId = route.query.from_challan
  if (challanId) {
    try {
      const [dcRes, itmRes] = await Promise.all([
        item('DeliveryChallan', { id: challanId }),
        list('DeliveryChallan:items', { dc_id: challanId }),
      ])
      const dc = dcRes.data?.data
      const dcItems = itmRes.data?.data || []
      if (dc) {
        form.value.client_id = dc.client_id
        form.value.notes = dc.notes || ''
        if (dcItems.length) {
          form.value.items = dcItems.map(it => ({
            description:  it.description,
            hsn_sac:      it.hsn_sac || '',
            unit:         it.unit || 'Nos',
            quantity:     parseFloat(it.quantity) || 1,
            unit_price:   0,
            discount_pct: 0,
            gst_rate:     18,
            product_id:   it.product_id || null,
          }))
        }
      }
    } catch { /* silently ignore prefill failure */ }
  }
})

watch(() => form.value.invoice_type, (type) => {
  if (type === 'bill_of_supply') {
    form.value.items.forEach(it => { it.gst_rate = 0 })
  }
})

function addItem() { form.value.items.push(blankItem()) }
function removeItem(i) { if (form.value.items.length > 1) form.value.items.splice(i, 1) }

function pickProduct(i, productId) {
  const p = products.value.find(p => p.id == productId)
  if (!p) return
  const it = form.value.items[i]
  it.description = p.name
  it.unit        = p.unit || 'Nos'
  it.unit_price  = p.price
  it.hsn_sac     = p.hsn_sac || ''
  const tr = taxRates.value.find(t => t.id == p.tax_rate_id)
  if (tr) it.gst_rate = parseFloat(tr.rate)
}

function lineTotal(it) {
  return parseFloat(it.quantity||0) * parseFloat(it.unit_price||0)
       * (1 - parseFloat(it.discount_pct||0)/100)
       * (1 + parseFloat(it.gst_rate||0)/100)
}

async function submit() {
  error.value = ''
  if (!form.value.client_id) return (error.value = 'Please choose a customer. You must select one from the list.')
  if (!form.value.items.some(i => i.description)) return (error.value = 'Please add at least one item.')

  loading.value = true
  try {
    if (isEdit.value) {
      await task('Invoice', 'update', { ...form.value, id: route.params.id })
      emit('refresh')
      router.push('/invoices/' + route.params.id)
    } else {
      const { data } = await task('Invoice', 'create', form.value)
      emit('refresh')
      router.push('/invoices/' + data.data.invoice_id)
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save. Please try again.'
  } finally { loading.value = false }
}
</script>

<template>
  <div class="max-w-3xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex items-center gap-3">
      <button @click="router.back()" class="p-2 rounded-xl hover:bg-gray-100 shrink-0 transition">
        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
      </button>
      <div>
        <h1 class="page-title">{{ isEdit ? 'Edit Bill' : isDuplicate ? 'Copy Bill' : 'Create New Bill' }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">Fill in the details to generate an invoice.</p>
      </div>
    </div>

    <form @submit.prevent="submit" class="space-y-6">

      <!-- Customer -->
      <div class="card card-body space-y-4">
        <h2 class="section-title mb-0">Bill To</h2>
        <div v-if="form.client_id" class="flex items-center gap-3 px-4 py-3 bg-primary-50/50 border border-primary-100 rounded-[1.25rem]">
          <div class="w-10 h-10 rounded-full bg-primary-100 flex items-center justify-center shrink-0">
            <span class="text-primary-700 text-sm font-bold">{{ selectedClient?.name?.charAt(0)?.toUpperCase() }}</span>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-primary-900 truncate">{{ selectedClient?.name }}</p>
            <p class="text-xs text-primary-600 truncate">{{ selectedClient?.mobile || selectedClient?.email }}</p>
          </div>
          <button type="button" @click="form.client_id = ''; clientSearch = ''" class="text-xs text-primary-600 font-semibold hover:underline shrink-0">Change</button>
        </div>
        <div v-else class="space-y-3">
          <div class="relative flex items-center gap-2">
            <div class="relative flex-1">
              <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
              <input v-model="clientSearch" type="text" placeholder="Search by name, mobile…" class="form-input pl-10" />
            </div>
            <button type="button" @click="openAddClient" title="Add New Customer"
              class="shrink-0 w-10 h-10 flex items-center justify-center rounded-xl bg-primary-600 hover:bg-primary-700 text-white transition-colors shadow-soft-blue">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            </button>
          </div>
          <div v-if="clientSearch || filteredClients.length > 0" class="max-h-56 overflow-y-auto rounded-[1rem] border border-gray-200 divide-y divide-gray-50">
            <div v-if="!filteredClients.length" class="px-4 py-4 text-center text-sm text-gray-400">No customers match "{{ clientSearch }}"</div>
            <button v-for="c in filteredClients" :key="c.id" type="button"
              @click="form.client_id = c.id; clientSearch = ''"
              class="w-full flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition text-left group">
              <div class="w-8 h-8 rounded-full bg-gray-100 group-hover:bg-primary-50 flex items-center justify-center shrink-0 transition">
                <span class="text-xs font-bold text-gray-600 group-hover:text-primary-600">{{ c.name?.charAt(0)?.toUpperCase() }}</span>
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-gray-800 group-hover:text-primary-700 truncate">{{ c.name }}</p>
                <p class="text-xs text-gray-400 truncate">{{ c.mobile || c.email || 'Customer' }}</p>
              </div>
            </button>
          </div>
        </div>
      </div>

      <!-- Dates + Bill Type -->
      <div class="card card-body space-y-4">
        <h2 class="section-title mb-0">Bill Details</h2>
        <div class="grid sm:grid-cols-2 gap-4">
          <div><label class="form-label">Bill Date</label><input v-model="form.issue_date" type="date" class="form-input" /></div>
          <div><label class="form-label">Pay By</label><input v-model="form.due_date" type="date" class="form-input" /></div>
          <div class="sm:col-span-2">
            <label class="form-label">Bill Type</label>
            <select v-model="form.invoice_type" class="form-select">
              <option value="tax_invoice">Tax Invoice (with GST)</option>
              <option value="bill_of_supply">Bill of Supply (no GST)</option>
              <option value="retail">Retail Invoice</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Items -->
      <div class="card">
        <div class="px-5 py-4 border-b border-gray-100/60 flex items-center justify-between bg-gray-50/50 rounded-t-[2rem]">
          <h2 class="section-title mb-0">Items</h2>
          <span class="text-xs text-gray-400 font-medium">{{ form.items.length }} item{{ form.items.length > 1 ? 's' : '' }}</span>
        </div>
        <div class="divide-y divide-gray-100/60">
          <div v-for="(it, i) in form.items" :key="i" class="p-5 space-y-4">
            
            <div class="flex items-center gap-2">
              <div v-if="products.length" class="flex-1">
                <label class="form-label">Select Product <span class="text-gray-400 font-normal">(or type manually below)</span></label>
                <select v-model="it.product_id" class="form-select" @change="pickProduct(i, it.product_id)">
                  <option :value="null">— Type manually —</option>
                  <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} — {{ inr(p.price) }}</option>
                </select>
              </div>
              <button type="button" @click="openAddProduct(i)"
                class="w-10 h-10 mt-6 rounded-xl bg-primary-600 hover:bg-primary-700 text-white flex items-center justify-center shrink-0 transition active:scale-95 shadow-sm"
                title="Add new product">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
              </button>
            </div>

            <div>
              <label class="form-label">Description *</label>
              <input v-model="it.description" type="text" class="form-input" placeholder="What are you billing for?" required />
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
              <div>
                <label class="form-label">Unit</label>
                <select v-model="it.unit" class="form-select"><option v-for="u in units" :key="u">{{ u }}</option></select>
              </div>
              <div>
                <label class="form-label">Quantity</label>
                <input v-model="it.quantity" type="number" min="0.001" step="0.001" class="form-input" />
              </div>
              <div>
                <label class="form-label">Price (₹)</label>
                <input v-model="it.unit_price" type="number" min="0" step="0.01" class="form-input" placeholder="0.00" />
              </div>
              <div>
                <label class="form-label">GST Rate</label>
                <select v-model="it.gst_rate" class="form-select" :disabled="form.invoice_type === 'bill_of_supply'">
                  <option v-for="r in gstRates" :key="r" :value="r">{{ form.invoice_type === 'bill_of_supply' ? 'No GST' : r + '%' }}</option>
                </select>
              </div>
            </div>

            <div class="flex items-center justify-between pt-2">
              <p class="text-sm font-bold text-gray-900">Total: {{ inr(lineTotal(it)) }}</p>
              <button v-if="form.items.length > 1" type="button" @click="removeItem(i)" class="text-xs text-danger-500 hover:text-danger-700 font-medium transition">Remove Item</button>
            </div>
          </div>
        </div>
        <div class="px-5 py-4 border-t border-gray-100/60">
          <button type="button" @click="addItem"
            class="w-full flex items-center justify-center gap-2 py-3 rounded-xl border-2 border-dashed border-gray-300 text-gray-500 text-sm font-medium hover:border-primary-400 hover:text-primary-600 transition hover:bg-primary-50/50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Another Item
          </button>
        </div>
      </div>

      <!-- Totals -->
      <div class="card card-body bg-gray-50/30">
        <div class="max-w-xs ml-auto space-y-2 text-sm">
          <div class="flex justify-between text-gray-600"><span>Subtotal</span><span class="font-medium text-gray-900">{{ inr(totals.subtotal) }}</span></div>
          <div v-if="totals.tax > 0" class="flex justify-between text-gray-600"><span>GST</span><span class="font-medium text-gray-900">{{ inr(totals.tax) }}</span></div>
          <div class="flex justify-between font-bold text-lg text-gray-900 border-t border-gray-200/60 pt-3 mt-1">
            <span>Grand Total</span><span class="text-primary-600">{{ inr(totals.total) }}</span>
          </div>
        </div>
      </div>

      <!-- Notes, Terms & Recurring (Collapsed) -->
      <div class="grid sm:grid-cols-2 gap-4">
        <div class="card card-body space-y-4">
          <h2 class="section-title mb-0">Notes & Terms</h2>
          <div>
            <label class="form-label">Message to Customer</label>
            <textarea v-model="form.notes" rows="2" class="form-input text-sm" placeholder="e.g. Thank you for your business!"></textarea>
          </div>
          <div>
            <label class="form-label">Terms & Conditions</label>
            <textarea v-model="form.terms" rows="2" class="form-input text-sm" placeholder="e.g. Payment due within 30 days."></textarea>
          </div>
        </div>

        <div class="card card-body space-y-4">
          <div class="flex items-center justify-between">
            <h2 class="section-title mb-0">Auto-repeat</h2>
            <button type="button" @click="form.is_recurring = !form.is_recurring"
              class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
              :class="form.is_recurring ? 'bg-primary-600' : 'bg-gray-200'">
              <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                :class="form.is_recurring ? 'translate-x-6' : 'translate-x-1'"></span>
            </button>
          </div>
          <p class="text-xs text-gray-500">Automatically create this bill on a schedule.</p>
          <div v-if="form.is_recurring" class="grid grid-cols-2 gap-3 pt-2">
            <div><label class="form-label">Every</label><input v-model="form.recur_every" type="number" min="1" class="form-input text-sm" /></div>
            <div>
              <label class="form-label">Period</label>
              <select v-model="form.recur_period" class="form-select text-sm">
                <option v-for="p in recurPeriods" :key="p" :value="p">{{ p.charAt(0).toUpperCase() + p.slice(1) }}(s)</option>
              </select>
            </div>
            <div class="col-span-2"><label class="form-label">Until</label><input v-model="form.recur_ends_at" type="date" class="form-input text-sm" /></div>
          </div>
        </div>
      </div>

      <div v-if="error" class="text-sm text-danger-600 bg-danger-50 border border-danger-100 rounded-xl px-4 py-3 font-medium">{{ error }}</div>

      <div class="flex gap-3 pb-6">
        <button type="button" @click="router.back()" class="btn-outline flex-1">Cancel</button>
        <button type="submit" class="btn-primary flex-1" :disabled="loading">
          {{ loading ? 'Saving…' : isEdit ? 'Save Changes' : 'Create Bill' }}
        </button>
      </div>

    </form>
  </div>

  <!-- ── Quick Add Product Modal ── -->
  <Teleport to="body">
    <div v-if="showAddProduct" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
      <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-sm" @click.stop>
        <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100">
          <h3 class="font-bold text-gray-900 text-lg">Add New Product</h3>
          <button @click="showAddProduct = false" class="text-gray-400 hover:text-gray-600 transition bg-gray-50 rounded-full p-1.5 hover:bg-gray-100">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
        <div class="p-6 space-y-5">
          <div class="flex gap-2">
            <button type="button" @click="newProduct.type = 'service'"
              class="flex-1 py-2.5 rounded-xl text-sm font-semibold border-2 transition"
              :class="newProduct.type === 'service' ? 'bg-primary-50 text-primary-700 border-primary-600' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'">
              Service
            </button>
            <button type="button" @click="newProduct.type = 'product'"
              class="flex-1 py-2.5 rounded-xl text-sm font-semibold border-2 transition"
              :class="newProduct.type === 'product' ? 'bg-primary-50 text-primary-700 border-primary-600' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'">
              Product
            </button>
          </div>

          <div>
            <label class="form-label">Name *</label>
            <input v-model="newProduct.name" type="text" class="form-input" placeholder="e.g. Web Development" autofocus />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="form-label">Price (₹) *</label>
              <input v-model="newProduct.price" type="number" min="0" class="form-input" placeholder="0.00" />
            </div>
            <div>
              <label class="form-label">Unit</label>
              <select v-model="newProduct.unit" class="form-select">
                <option>Nos</option><option>Kg</option><option>Ltr</option>
                <option>Mtr</option><option>Box</option><option>Project</option>
                <option>Month</option><option>Year</option><option>Hour</option>
              </select>
            </div>
          </div>

          <div>
            <label class="form-label">GST Rate (%)</label>
            <select v-model="newProduct.gst_rate" class="form-select">
              <option :value="0">0% — Exempt</option>
              <option :value="5">5%</option>
              <option :value="12">12%</option>
              <option :value="18">18%</option>
              <option :value="28">28%</option>
            </select>
          </div>

          <div v-if="addProductError" class="flex items-center gap-2 text-sm text-danger-600 bg-danger-50 border border-danger-100 rounded-xl px-4 py-3">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ addProductError }}
          </div>

          <div class="flex gap-3 pt-2">
            <button type="button" @click="showAddProduct = false" class="btn-outline flex-1">Cancel</button>
            <button type="button" @click="saveNewProduct" :disabled="addingProduct" class="btn-primary flex-1">
              <svg v-if="addingProduct" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
              {{ addingProduct ? 'Saving…' : 'Save & Select' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- ── Quick Add Customer Modal ── -->
  <Teleport to="body">
    <div v-if="showAddClient" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
      <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-sm" @click.stop>
        <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100">
          <h3 class="font-bold text-gray-900 text-lg">Add New Customer</h3>
          <button @click="showAddClient = false" class="text-gray-400 hover:text-gray-600 transition bg-gray-50 rounded-full p-1.5 hover:bg-gray-100">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="form-label">Customer Name *</label>
            <input v-model="newClient.name" type="text" class="form-input" placeholder="e.g. Acme Corp" autofocus />
          </div>
          <div>
            <label class="form-label">Mobile Number</label>
            <input v-model="newClient.mobile" type="tel" class="form-input" placeholder="Optional" />
          </div>
          <div>
            <label class="form-label">Email Address</label>
            <input v-model="newClient.email" type="email" class="form-input" placeholder="Optional" />
          </div>

          <div v-if="addClientError" class="flex items-center gap-2 text-sm text-danger-600 bg-danger-50 border border-danger-100 rounded-xl px-4 py-3">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ addClientError }}
          </div>

          <div class="flex gap-3 pt-2">
            <button type="button" @click="showAddClient = false" class="btn-outline flex-1">Cancel</button>
            <button type="button" @click="saveNewClient" :disabled="addingClient" class="btn-primary flex-1">
              <svg v-if="addingClient" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
              {{ addingClient ? 'Saving…' : 'Save & Select' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
