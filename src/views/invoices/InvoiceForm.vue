<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { task, item, list, all } from '../../api'
import { useToast } from '../../composables/useToast'
import { useBusinessStore } from '../../stores/business'
import { inr } from '../../utils/currency'
import { today, addDays } from '../../utils/date'
import { calcInvoice } from '../../utils/invoice'
import { useFormKeys, handleLineItemTab } from '../../composables/useFormKeys'

const router        = useRouter()
const route         = useRoute()
const emit          = defineEmits(['refresh'])
const toast         = useToast()
const businessStore = useBusinessStore()

useFormKeys({ formId: 'invoice-form', autoFocus: false })

const clients      = ref([])
const products     = ref([])
const taxRates     = ref([])
const states       = ref([])
const loading      = ref(false)
const clientSearch = ref('')

const activeItemIndex = ref(0)
const businessName    = ref('')
const invoiceNumber   = ref('')

const editingInvoiceDetails = ref(false)
const editingClient         = ref(false)

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
const newClient = ref({ name: '', mobile: '', email: '', type: 'individual' })

async function saveNewClient() {
  addClientError.value = ''
  if (!newClient.value.name) return addClientError.value = 'Customer name is required.'
  addingClient.value = true
  try {
    const res = await task('Client', 'create', { ...newClient.value, type: 'individual' })
    const created = res.data?.data
    clients.value.push(created)
    clients.value.sort((a, b) => (a.name || '').localeCompare(b.name || ''))
    form.value.client_id = created.id
    showAddClient.value = false
    clientSearch.value = ''
    newClient.value = { name: '', mobile: '', email: '', type: 'individual' }
  } catch (e) {
    addClientError.value = e.response?.data?.message || 'Failed to save customer.'
  }
  addingClient.value = false
}

