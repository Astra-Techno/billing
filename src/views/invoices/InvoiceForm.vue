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

// Mobile step wizard (3 steps): 1=Customer+Date, 2=Items, 3=Review
const step = ref(1)
const totalSteps = 3

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
      if (inv) { applySourceInvoice(inv); if (isEdit.value) step.value = 1 }
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

function nextStep() {
  error.value = ''
  if (step.value === 1 && !form.value.client_id) return (error.value = 'Please choose a customer.')
  if (step.value === 2 && !form.value.items.some(i => i.description)) return (error.value = 'Please add at least one item.')
  step.value++
}

function prevStep() { step.value-- }

async function submit() {
  error.value = ''
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
    step.value = 3
  } finally { loading.value = false }
}
</script>

<template>
  <div class="max-w-5xl mx-auto">

    <!-- Header -->
    <div class="flex items-center gap-3 mb-5">
      <button @click="step > 1 ? prevStep() : router.back()" class="p-2 rounded-xl hover:bg-gray-100 shrink-0 lg:hidden">
        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
      </button>
      <button @click="router.back()" class="p-2 rounded-xl hover:bg-gray-100 shrink-0 hidden lg:flex">
        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
      </button>
      <div class="flex-1">
        <h1 class="page-title">{{ isEdit ? 'Edit Bill' : isDuplicate ? 'Copy Bill' : 'Create New Bill' }}</h1>
        <div class="flex items-center gap-1.5 mt-1 lg:hidden">
          <div v-for="s in totalSteps" :key="s"
            class="h-1.5 rounded-full transition-all duration-200"
            :class="s <= step ? 'bg-primary-600 w-6' : 'bg-gray-200 w-3'"></div>
          <span class="text-xs text-gray-400 ml-1">Step {{ step }} of {{ totalSteps }}</span>
        </div>
      </div>
    </div>

    <!-- ==================== DESKTOP: Two-column layout ==================== -->
    <div class="hidden lg:grid lg:grid-cols-5 lg:gap-6">

      <!-- LEFT: Customer + Dates + Notes + Recurring -->
      <div class="lg:col-span-2 space-y-4">

        <!-- Customer -->
        <div class="card card-body space-y-3">
          <h2 class="section-title mb-0">Bill To</h2>
          <div v-if="form.client_id" class="flex items-center gap-3 px-3 py-2.5 bg-primary-50 border border-primary-200 rounded-xl">
            <div class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center shrink-0">
              <span class="text-primary-700 text-xs font-bold">{{ clients.find(c => c.id == form.client_id)?.name?.charAt(0)?.toUpperCase() }}</span>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-primary-800 truncate">{{ clients.find(c => c.id == form.client_id)?.name }}</p>
              <p class="text-xs text-primary-500 truncate">{{ clients.find(c => c.id == form.client_id)?.mobile || clients.find(c => c.id == form.client_id)?.email }}</p>
            </div>
            <button type="button" @click="form.client_id = ''; clientSearch = ''" class="text-xs text-primary-600 font-semibold hover:underline shrink-0">Change</button>
          </div>
          <div v-else class="space-y-2">
            <div class="relative">
              <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
              <input v-model="clientSearch" type="text" placeholder="Search by name, mobile…" class="form-input pl-9 text-sm" />
            </div>
            <div class="max-h-48 overflow-y-auto rounded-xl border border-gray-200 divide-y divide-gray-100">
              <div v-if="!filteredClients.length" class="px-4 py-4 text-center text-sm text-gray-400">No customers match "{{ clientSearch }}"</div>
              <button v-for="c in filteredClients" :key="c.id" type="button"
                @click="form.client_id = c.id; clientSearch = ''"
                class="w-full flex items-center gap-3 px-3 py-2.5 hover:bg-primary-50 transition text-left group">
                <div class="w-8 h-8 rounded-full bg-gray-100 group-hover:bg-primary-100 flex items-center justify-center shrink-0 transition">
                  <span class="text-xs font-bold text-gray-600 group-hover:text-primary-700">{{ c.name?.charAt(0)?.toUpperCase() }}</span>
                </div>
                <div class="min-w-0 flex-1">
                  <p class="text-sm font-semibold text-gray-800 group-hover:text-primary-700 truncate">{{ c.name }}</p>
                  <p class="text-xs text-gray-400 truncate">{{ c.mobile || c.email || 'Customer' }}</p>
                </div>
              </button>
            </div>
            <RouterLink to="/clients/new" class="flex items-center gap-1.5 text-sm text-primary-600 font-medium mt-1">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
              Add new customer
            </RouterLink>
          </div>
        </div>

        <!-- Dates + Bill Type -->
        <div class="card card-body space-y-3">
          <h2 class="section-title mb-0">Bill Details</h2>
          <div class="grid grid-cols-2 gap-3">
            <div><label class="form-label">Bill Date</label><input v-model="form.issue_date" type="date" class="form-input" /></div>
            <div><label class="form-label">Pay By</label><input v-model="form.due_date" type="date" class="form-input" /></div>
          </div>
          <div>
            <label class="form-label">Bill Type</label>
            <select v-model="form.invoice_type" class="form-select">
              <option value="tax_invoice">Tax Invoice (with GST)</option>
              <option value="bill_of_supply">Bill of Supply (no GST)</option>
              <option value="retail">Retail Invoice</option>
            </select>
          </div>
        </div>

        <!-- Notes + Terms -->
        <div class="card card-body space-y-3">
          <h2 class="section-title mb-0">Notes & Terms</h2>
          <div>
            <label class="form-label">Message to Customer</label>
            <textarea v-model="form.notes" rows="2" class="form-input" placeholder="e.g. Thank you for your business!"></textarea>
          </div>
          <div>
            <label class="form-label">Terms & Conditions</label>
            <textarea v-model="form.terms" rows="2" class="form-input" placeholder="e.g. Payment due within 30 days."></textarea>
          </div>
        </div>

        <!-- Recurring -->
        <div class="card card-body space-y-3">
          <div class="flex items-center justify-between">
            <h2 class="section-title mb-0">Auto-repeat</h2>
            <button type="button" @click="form.is_recurring = !form.is_recurring"
              class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
              :class="form.is_recurring ? 'bg-primary-600' : 'bg-gray-200'">
              <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                :class="form.is_recurring ? 'translate-x-6' : 'translate-x-1'"></span>
            </button>
          </div>
          <div v-if="form.is_recurring" class="grid grid-cols-3 gap-2">
            <div><label class="form-label">Every</label><input v-model="form.recur_every" type="number" min="1" class="form-input" /></div>
            <div>
              <label class="form-label">Period</label>
              <select v-model="form.recur_period" class="form-select">
                <option v-for="p in recurPeriods" :key="p" :value="p">{{ p.charAt(0).toUpperCase() + p.slice(1) }}(s)</option>
              </select>
            </div>
            <div><label class="form-label">Until</label><input v-model="form.recur_ends_at" type="date" class="form-input" /></div>
          </div>
        </div>
      </div>

      <!-- RIGHT: Items + Totals + Submit -->
      <div class="lg:col-span-3 space-y-4">
        <div class="card">
          <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="section-title mb-0">Items</h2>
            <span class="text-xs text-gray-400">{{ form.items.length }} item{{ form.items.length > 1 ? 's' : '' }}</span>
          </div>
          <div class="divide-y divide-gray-100">
            <div v-for="(it, i) in form.items" :key="i" class="p-4 space-y-3">
              <div class="flex items-center gap-2">
                <select v-model="it.product_id" class="form-select text-sm flex-1" @change="pickProduct(i, it.product_id)">
                  <option :value="null">Pick a product or type manually…</option>
                  <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} — {{ inr(p.price) }}</option>
                </select>
                <button type="button" @click="openAddProduct(i)"
                  class="w-9 h-9 rounded-xl bg-primary-600 hover:bg-primary-700 text-white flex items-center justify-center shrink-0 transition" title="Add new product">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                </button>
              </div>
              <input v-model="it.description" type="text" class="form-input" placeholder="Description / Item name" />
              <div class="grid grid-cols-4 gap-2">
                <div>
                  <label class="form-label">Qty</label>
                  <input v-model="it.quantity" type="number" min="0.001" step="0.001" class="form-input text-center" />
                </div>
                <div class="col-span-2">
                  <label class="form-label">Price (₹)</label>
                  <input v-model="it.unit_price" type="number" min="0" step="0.01" class="form-input" placeholder="0.00" />
                </div>
                <div>
                  <label class="form-label">Unit</label>
                  <select v-model="it.unit" class="form-select text-sm">
                    <option v-for="u in units" :key="u">{{ u }}</option>
                  </select>
                </div>
              </div>
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <template v-if="form.invoice_type !== 'bill_of_supply'">
                    <span class="text-xs text-gray-500">GST</span>
                    <div class="flex gap-1">
                      <button v-for="r in gstRates" :key="r" type="button" @click="it.gst_rate = r"
                        class="px-2 py-1 rounded-lg text-xs font-medium border transition"
                        :class="it.gst_rate === r ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-600 border-gray-200'">
                        {{ r }}%
                      </button>
                    </div>
                  </template>
                  <span v-else class="text-xs text-amber-600 font-medium bg-amber-50 px-2 py-1 rounded-lg">No GST (Bill of Supply)</span>
                </div>
                <div class="flex items-center gap-2">
                  <p class="text-sm font-bold text-gray-900">{{ inr(lineTotal(it)) }}</p>
                  <button v-if="form.items.length > 1" type="button" @click="removeItem(i)"
                    class="w-7 h-7 rounded-full bg-red-50 flex items-center justify-center text-red-400 hover:bg-red-100">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                  </button>
                </div>
              </div>
            </div>
          </div>
          <div class="px-4 py-3 border-t border-gray-100">
            <button type="button" @click="addItem"
              class="w-full flex items-center justify-center gap-2 py-2.5 rounded-xl border-2 border-dashed border-gray-300 text-gray-500 text-sm font-medium hover:border-primary-400 hover:text-primary-600 transition">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
              Add Another Item
            </button>
          </div>
        </div>

        <!-- Totals -->
        <div class="card card-body">
          <div class="flex justify-between text-sm text-gray-500 mb-1.5"><span>Subtotal</span><span>{{ inr(totals.subtotal) }}</span></div>
          <div v-if="totals.tax > 0" class="flex justify-between text-sm text-gray-500 mb-1.5"><span>GST</span><span>{{ inr(totals.tax) }}</span></div>
          <div class="flex justify-between font-bold text-lg text-gray-900 border-t border-gray-100 pt-2">
            <span>Grand Total</span><span class="text-primary-600">{{ inr(totals.total) }}</span>
          </div>
        </div>

        <div v-if="error" class="text-sm text-danger-500 bg-danger-50 rounded-xl px-4 py-3">{{ error }}</div>

        <button @click="submit" :disabled="loading"
          class="btn-primary w-full btn-lg disabled:opacity-60 disabled:cursor-not-allowed">
          <svg v-if="loading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
          </svg>
          {{ loading ? 'Saving…' : isEdit ? 'Save Changes' : 'Create Bill' }}
        </button>
      </div>
    </div>

    <!-- ======================== MOBILE: Step-by-step ======================== -->
    <div class="lg:hidden">

    <!-- ======================== STEP 1: Customer + Date ======================== -->
    <div v-if="step === 1" class="space-y-4">
      <div class="card card-body space-y-4">
        <div class="flex items-center gap-2 mb-1">
          <div class="w-7 h-7 rounded-full bg-primary-100 flex items-center justify-center">
            <span class="text-primary-700 text-xs font-bold">1</span>
          </div>
          <h2 class="section-title mb-0">Who is this bill for?</h2>
        </div>

        <!-- Customer picker: searchable at any scale -->
        <!-- Selected customer pill -->
        <div v-if="form.client_id" class="flex items-center gap-3 px-3 py-2.5 bg-primary-50 border border-primary-200 rounded-xl">
          <div class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center shrink-0">
            <span class="text-primary-700 text-xs font-bold">
              {{ clients.find(c => c.id == form.client_id)?.name?.charAt(0)?.toUpperCase() }}
            </span>
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-primary-800 truncate">{{ clients.find(c => c.id == form.client_id)?.name }}</p>
            <p class="text-xs text-primary-500 truncate">{{ clients.find(c => c.id == form.client_id)?.mobile || clients.find(c => c.id == form.client_id)?.email }}</p>
          </div>
          <button type="button" @click="form.client_id = ''; clientSearch = ''"
            class="text-xs text-primary-600 font-semibold hover:underline shrink-0">Change</button>
        </div>

        <!-- Search + list (shown when no customer selected) -->
        <div v-else class="space-y-2">
          <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input v-model="clientSearch" type="text" placeholder="Search by name, mobile…"
              class="form-input pl-9 text-sm" />
          </div>

          <!-- Results -->
          <div class="max-h-64 overflow-y-auto rounded-xl border border-gray-200 divide-y divide-gray-100">
            <div v-if="!filteredClients.length" class="px-4 py-5 text-center text-sm text-gray-400">
              No customers match "{{ clientSearch }}"
            </div>
            <button v-for="c in filteredClients" :key="c.id" type="button"
              @click="form.client_id = c.id; clientSearch = ''"
              class="w-full flex items-center gap-3 px-4 py-3 hover:bg-primary-50 transition text-left group">
              <div class="w-9 h-9 rounded-full bg-gray-100 group-hover:bg-primary-100 flex items-center justify-center shrink-0 transition">
                <span class="text-xs font-bold text-gray-600 group-hover:text-primary-700">{{ c.name?.charAt(0)?.toUpperCase() }}</span>
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-gray-800 group-hover:text-primary-700 truncate transition">{{ c.name }}</p>
                <p class="text-xs text-gray-400 truncate">{{ c.mobile || c.email || c.company || 'Customer' }}</p>
              </div>
              <svg class="w-4 h-4 text-gray-300 group-hover:text-primary-400 shrink-0 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </button>
          </div>
        </div>

        <RouterLink to="/clients/new" class="flex items-center gap-2 text-sm text-primary-600 font-medium">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Add new customer
        </RouterLink>
      </div>

      <div class="card card-body space-y-4">
        <div class="flex items-center gap-2 mb-1">
          <div class="w-7 h-7 rounded-full bg-primary-100 flex items-center justify-center">
            <span class="text-primary-700 text-xs font-bold">2</span>
          </div>
          <h2 class="section-title mb-0">Bill dates</h2>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Bill Date</label>
            <input v-model="form.issue_date" type="date" class="form-input" />
          </div>
          <div>
            <label class="form-label">Pay By</label>
            <input v-model="form.due_date" type="date" class="form-input" />
          </div>
        </div>

        <!-- Bill type (collapsed by default on mobile) -->
        <details class="group">
          <summary class="text-sm text-gray-500 cursor-pointer list-none flex items-center gap-1 select-none">
            <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            Advanced options
          </summary>
          <div class="mt-3 space-y-3">
            <div>
              <label class="form-label">Bill Type</label>
              <select v-model="form.invoice_type" class="form-select">
                <option value="tax_invoice">Tax Invoice (with GST)</option>
                <option value="bill_of_supply">Bill of Supply (no GST)</option>
                <option value="retail">Retail Invoice</option>
              </select>
            </div>
          </div>
        </details>
      </div>

      <div v-if="error" class="text-sm text-danger-500 bg-danger-50 rounded-xl px-4 py-3">{{ error }}</div>

      <button @click="nextStep" class="btn-primary w-full btn-lg">
        Next: Add Items
        <svg class="w-5 h-5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
      </button>
    </div>

    <!-- ======================== STEP 2: Items ======================== -->
    <div v-if="step === 2" class="space-y-4">

      <!-- Selected customer summary chip -->
      <div v-if="selectedClient" class="flex items-center gap-2 px-4 py-2.5 bg-primary-50 rounded-xl border border-primary-100">
        <div class="w-7 h-7 rounded-full bg-primary-100 flex items-center justify-center shrink-0">
          <span class="text-primary-700 text-xs font-bold">{{ selectedClient.name?.charAt(0)?.toUpperCase() }}</span>
        </div>
        <div class="flex-1 min-w-0">
          <span class="text-sm font-medium text-primary-700 truncate">{{ selectedClient.name }}</span>
        </div>
        <button @click="step = 1" class="text-xs text-primary-500 font-medium">Change</button>
      </div>

      <div class="card">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <h2 class="section-title mb-0">What are you billing for?</h2>
          <span class="text-xs text-gray-400">{{ form.items.length }} item{{ form.items.length > 1 ? 's' : '' }}</span>
        </div>

        <div class="divide-y divide-gray-100">
          <div v-for="(it, i) in form.items" :key="i" class="p-4">

            <!-- Product picker -->
            <div class="flex items-center gap-2 mb-3">
              <select v-model="it.product_id" class="form-select text-sm flex-1" @change="pickProduct(i, it.product_id)">
                <option :value="null">Type manually or pick below…</option>
                <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} — {{ inr(p.price) }}</option>
              </select>
              <button type="button" @click="openAddProduct(i)"
                class="w-9 h-9 rounded-xl bg-primary-600 hover:bg-primary-700 text-white flex items-center justify-center shrink-0 transition active:scale-95"
                title="Add new product">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                </svg>
              </button>
            </div>

            <!-- Description -->
            <input v-model="it.description" type="text" class="form-input mb-3"
              placeholder="What did you sell / provide?" />

            <!-- Qty + Price on same row (mobile friendly) -->
            <div class="grid grid-cols-2 gap-3 mb-3">
              <div>
                <label class="form-label">Quantity</label>
                <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden">
                  <button type="button" @click="it.quantity = Math.max(1, (parseFloat(it.quantity)||1) - 1)"
                    class="px-3 py-2.5 text-gray-500 bg-gray-50 hover:bg-gray-100 text-lg leading-none">−</button>
                  <input v-model="it.quantity" type="number" min="0.001" step="0.001"
                    class="flex-1 text-center border-0 focus:ring-0 text-sm font-medium py-2.5" />
                  <button type="button" @click="it.quantity = (parseFloat(it.quantity)||0) + 1"
                    class="px-3 py-2.5 text-gray-500 bg-gray-50 hover:bg-gray-100 text-lg leading-none">+</button>
                </div>
              </div>
              <div>
                <label class="form-label">Price (₹)</label>
                <input v-model="it.unit_price" type="number" min="0" step="0.01" class="form-input"
                  placeholder="0.00" />
              </div>
            </div>

            <!-- GST + line total on same row -->
            <div class="flex items-center justify-between gap-3">
              <div class="flex items-center gap-2">
                <template v-if="form.invoice_type !== 'bill_of_supply'">
                  <label class="text-xs text-gray-500">GST</label>
                  <div class="flex gap-1">
                    <button v-for="r in gstRates" :key="r" type="button"
                      @click="it.gst_rate = r"
                      class="px-2.5 py-1 rounded-lg text-xs font-medium border transition"
                      :class="it.gst_rate === r ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-600 border-gray-200'">
                      {{ r }}%
                    </button>
                  </div>
                </template>
                <span v-else class="text-xs text-amber-600 font-medium bg-amber-50 px-2 py-1 rounded-lg">No GST</span>
              </div>
              <div class="flex items-center gap-2">
                <p class="text-sm font-bold text-gray-900">{{ inr(lineTotal(it)) }}</p>
                <button v-if="form.items.length > 1" type="button" @click="removeItem(i)"
                  class="w-7 h-7 rounded-full bg-red-50 flex items-center justify-center text-red-400 hover:bg-red-100">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
              </div>
            </div>
          </div>
        </div>

        <div class="px-4 py-3 border-t border-gray-100">
          <button type="button" @click="addItem"
            class="w-full flex items-center justify-center gap-2 py-2.5 rounded-xl border-2 border-dashed border-gray-300 text-gray-500 text-sm font-medium hover:border-primary-400 hover:text-primary-600 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Another Item
          </button>
        </div>
      </div>

      <!-- Live total -->
      <div class="card card-body">
        <div class="flex items-center justify-between text-sm text-gray-500 mb-1.5">
          <span>Subtotal</span><span>{{ inr(totals.subtotal) }}</span>
        </div>
        <div v-if="totals.tax > 0" class="flex items-center justify-between text-sm text-gray-500 mb-1.5">
          <span>GST</span><span>{{ inr(totals.tax) }}</span>
        </div>
        <div class="flex items-center justify-between font-bold text-lg text-gray-900 pt-2 border-t border-gray-100">
          <span>Grand Total</span><span class="text-primary-600">{{ inr(totals.total) }}</span>
        </div>
      </div>

      <div v-if="error" class="text-sm text-danger-500 bg-danger-50 rounded-xl px-4 py-3">{{ error }}</div>

      <button @click="nextStep" class="btn-primary w-full btn-lg">
        Review & Create Bill
        <svg class="w-5 h-5 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
      </button>
    </div>

    <!-- ======================== STEP 3: Review ======================== -->
    <div v-if="step === 3" class="space-y-4">

      <!-- Summary card -->
      <div class="card card-body bg-gradient-to-br from-primary-600 to-primary-700 text-white space-y-3">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-primary-100 text-xs">Bill for</p>
            <p class="font-bold text-lg">{{ selectedClient?.name || '—' }}</p>
          </div>
          <div class="text-right">
            <p class="text-primary-100 text-xs">Total</p>
            <p class="font-bold text-2xl">{{ inr(totals.total) }}</p>
          </div>
        </div>
        <div class="flex gap-4 pt-2 border-t border-primary-500 text-sm">
          <div>
            <p class="text-primary-200 text-xs">Bill Date</p>
            <p class="font-medium">{{ form.issue_date }}</p>
          </div>
          <div>
            <p class="text-primary-200 text-xs">Pay By</p>
            <p class="font-medium">{{ form.due_date }}</p>
          </div>
          <div>
            <p class="text-primary-200 text-xs">Items</p>
            <p class="font-medium">{{ form.items.filter(i => i.description).length }}</p>
          </div>
        </div>
      </div>

      <!-- Items summary -->
      <div class="card">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
          <h3 class="font-semibold text-gray-800 text-sm">Items</h3>
          <button @click="step = 2" class="text-xs text-primary-600 font-medium">Edit</button>
        </div>
        <div class="divide-y divide-gray-100">
          <div v-for="(it, i) in form.items.filter(i => i.description)" :key="i"
            class="flex items-center justify-between px-4 py-3">
            <div class="min-w-0">
              <p class="text-sm font-medium text-gray-800 truncate">{{ it.description }}</p>
              <p class="text-xs text-gray-400">{{ it.quantity }} {{ it.unit }} × {{ inr(it.unit_price) }} · GST {{ it.gst_rate }}%</p>
            </div>
            <p class="text-sm font-semibold text-gray-900 ml-3 shrink-0">{{ inr(lineTotal(it)) }}</p>
          </div>
        </div>
        <div class="px-4 py-3 border-t border-gray-100 space-y-1 text-sm">
          <div class="flex justify-between text-gray-500"><span>Subtotal</span><span>{{ inr(totals.subtotal) }}</span></div>
          <div v-if="totals.tax > 0" class="flex justify-between text-gray-500"><span>GST</span><span>{{ inr(totals.tax) }}</span></div>
          <div class="flex justify-between font-bold text-gray-900 pt-1.5 border-t border-gray-100"><span>Total</span><span>{{ inr(totals.total) }}</span></div>
        </div>
      </div>

      <!-- Optional notes (collapsed) -->
      <details class="card card-body group">
        <summary class="text-sm font-medium text-gray-700 cursor-pointer list-none flex items-center justify-between">
          <span>Add a note or terms</span>
          <svg class="w-4 h-4 text-gray-400 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </summary>
        <div class="mt-4 space-y-3">
          <div>
            <label class="form-label">Message to Customer</label>
            <textarea v-model="form.notes" rows="2" class="form-input" placeholder="e.g. Thank you for your business!"></textarea>
          </div>
          <div>
            <label class="form-label">Terms & Conditions</label>
            <textarea v-model="form.terms" rows="2" class="form-input" placeholder="e.g. Payment due within 30 days."></textarea>
          </div>
        </div>
      </details>

      <!-- Recurring (collapsed) -->
      <details class="card card-body group">
        <summary class="text-sm font-medium text-gray-700 cursor-pointer list-none flex items-center justify-between">
          <span>Auto-repeat this bill</span>
          <svg class="w-4 h-4 text-gray-400 transition-transform group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </summary>
        <div class="mt-4 space-y-3">
          <div class="flex items-center justify-between">
            <p class="text-sm text-gray-600">Automatically create this bill again</p>
            <button type="button" @click="form.is_recurring = !form.is_recurring"
              class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
              :class="form.is_recurring ? 'bg-primary-600' : 'bg-gray-200'">
              <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                :class="form.is_recurring ? 'translate-x-6' : 'translate-x-1'"></span>
            </button>
          </div>
          <div v-if="form.is_recurring" class="grid grid-cols-3 gap-3">
            <div>
              <label class="form-label">Every</label>
              <input v-model="form.recur_every" type="number" min="1" class="form-input" />
            </div>
            <div>
              <label class="form-label">Period</label>
              <select v-model="form.recur_period" class="form-select">
                <option v-for="p in recurPeriods" :key="p" :value="p">{{ p.charAt(0).toUpperCase() + p.slice(1) }}(s)</option>
              </select>
            </div>
            <div>
              <label class="form-label">Until</label>
              <input v-model="form.recur_ends_at" type="date" class="form-input" />
            </div>
          </div>
        </div>
      </details>

      <div v-if="error" class="text-sm text-danger-500 bg-danger-50 rounded-xl px-4 py-3">{{ error }}</div>

      <button @click="submit" :disabled="loading"
        class="btn-primary w-full btn-lg disabled:opacity-60 disabled:cursor-not-allowed">
        <svg v-if="loading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
        {{ loading ? 'Creating…' : isEdit ? 'Save Changes' : 'Create Bill' }}
      </button>
    </div><!-- end step 3 -->

    </div><!-- end mobile steps (lg:hidden) -->

  </div><!-- end max-w-5xl -->

  <!-- ── Quick Add Product Modal ── -->
  <Teleport to="body">
    <div v-if="showAddProduct" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm" @click.stop>
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
          <h3 class="font-bold text-gray-900">Add New Product</h3>
          <button @click="showAddProduct = false" class="text-gray-400 hover:text-gray-600 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
        <div class="p-5 space-y-4">

          <!-- Type toggle -->
          <div class="flex gap-2">
            <button type="button" @click="newProduct.type = 'service'"
              class="flex-1 py-2 rounded-xl text-sm font-semibold border transition"
              :class="newProduct.type === 'service' ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'">
              Service
            </button>
            <button type="button" @click="newProduct.type = 'product'"
              class="flex-1 py-2 rounded-xl text-sm font-semibold border transition"
              :class="newProduct.type === 'product' ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'">
              Product
            </button>
          </div>

          <div>
            <label class="form-label">Name *</label>
            <input v-model="newProduct.name" type="text" class="form-input" placeholder="e.g. Web Development" autofocus />
          </div>

          <div class="grid grid-cols-2 gap-3">
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

          <div v-if="addProductError" class="flex items-center gap-2 text-sm text-red-600 bg-red-50 border border-red-100 rounded-xl px-4 py-3">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ addProductError }}
          </div>

          <div class="flex gap-3 pt-1">
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

</template>
