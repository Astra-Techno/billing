<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { task, item, list, all } from '../../api'
import { useToast } from '../../composables/useToast'
import { useBusinessStore } from '../../stores/business'
import { inr } from '../../utils/currency'
import { today, addDays } from '../../utils/date'
import { calcInvoice } from '../../utils/invoice'

const router        = useRouter()
const route         = useRoute()
const emit          = defineEmits(['refresh'])
const toast         = useToast()
const businessStore = useBusinessStore()

const clients      = ref([])
const products     = ref([])
const taxRates     = ref([])
const states       = ref([])
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
  client_id:       route.query.client || route.query.client_id || '',
  invoice_type:    'tax_invoice',
  issue_date:      today(),
  due_date:        addDays(today(), 30),
  place_of_supply: '',
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
  form.value.client_id       = inv.client_id
  form.value.invoice_type    = inv.invoice_type
  form.value.place_of_supply = inv.place_of_supply || businessStore.stateId || ''
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
  const [cRes, pRes, tRes, sRes, bizRes] = await Promise.all([all('Client'), all('Product'), all('TaxRate'), all('IndianState'), item('Business')])
  clients.value  = cRes.data?.data  || []
  products.value = pRes.data?.data  || []
  taxRates.value = tRes.data?.data  || []
  states.value   = sRes.data?.data  || []
  const bizStateId = bizRes.data?.data?.state_id
  if (bizStateId) businessStore.setStateId(bizStateId)

  if (!isEdit.value && !route.query.duplicate) {
    form.value.place_of_supply = businessStore.stateId || ''
  }

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
      toast.success('Invoice updated successfully.')
      router.push('/invoices/' + route.params.id)
    } else {
      const { data } = await task('Invoice', 'create', form.value)
      emit('refresh')
      toast.success('Invoice created successfully.')
      router.push('/invoices/' + data.data.invoice_id)
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save. Please try again.'
  } finally { loading.value = false }
}
</script>