function openAddClient() {
  addClientError.value = ''
  newClient.value = { name: clientSearch.value, mobile: '', email: '', type: 'individual' }
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
function qtyStep(unit) {
  if (['Nos', 'Pcs', 'Box', 'Set', 'Pair'].includes(unit)) return 1
  if (unit === 'Hrs') return 0.5
  if (['Kg', 'Ltr', 'Mtr'].includes(unit)) return 0.1
  return 1
}
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
  if (isEdit.value) {
    invoiceNumber.value = inv.number || ''
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
  businessName.value = bizRes.data?.data?.name || ''
  if (bizStateId) businessStore.setStateId(bizStateId)

  if (!isEdit.value && !route.query.duplicate) {
    form.value.place_of_supply = businessStore.stateId || ''
  }

  const sourceId = isEdit.value ? route.params.id : route.query.duplicate
  if (sourceId) {
    try {
      const fetches = [item('Invoice', { id: sourceId })]
      if (isEdit.value) fetches.push(list('Invoice:items', { invoice_id: sourceId }))
      const [invRes, itmRes] = await Promise.all(fetches)
      const inv = invRes.data?.data
      if (inv) {
        if (isEdit.value) inv.items = itmRes?.data?.data || []
        applySourceInvoice(inv)
      }
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

function addItem() {
  form.value.items.push(blankItem())
  activeItemIndex.value = form.value.items.length - 1
}

function removeItem(i) {
  if (form.value.items.length > 1) {
    form.value.items.splice(i, 1)
    if (activeItemIndex.value >= form.value.items.length) {
      activeItemIndex.value = form.value.items.length - 1
    }
  }
}

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

function onLastFieldTab(i, e) {
  handleLineItemTab(i, e, form.value.items, addItem, '.line-desc')
}

function lineTotal(it) {
  return parseFloat(it.quantity||0) * parseFloat(it.unit_price||0)
       * (1 - parseFloat(it.discount_pct||0)/100)
       * (1 + parseFloat(it.gst_rate||0)/100)
}

function lineTaxAmount(it) {
  const qty = parseFloat(it.quantity || 0)
  const price = parseFloat(it.unit_price || 0)
  const disc = parseFloat(it.discount_pct || 0)
  const gst = parseFloat(it.gst_rate || 0)
  return qty * price * (1 - disc / 100) * (gst / 100)
}

function lineDiscountAmount(it) {
  const qty = parseFloat(it.quantity || 0)
  const price = parseFloat(it.unit_price || 0)
  const disc = parseFloat(it.discount_pct || 0)
  return qty * price * (disc / 100)
}

function formatDateDisplay(d) {
  if (!d) return ''
  const parts = d.split('-')
  if (parts.length === 3) {
    return `${parts[2]}/${parts[1]}/${parts[0]}`
  }
  return d
}

const placeOfSupplyName = computed(() => {
  const s = states.value.find(s => s.id == form.value.place_of_supply)
  return s ? s.name : 'Not selected'
})

async function submit() {
  error.value = ''
  if (!form.value.client_id) return (error.value = 'Please choose a customer.')
  if (!form.value.items.some(i => i.description)) return (error.value = 'Please add at least one item.')
  loading.value = true
  try {
    if (isEdit.value) {
      await task('Invoice', 'update', { ...form.value, id: route.params.id })
      emit('refresh')
      toast.success('Invoice updated.')
      router.push(`/invoices/${route.params.id}`)
    } else {
      const { data } = await task('Invoice', 'create', form.value)
      emit('refresh')
      toast.success('Invoice created.')
      router.push(`/invoices/${data.data.invoice_id}?newly_created=true`)
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save. Please check the details and try again.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <!-- Page shell -->
  <div class="inv-shell">

    <!-- Toolbar: Invozen mockup style on mobile -->
    <div class="inv-toolbar max-lg:bg-[#1a56db] max-lg:text-white max-lg:border-none">
      <div class="flex items-center gap-3 min-w-0">
        <button type="button" @click="router.back()" class="inv-back-btn max-lg:bg-white/10 max-lg:text-white max-lg:hover:bg-white/20">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <h1 class="inv-page-title max-lg:text-white max-lg:text-lg max-lg:font-semibold">
          {{ isEdit ? 'Edit Invoice' : isDuplicate ? 'Duplicate Invoice' : 'Create Invoice' }}
        </h1>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" @click="router.back()" class="inv-btn-secondary hidden sm:inline-flex">Cancel</button>
        <button type="submit" form="invoice-form" class="inv-btn-primary hidden sm:inline-flex" :disabled="loading" title="Ctrl+Enter">
          {{ loading ? 'Saving…' : isEdit ? 'Save Changes' : 'Save and Continue' }}
          <kbd class="ml-1.5 text-[10px] opacity-60 bg-white/20 px-1.5 py-0.5 rounded">Ctrl+Enter</kbd>
        </button>
        <!-- Mockup avatar image displayed on right side for mobile -->
        <div class="lg:hidden w-8 h-8 rounded-full border border-white/20 overflow-hidden bg-white/10 flex items-center justify-center shrink-0">
          <svg class="w-4 h-4 text-white/80" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
        </div>
      </div>
    </div>

    <!-- Body: two-column on desktop -->
    <div class="inv-body">
      <form id="invoice-form" @submit.prevent="submit" class="inv-layout">

        <!-- ── LEFT: Main content ── -->
        <div class="inv-main">

          <!-- Mobile Only Section: Invoice Details & Business Info -->
          <div class="lg:hidden space-y-4">
            
            <!-- Invoice Details Card (Mockup Style Summary-First View) -->
            <div>
              <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5 px-1">Invoice Details</p>

              <div class="inv-card p-4">
                <!-- 1. Collapsed state (Text grid matching Mockup screenshot) -->
                <div v-if="!editingInvoiceDetails" class="flex items-center justify-between gap-2 text-sm">
                  <div class="flex-1 grid grid-cols-3 gap-2">
                    <div>
                      <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Number</p>
                      <p class="font-bold text-gray-800 mt-1 truncate">#{{ invoiceNumber || 'Draft' }}</p>
                    </div>
                    <div>
                      <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Invoice Date</p>
                      <p class="font-bold text-gray-800 mt-1 truncate">{{ formatDateDisplay(form.issue_date) }}</p>
                    </div>
                    <div>
                      <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">Due Date</p>
                      <p class="font-bold text-gray-800 mt-1 truncate">{{ formatDateDisplay(form.due_date) }}</p>
                    </div>
                  </div>
                  <!-- Edit Icon (Blue outline circular icon with pencil inside card) -->
                  <button type="button" @click="editingInvoiceDetails = true"
                    class="w-9 h-9 rounded-full bg-blue-50 text-[#1a56db] flex items-center justify-center shrink-0 border border-blue-100/50 hover:bg-blue-100 transition ml-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                  </button>
                </div>

                <!-- 2. Expanded editor state (Inputs show only when pencil is clicked) -->
                <div v-else class="flex items-start justify-between gap-2 animate-fade-in-up">
                  <div class="flex-1 grid grid-cols-2 gap-3">
                    <div>
                      <label class="inv-label">Number</label>
                      <input :value="invoiceNumber || 'Draft'" type="text" class="inv-input !bg-gray-100" disabled />
                    </div>
                    <div>
                      <label class="inv-label">Bill Type</label>
                      <select v-model="form.invoice_type" class="inv-select !bg-white">
                        <option value="tax_invoice">Tax Invoice (with GST)</option>
                        <option value="bill_of_supply">Bill of Supply</option>
                        <option value="retail">Retail Invoice</option>
                      </select>
                    </div>
                    <div>
                      <label class="inv-label">Invoice Date</label>
                      <input v-model="form.issue_date" type="date" class="inv-input !bg-white" />
                    </div>
                    <div>
                      <label class="inv-label">Due Date</label>
                      <input v-model="form.due_date" type="date" class="inv-input !bg-white" />
                    </div>
                    <div class="col-span-2">
                      <label class="inv-label">Place of Supply *</label>
                      <select v-model="form.place_of_supply" class="inv-select !bg-white">
                        <option value="">Select state</option>
                        <option v-for="s in states" :key="s.id" :value="s.id">{{ s.name }}</option>
                      </select>
                    </div>
                  </div>
                  <!-- Save checkmark button placed inside the card on the right -->
                  <button type="button" @click="editingInvoiceDetails = false"
                    class="w-9 h-9 rounded-full bg-[#1a56db] text-white flex items-center justify-center shrink-0 border border-blue-500 hover:bg-blue-700 transition ml-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                  </button>
                </div>
              </div>
            </div>

            <!-- Business Info Card (Mockup Style List Rows) -->
            <div>
              <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5 px-1">Business Info</p>
              <div class="inv-card divide-y divide-gray-100">
                <!-- From Row (Read-only list representation) -->
                <div class="flex items-center justify-between p-4 cursor-pointer hover:bg-gray-50/30 transition">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-blue-50 text-[#1a56db] flex items-center justify-center shrink-0 shadow-sm border border-blue-100/50">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    </div>
                    <div>
                      <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">From</p>
                      <p class="text-sm font-bold text-gray-900 leading-snug">{{ businessName || 'MeiVeli Technology' }}</p>
                    </div>
                  </div>
                  <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </div>

                <!-- To Row (Expandable customer selector) -->
                <div @click="editingClient = !editingClient" class="flex items-center justify-between p-4 cursor-pointer hover:bg-gray-50/30 transition">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 shadow-sm border border-emerald-100/50">
                      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <div>
                      <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">To</p>
                      <p class="text-sm font-bold text-gray-800 leading-snug" :class="!form.client_id ? 'text-[#1a56db]' : ''">
                        {{ selectedClient?.name || 'Add Client Information' }}
                      </p>
                    </div>
                  </div>
                  <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="editingClient ? 'rotate-90' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </div>

                <!-- Expanded search & selection area under To Row -->
                <div v-if="editingClient" class="p-4 bg-gray-50/50 space-y-3 animate-fade-in-up">
                  <div class="relative flex items-center gap-2">
                    <div class="relative flex-1">
                      <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                      <input v-model="clientSearch" type="text" placeholder="Search customer…" class="inv-input w-full pl-9 !bg-white" />
                    </div>
                    <button type="button" @click="openAddClient" title="Add new"
                      class="shrink-0 w-9 h-9 flex items-center justify-center rounded-lg bg-primary-600 hover:bg-primary-700 text-white transition">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    </button>
                  </div>
                  
                  <div v-if="clientSearch || filteredClients.length > 0" class="max-h-48 overflow-y-auto rounded-lg border border-gray-200 divide-y divide-gray-50 bg-white shadow-sm">
                    <div v-if="!filteredClients.length" class="px-3 py-3 text-center text-sm text-gray-400">No match for "{{ clientSearch }}"</div>
                    <button v-for="c in filteredClients" :key="c.id" type="button"
                      @click="form.client_id = c.id; clientSearch = ''; editingClient = false"
                      class="w-full flex items-center gap-3 px-3 py-2.5 hover:bg-gray-50 transition text-left">
                      <div class="w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center shrink-0">
                        <span class="text-[10px] font-bold text-gray-500">{{ c.name?.charAt(0)?.toUpperCase() }}</span>
                      </div>
                      <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate">{{ c.name }}</p>
                        <p class="text-xs text-gray-400 truncate">{{ c.mobile || c.email || 'Customer' }}</p>
                      </div>
                    </button>
                  </div>
                </div>

              </div>
            </div>

          </div>

          <!-- Line Items card -->
          <div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5 px-1 lg:hidden">Item Detail</p>
            <div class="inv-card">
              <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between hidden lg:flex">
                <h2 class="text-sm font-semibold text-gray-800">Items</h2>
                <span class="text-xs text-gray-400">{{ form.items.length }} item{{ form.items.length > 1 ? 's' : '' }}</span>
              </div>

              <!-- Desktop table (Redesigned Grid) -->
              <div class="hidden lg:block">
                <!-- Table Header -->
                <div class="grid grid-cols-12 gap-4 px-5 py-2.5 text-[10px] font-bold uppercase tracking-wider text-gray-400 bg-gray-50 border-b border-gray-100">
                  <span class="col-span-5">Items</span>
                  <span class="col-span-2 text-center">QTY / Unit</span>
                  <span class="col-span-3 text-right">Price / Tax</span>
                  <span class="col-span-2 text-right pr-2">Amount</span>
                </div>
                <!-- Rows -->
                <div class="divide-y divide-gray-100">
                  <div v-for="(it, i) in form.items" :key="i" class="grid grid-cols-12 gap-4 px-5 py-4 items-start hover:bg-gray-50/20 transition-colors">
                    <!-- Column 1: Item details + product picker -->
                    <div class="col-span-5 space-y-2">
                      <input v-model="it.description" type="text" class="inv-input font-medium !bg-white line-desc" placeholder="Enter item name or description" required />
                      <select v-if="products.length" v-model="it.product_id" class="inv-select text-xs text-gray-400 w-full !bg-white" @change="pickProduct(i, it.product_id)">
                        <option :value="null">— Select from products —</option>
                        <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                      </select>
                    </div>

                    <!-- Column 2: QTY + Unit -->
                    <div class="col-span-2 space-y-2">
                      <input v-model="it.quantity" type="number" :min="qtyStep(it.unit)" :step="qtyStep(it.unit)" class="inv-input text-center tabular-nums !bg-white" />
                      <select v-model="it.unit" class="inv-select text-center text-xs !bg-white">
                        <option v-for="u in units" :key="u">{{ u }}</option>
                      </select>
                    </div>

                    <!-- Column 3: Price + Tax/GST -->
                    <div class="col-span-3 space-y-2">
                      <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₹</span>
                        <input v-model="it.unit_price" type="number" min="0" step="0.01" class="inv-input pl-7 text-right tabular-nums !bg-white" placeholder="0.00" />
                      </div>
                      <select v-model="it.gst_rate" class="inv-select text-center text-xs !bg-white" @keydown.tab="onLastFieldTab(i, $event)">
                        <option v-for="r in gstRates" :key="r" :value="r">{{ r }}% GST</option>
                      </select>
                    </div>

                    <!-- Column 4: calculated line amount + remove button -->
                    <div class="col-span-2 flex flex-col items-end justify-between h-[86px] py-1">
                      <span class="text-sm font-semibold text-gray-800 tabular-nums pr-2">{{ inr(lineTotal(it)) }}</span>
                      <button v-if="form.items.length > 1" type="button" @click="removeItem(i)"
                        class="mr-1 w-7 h-7 flex items-center justify-center text-gray-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition" title="Remove">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Desktop Add item -->
                <div class="px-5 py-3 border-t border-gray-100 bg-gray-50/50">
                  <button type="button" @click="addItem"
                    class="flex items-center gap-2 text-sm text-primary-600 font-medium hover:text-primary-700 transition">
                    <span class="w-5 h-5 rounded-full border-2 border-primary-300 flex items-center justify-center text-primary-500 text-xs leading-none">+</span>
                    Add item
                  </button>
                </div>
              </div>

              <!-- Mobile: Accordion items (Invozen Mockup Visual Layout Match) -->
              <div class="lg:hidden divide-y divide-gray-100">
                <div v-for="(it, i) in form.items" :key="i" class="transition-all duration-200">
                  
                  <!-- Collapsed details representation (Matches Item summary rows in the mockup) -->
                  <div class="p-4 space-y-1 hover:bg-gray-50/30">
                    <!-- Item Header: Name on left, Qty x Price on right -->
                    <div class="flex justify-between items-start">
                      <p class="font-bold text-gray-800 text-sm leading-tight pr-4 truncate">{{ it.description || 'New Item Detail' }}</p>
                      <p class="text-xs font-semibold text-gray-500 tabular-nums shrink-0">
                        {{ it.quantity }} × {{ inr(it.unit_price || 0) }}
                      </p>
                    </div>
                    <!-- Tax & Discount sub-rows (mockup layout structure) -->
                    <div class="flex justify-between text-xs text-gray-400">
                      <span>Tax ({{ it.gst_rate }}%)</span>
                      <span class="tabular-nums">{{ inr(lineTaxAmount(it)) }}</span>
                    </div>
                    <div v-if="it.discount_pct > 0" class="flex justify-between text-xs text-gray-400">
                      <span>Discount ({{ it.discount_pct }}%)</span>
                      <span class="tabular-nums">-{{ inr(lineDiscountAmount(it)) }}</span>
                    </div>
                    <!-- Total line amount + Expand action -->
                    <div class="flex justify-between items-center pt-2 text-sm font-bold text-gray-800 border-t border-gray-100/50 mt-1.5">
                      <button type="button" @click="activeItemIndex = (activeItemIndex === i ? null : i)" 
                        class="text-xs text-primary-600 hover:underline flex items-center gap-1 font-bold">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        Edit Details
                      </button>
                      <span class="tabular-nums text-gray-900 font-extrabold">{{ inr(lineTotal(it)) }}</span>
                    </div>
                  </div>

                  <!-- Expanded input fields for edit -->
                  <div v-show="activeItemIndex === i" class="px-4 pb-5 pt-3 space-y-3 bg-gray-50/70 border-t border-gray-100">
                    <div class="flex items-center gap-2">
                      <div v-if="products.length" class="flex-1">
                        <label class="inv-label">Select Catalog Product</label>
                        <select v-model="it.product_id" class="inv-select w-full !bg-white" @change="pickProduct(i, it.product_id)">
                          <option :value="null">— Select product —</option>
                          <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} — {{ inr(p.price) }}</option>
                        </select>
                      </div>
                      <button type="button" @click="openAddProduct(i)" class="shrink-0 w-9 h-9 mt-5 rounded-xl bg-primary-600 text-white flex items-center justify-center">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                      </button>
                    </div>
                    <div>
                      <label class="inv-label">Item Name / Description *</label>
                      <input v-model="it.description" type="text" class="inv-input w-full !bg-white" required placeholder="What are you billing for?" />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                      <div>
                        <label class="inv-label">Unit</label>
                        <select v-model="it.unit" class="inv-select w-full !bg-white">
                          <option v-for="u in units" :key="u">{{ u }}</option>
                        </select>
                      </div>
                      <div>
                        <label class="inv-label">Qty</label>
                        <input v-model="it.quantity" type="number" :min="qtyStep(it.unit)" :step="qtyStep(it.unit)" class="inv-input w-full !bg-white" />
                      </div>
                      <div>
                        <label class="inv-label">Price (₹)</label>
                        <input v-model="it.unit_price" type="number" min="0" step="0.01" class="inv-input w-full !bg-white" placeholder="0.00" />
                      </div>
                      <div>
                        <label class="inv-label">GST Tax</label>
                        <select v-model="it.gst_rate" class="inv-select w-full !bg-white">
                          <option v-for="r in gstRates" :key="r" :value="r">{{ r }}% GST</option>
                        </select>
                      </div>
                    </div>
                    <div class="flex items-center justify-between pt-2 border-t border-gray-100/50">
                      <span class="text-sm font-extrabold text-primary-600">{{ inr(lineTotal(it)) }}</span>
                      <button v-if="form.items.length > 1" type="button" @click="removeItem(i)" class="text-xs text-red-500 font-bold hover:underline">Remove Item</button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Centered Add Item Pill Button (for Mobile) -->
              <div class="lg:hidden flex justify-center py-4 border-t border-gray-100 bg-white">
                <button type="button" @click="addItem"
                  class="inline-flex items-center gap-1.5 px-6 py-2.5 rounded-full border-2 border-primary-600 text-primary-600 font-bold text-sm hover:bg-primary-50 transition shadow-sm">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                  Add Item
                </button>
              </div>

              <!-- Summary totals at bottom of Item card (for Mobile) -->
              <div class="lg:hidden p-4 border-t border-gray-100 bg-gray-50/50 space-y-2.5 text-sm text-gray-600">
                <div class="flex justify-between font-medium">
                  <span>Sub Total :</span>
                  <span class="font-bold text-gray-900 tabular-nums">{{ inr(totals.subtotal) }}</span>
                </div>
                <div v-if="totals.tax > 0" class="flex justify-between font-medium">
                  <span>Tax :</span>
                  <span class="font-bold text-gray-900 tabular-nums">{{ inr(totals.tax) }}</span>
                </div>
                <div class="flex justify-between items-center pt-2.5 border-t border-gray-200 font-extrabold text-gray-900">
                  <span class="text-base">Total :</span>
                  <span class="text-lg tabular-nums text-primary-600">{{ inr(totals.total) }}</span>
                </div>
              </div>

            </div>
          </div>

          <!-- Notes / Terms -->
          <div class="inv-card p-5 space-y-3">
            <h2 class="text-sm font-semibold text-gray-700">Notes / Terms</h2>
            <div class="grid sm:grid-cols-2 gap-3">
              <div>
                <label class="inv-label">Message to customer</label>
                <textarea v-model="form.notes" rows="3" class="inv-textarea w-full !bg-white" placeholder="Thank you for your business"></textarea>
              </div>
              <div>
                <label class="inv-label">Terms & conditions</label>
                <textarea v-model="form.terms" rows="3" class="inv-textarea w-full !bg-white" placeholder="Payment due within 30 days"></textarea>
              </div>
            </div>
          </div>

          <!-- Auto-repeat (collapsed section) -->
          <div class="inv-card p-5">
            <div class="flex items-center justify-between">
              <div>
                <h2 class="text-sm font-semibold text-gray-700">Auto-repeat</h2>
                <p class="text-xs text-gray-400 mt-0.5">Schedule this bill to repeat automatically</p>
              </div>
              <button type="button" @click="form.is_recurring = !form.is_recurring"
                class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors duration-200"
                :class="form.is_recurring ? 'bg-primary-600' : 'bg-gray-200'">
                <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform duration-200"
                  :class="form.is_recurring ? 'translate-x-6' : 'translate-x-1'"></span>
              </button>
            </div>
            <div v-if="form.is_recurring" class="grid grid-cols-3 gap-3 mt-4 pt-4 border-t border-gray-100">
              <div>
                <label class="inv-label">Every</label>
                <input v-model="form.recur_every" type="number" min="1" class="inv-input w-full !bg-white" />
              </div>
              <div>
                <label class="inv-label">Period</label>
                <select v-model="form.recur_period" class="inv-select w-full !bg-white">
                  <option v-for="p in recurPeriods" :key="p" :value="p">{{ p }}</option>
                </select>
              </div>
              <div>
                <label class="inv-label">Until</label>
                <input v-model="form.recur_ends_at" type="date" class="inv-input w-full !bg-white" />
              </div>
            </div>
          </div>

        </div><!-- /inv-main -->

        <!-- ── RIGHT: Sidebar (Hidden on Mobile) ── -->
        <aside class="inv-sidebar hidden lg:block">

          <!-- Bill To -->
          <div class="inv-card">
            <div class="px-5 py-3.5 border-b border-gray-100">
              <h2 class="text-sm font-semibold text-gray-800">Bill To</h2>
              <p class="text-xs text-gray-400 mt-0.5">*Select one recipient</p>
            </div>
            <div class="p-4">
              <div v-if="form.client_id" class="flex items-center gap-3 p-3 bg-primary-50 rounded-xl border border-primary-100">
                <div class="w-9 h-9 rounded-full bg-primary-600 flex items-center justify-center shrink-0">
                  <span class="text-white text-sm font-bold">{{ selectedClient?.name?.charAt(0)?.toUpperCase() }}</span>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-semibold text-gray-900 truncate">{{ selectedClient?.name }}</p>
                  <p class="text-xs text-gray-500 truncate">{{ selectedClient?.mobile || selectedClient?.email || '—' }}</p>
                </div>
                <button type="button" @click="form.client_id = ''; clientSearch = ''" class="text-xs text-primary-600 font-semibold shrink-0 hover:underline">Change</button>
              </div>
              <div v-else class="space-y-2">
                <div class="relative flex items-center gap-2">
                  <div class="relative flex-1">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input v-model="clientSearch" type="text" placeholder="Search customer…" class="inv-input w-full pl-9" />
                  </div>
                  <button type="button" @click="openAddClient" title="Add new"
                    class="shrink-0 w-9 h-9 flex items-center justify-center rounded-lg bg-primary-600 hover:bg-primary-700 text-white transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                  </button>
                </div>
                <div v-if="clientSearch || filteredClients.length > 0" class="max-h-48 overflow-y-auto rounded-lg border border-gray-200 divide-y divide-gray-50 bg-white shadow-sm">
                  <div v-if="!filteredClients.length" class="px-3 py-3 text-center text-sm text-gray-400">No match for "{{ clientSearch }}"</div>
                  <button v-for="c in filteredClients" :key="c.id" type="button"
                    @click="form.client_id = c.id; clientSearch = ''"
                    class="w-full flex items-center gap-3 px-3 py-2.5 hover:bg-gray-50 transition text-left">
                    <div class="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center shrink-0">
                      <span class="text-xs font-bold text-gray-500">{{ c.name?.charAt(0)?.toUpperCase() }}</span>
                    </div>
                    <div class="min-w-0">
                      <p class="text-sm font-medium text-gray-800 truncate">{{ c.name }}</p>
                      <p class="text-xs text-gray-400 truncate">{{ c.mobile || c.email || 'Customer' }}</p>
                    </div>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Invoice Information -->
          <div class="inv-card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-800">Invoice Information</h2>

            <div>
              <label class="inv-label">Bill Type</label>
              <select v-model="form.invoice_type" class="inv-select w-full">
                <option value="tax_invoice">Tax Invoice (with GST)</option>
                <option value="bill_of_supply">Bill of Supply</option>
                <option value="retail">Retail Invoice</option>
              </select>
            </div>

            <div>
              <label class="inv-label">Place of Supply <span class="text-red-500">*</span></label>
              <select v-model="form.place_of_supply" class="inv-select w-full">
                <option value="">Select state</option>
                <option v-for="s in states" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="inv-label">Issued Date</label>
                <input v-model="form.issue_date" type="date" class="inv-input w-full" />
              </div>
              <div>
                <label class="inv-label">Due Date</label>
                <input v-model="form.due_date" type="date" class="inv-input w-full" />
              </div>
            </div>
          </div>

          <!-- Summary + Actions -->
          <div class="inv-card p-5 space-y-3">
            <div class="space-y-2 text-sm">
              <div class="flex justify-between text-gray-500">
                <span>Subtotal</span>
                <span class="font-medium text-gray-800 tabular-nums">{{ inr(totals.subtotal) }}</span>
              </div>
              <div v-if="totals.tax > 0" class="flex justify-between text-gray-500">
                <span>Tax</span>
                <span class="font-medium text-gray-800 tabular-nums">{{ inr(totals.tax) }}</span>
              </div>
              <div v-else class="flex justify-between text-gray-400 text-xs">
                <span>Tax</span><span>—</span>
              </div>
              <div class="flex justify-between items-center pt-3 border-t border-gray-100">
                <span class="font-semibold text-gray-800">Total</span>
                <span class="text-xl font-bold tabular-nums" :class="totals.total > 0 ? 'text-primary-600' : 'text-gray-400'">{{ inr(totals.total) }}</span>
              </div>
            </div>

            <div v-if="error" class="text-xs text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2">{{ error }}</div>
          </div>

        </aside>
      </form>
    </div>

    <!-- Mobile sticky footer (Invozen Mockup Style) -->
    <div class="form-footer-mobile lg:hidden bg-white/95 backdrop-blur-md border-t border-gray-100 px-4 py-3 flex gap-3">
      <button type="submit" form="invoice-form" class="flex-1 py-3.5 px-4 rounded-xl font-bold text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 flex items-center justify-center gap-1.5 transition">
        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
        Preview
      </button>
      <button type="submit" form="invoice-form" class="flex-1 py-3.5 px-4 rounded-xl font-bold text-sm text-white flex items-center justify-center gap-1.5 transition" style="background: linear-gradient(135deg, #3b7ded 0%, #1a5fd4 100%);">
        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.286L13 21l-2.286-6.857L5 12l5.714-2.286L13 3z"/></svg>
        {{ loading ? 'Generating…' : isEdit ? 'Save Changes' : 'Generate' }}
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