<template>
  <div class="desktop-form-shell max-w-3xl mx-auto lg:max-w-none">

    <!-- Sticky toolbar: title + actions always visible on desktop -->
    <div class="form-toolbar">
      <div class="flex items-center gap-3 min-w-0">
        <button type="button" @click="router.back()" class="p-2 rounded-xl hover:bg-surface-muted shrink-0 transition">
          <svg class="w-5 h-5 text-google-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
        </button>
        <div class="min-w-0">
          <h1 class="text-lg lg:text-xl font-bold text-ink truncate">
            {{ isEdit ? 'Edit Bill' : isDuplicate ? 'Copy Bill' : 'Create New Bill' }}
          </h1>
          <p class="text-xs text-google-muted hidden sm:block truncate">Customer, items & payment details</p>
        </div>
      </div>
      <div class="form-toolbar-actions">
        <div class="hidden lg:block text-right mr-1 pr-3 border-r border-google-divider">
          <p class="text-[10px] font-bold text-google-muted uppercase tracking-wide">Grand total</p>
          <p class="text-xl font-bold text-primary-600 tabular-nums leading-tight">{{ inr(totals.total) }}</p>
        </div>
        <button type="button" @click="router.back()" class="btn-outline btn-sm hidden lg:inline-flex">Cancel</button>
        <button type="submit" form="invoice-form" class="btn-primary btn-sm whitespace-nowrap" :disabled="loading">
          {{ loading ? 'Saving…' : isEdit ? 'Save' : 'Create Bill' }}
        </button>
      </div>
    </div>

    <div class="form-body">
    <form id="invoice-form" @submit.prevent="submit" class="form-layout">

      <div class="form-main space-y-4">

      <!-- Customer + Bill details: side by side on desktop -->
      <div class="grid lg:grid-cols-2 gap-4">
      <div class="form-panel">
        <h2 class="form-panel-title">Bill to</h2>
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
              class="shrink-0 w-10 h-10 flex items-center justify-center rounded-xl bg-primary-600 hover:bg-primary-700 text-white transition-colors shadow-gpay">
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

      <div class="form-panel">
        <h2 class="form-panel-title">Bill details</h2>
        <div class="grid grid-cols-2 gap-2">
          <div><label class="form-label">Bill date</label><input v-model="form.issue_date" type="date" class="form-input !py-2 text-sm" /></div>
          <div><label class="form-label">Pay by</label><input v-model="form.due_date" type="date" class="form-input !py-2 text-sm" /></div>
          <div class="col-span-2">
            <label class="form-label">Place of supply <span class="text-danger-500">*</span></label>
            <select v-model="form.place_of_supply" class="form-select !py-2 text-sm">
              <option value="">Select state</option>
              <option v-for="s in states" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
          <div class="col-span-2">
            <label class="form-label">Bill type</label>
            <select v-model="form.invoice_type" class="form-select !py-2 text-sm">
              <option value="tax_invoice">Tax invoice (GST)</option>
              <option value="bill_of_supply">Bill of supply</option>
              <option value="retail">Retail invoice</option>
            </select>
          </div>
        </div>
      </div>
      </div>

      <!-- Items -->
      <div class="card overflow-hidden">
        <div class="px-4 py-2.5 border-b border-google-divider flex items-center justify-between bg-surface-dim/50">
          <h2 class="form-panel-title mb-0">Line items</h2>
          <div class="flex items-center gap-2">
            <span class="text-xs text-google-muted font-medium">{{ form.items.length }} line{{ form.items.length > 1 ? 's' : '' }}</span>
            <button type="button" @click="addItem" class="btn-primary btn-sm !py-1.5 !px-3 text-xs">
              + Add line
            </button>
          </div>
        </div>

        <div class="items-grid-header">
          <span class="col-span-3">Product</span>
          <span class="col-span-3">Description</span>
          <span class="col-span-1">Unit</span>
          <span class="col-span-1">Qty</span>
          <span class="col-span-1">Price</span>
          <span class="col-span-1">GST</span>
          <span class="col-span-2 text-right">Amount</span>
        </div>

        <div class="divide-y divide-google-divider/50 lg:border lg:border-t-0 lg:border-google-divider lg:rounded-b-lg">
          <div v-for="(it, i) in form.items" :key="i" class="p-4 space-y-3 item-row-desktop">

            <!-- Desktop: compact row -->
            <div class="hidden lg:contents">
              <div class="col-span-3 flex gap-1">
                <select v-if="products.length" v-model="it.product_id" class="form-select !py-2 text-xs min-w-0 flex-1" @change="pickProduct(i, it.product_id)">
                  <option :value="null">Manual</option>
                  <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <button type="button" @click="openAddProduct(i)" class="shrink-0 w-9 h-9 rounded-lg bg-primary-600 text-white flex items-center justify-center" title="New product">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                </button>
              </div>
              <div class="col-span-3">
                <input v-model="it.description" type="text" class="form-input !py-2 text-sm" placeholder="Description" required />
              </div>
              <div class="col-span-1">
                <select v-model="it.unit" class="form-select !py-2 text-xs"><option v-for="u in units" :key="u">{{ u }}</option></select>
              </div>
              <div class="col-span-1">
                <input v-model="it.quantity" type="number" min="0.001" step="0.001" class="form-input !py-2 text-sm tabular-nums" />
              </div>
              <div class="col-span-1">
                <input v-model="it.unit_price" type="number" min="0" step="0.01" class="form-input !py-2 text-sm tabular-nums" />
              </div>
              <div class="col-span-1">
                <select v-model="it.gst_rate" class="form-select !py-2 text-xs" :disabled="form.invoice_type === 'bill_of_supply'">
                  <option v-for="r in gstRates" :key="r" :value="r">{{ form.invoice_type === 'bill_of_supply' ? '0' : r }}</option>
                </select>
              </div>
              <div class="col-span-2 flex items-center justify-end gap-2">
                <span class="text-sm font-bold tabular-nums text-ink">{{ inr(lineTotal(it)) }}</span>
                <button v-if="form.items.length > 1" type="button" @click="removeItem(i)" class="p-1.5 text-danger-500 hover:bg-danger-50 rounded-lg" title="Remove">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
              </div>
            </div>

            <!-- Mobile: stacked -->
            <div class="lg:hidden space-y-3">
              <div class="flex items-center gap-2">
                <div v-if="products.length" class="flex-1">
                  <label class="form-label">Product</label>
                  <select v-model="it.product_id" class="form-select" @change="pickProduct(i, it.product_id)">
                    <option :value="null">— Manual —</option>
                    <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} — {{ inr(p.price) }}</option>
                  </select>
                </div>
                <button type="button" @click="openAddProduct(i)" class="shrink-0 w-10 h-10 mt-6 rounded-xl bg-primary-600 text-white flex items-center justify-center shadow-gpay">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                </button>
              </div>
              <div>
                <label class="form-label">Description *</label>
                <input v-model="it.description" type="text" class="form-input" required />
              </div>
              <div class="grid grid-cols-2 gap-2">
                <div><label class="form-label">Unit</label><select v-model="it.unit" class="form-select"><option v-for="u in units" :key="u">{{ u }}</option></select></div>
                <div><label class="form-label">Qty</label><input v-model="it.quantity" type="number" min="0.001" step="0.001" class="form-input" /></div>
                <div><label class="form-label">Price (₹)</label><input v-model="it.unit_price" type="number" min="0" step="0.01" class="form-input" /></div>
                <div>
                  <label class="form-label">GST</label>
                  <select v-model="it.gst_rate" class="form-select" :disabled="form.invoice_type === 'bill_of_supply'">
                    <option v-for="r in gstRates" :key="r" :value="r">{{ form.invoice_type === 'bill_of_supply' ? 'No GST' : r + '%' }}</option>
                  </select>
                </div>
              </div>
              <div class="flex items-center justify-between">
                <p class="text-sm font-bold">{{ inr(lineTotal(it)) }}</p>
                <button v-if="form.items.length > 1" type="button" @click="removeItem(i)" class="text-xs text-danger-500 font-semibold">Remove</button>
              </div>
            </div>
          </div>
        </div>

        <div class="px-4 py-2 border-t border-google-divider lg:hidden">
          <button type="button" @click="addItem" class="w-full py-2.5 text-sm font-semibold text-primary-600 border border-dashed border-primary-200 rounded-xl hover:bg-primary-50">
            + Add another item
          </button>
        </div>
      </div>

      <!-- Notes (main column, compact) -->
      <div class="form-panel lg:col-span-full">
        <h2 class="form-panel-title">Notes & terms</h2>
        <div class="grid lg:grid-cols-2 gap-3">
          <div>
            <label class="form-label">Message to customer</label>
            <textarea v-model="form.notes" rows="2" class="form-textarea !min-h-[60px] text-sm" placeholder="Thank you for your business"></textarea>
          </div>
          <div>
            <label class="form-label">Terms</label>
            <textarea v-model="form.terms" rows="2" class="form-textarea !min-h-[60px] text-sm" placeholder="Payment due within 30 days"></textarea>
          </div>
        </div>
      </div>

      <div v-if="error" class="lg:hidden text-sm text-danger-600 bg-danger-50 border border-danger-100 rounded-xl px-4 py-3 font-medium">{{ error }}</div>

      </div><!-- /form-main -->

      <!-- Sidebar: totals + recurring (sticky on desktop) -->
      <aside class="form-sidebar">
        <div class="card-premium p-4 space-y-3">
          <h2 class="form-panel-title">Summary</h2>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between text-google-muted"><span>Subtotal</span><span class="font-semibold text-ink tabular-nums">{{ inr(totals.subtotal) }}</span></div>
            <div v-if="totals.tax > 0" class="flex justify-between text-google-muted"><span>GST</span><span class="font-semibold text-ink tabular-nums">{{ inr(totals.tax) }}</span></div>
            <div class="flex justify-between items-center pt-3 border-t border-google-divider">
              <span class="font-bold text-ink">Grand total</span>
              <span class="text-2xl font-bold text-primary-600 tabular-nums">{{ inr(totals.total) }}</span>
            </div>
          </div>
          <div class="hidden lg:flex flex-col gap-2 pt-2">
            <button type="submit" class="btn-primary w-full" :disabled="loading">
              {{ loading ? 'Saving…' : isEdit ? 'Save changes' : 'Create bill' }}
            </button>
            <button type="button" @click="router.back()" class="btn-outline w-full">Cancel</button>
          </div>
        </div>

        <div class="form-panel-compact">
          <div class="flex items-center justify-between gap-2">
            <div>
              <h2 class="form-panel-title mb-0">Auto-repeat</h2>
              <p class="text-[11px] text-google-muted mt-0.5">Schedule recurring bills</p>
            </div>
            <button type="button" @click="form.is_recurring = !form.is_recurring"
              class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors"
              :class="form.is_recurring ? 'bg-primary-600' : 'bg-surface-muted'">
              <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                :class="form.is_recurring ? 'translate-x-6' : 'translate-x-1'"></span>
            </button>
          </div>
          <div v-if="form.is_recurring" class="grid grid-cols-2 gap-2 pt-2">
            <div><label class="form-label">Every</label><input v-model="form.recur_every" type="number" min="1" class="form-input !py-2 text-sm" /></div>
            <div>
              <label class="form-label">Period</label>
              <select v-model="form.recur_period" class="form-select !py-2 text-sm">
                <option v-for="p in recurPeriods" :key="p" :value="p">{{ p }}</option>
              </select>
            </div>
            <div class="col-span-2"><label class="form-label">Until</label><input v-model="form.recur_ends_at" type="date" class="form-input !py-2 text-sm" /></div>
          </div>
        </div>

        <div v-if="error" class="hidden lg:block text-sm text-danger-600 bg-danger-50 border border-danger-100 rounded-xl px-4 py-3 font-medium">{{ error }}</div>
      </aside>

    </form>
    </div>

    <!-- Mobile sticky footer -->
    <div class="form-footer-mobile lg:hidden">
      <div class="flex-1 min-w-0 mr-2">
        <p class="text-[10px] font-bold text-google-muted uppercase">Total</p>
        <p class="text-lg font-bold text-primary-600 tabular-nums">{{ inr(totals.total) }}</p>
      </div>
      <button type="button" @click="router.back()" class="btn-outline">Cancel</button>
      <button type="submit" form="invoice-form" class="btn-primary" :disabled="loading">
        {{ loading ? '…' : isEdit ? 'Save' : 'Create' }}
      </button>
    </div>
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
