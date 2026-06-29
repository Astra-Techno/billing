<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { task, item, list, all } from '../../api'
import { useToast } from '../../composables/useToast'
import { useBusinessStore } from '../../stores/business'
import { inr } from '../../utils/currency'
import { today, addDays } from '../../utils/date'
import { calcInvoice } from '../../utils/invoice'
import { useFormKeys, handleLineItemTab } from '../../composables/useFormKeys'
import { useTour } from '../../composables/useTour'

const { startTour: startFormTour, isTourSeen: isFormTourSeen } = useTour('invoice-form', [
  { target: '[data-tour="inv-items"]', title: 'Line Items', text: 'Add products or services here. Type to search existing products or create new ones inline.' },
  { target: '[data-tour="inv-billto"]', title: 'Bill To', text: 'Select or create a customer. Use the search to find existing clients quickly.' },
  { target: '[data-tour="inv-info"]', title: 'Invoice Info', text: 'Set the bill type, dates, and place of supply for GST calculations.' },
  { target: '[data-tour="inv-summary"]', title: 'Summary', text: 'See the subtotal, tax breakdown, and grand total updated in real time.' },
  { target: '[data-tour="inv-save"]', title: 'Save Invoice', text: 'Click to save, or use Ctrl+Enter as a keyboard shortcut.' },
])

const router        = useRouter()
const route         = useRoute()
const emit          = defineEmits(['refresh'])
const toast         = useToast()
const businessStore = useBusinessStore()

useFormKeys({ formId: 'invoice-form', autoFocus: false })

// Keyboard shortcuts
function onFormShortcut(e) {
  // Skip if inside a modal or typing in an input that shouldn't trigger shortcuts
  if (e.target.closest('[class*="fixed inset-0"]')) return

  // Alt+A → Add new line item
  if (e.altKey && e.key === 'a') {
    e.preventDefault()
    addItem()
  }
  // Alt+C → Focus customer search
  if (e.altKey && e.key === 'c') {
    e.preventDefault()
    form.value.client_id = ''
    clientDropdownOpen.value = true
    nextTick(() => {
      const el = document.querySelector('.client-search-input')
      if (el) el.focus()
    })
  }
  // Alt+N → Focus notes
  if (e.altKey && e.key === 'n') {
    e.preventDefault()
    const el = document.querySelector('.notes-input')
    if (el) el.focus()
  }
  // Escape → Close product/client dropdowns
  if (e.key === 'Escape') {
    closeProductSearch()
    clientDropdownOpen.value = false
  }
}
onMounted(() => document.addEventListener('keydown', onFormShortcut))
onUnmounted(() => document.removeEventListener('keydown', onFormShortcut))

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
const showInlineCreate = computed(() => clientSearch.value.trim().length >= 2 && filteredClients.value.length === 0)
const clientDropdownOpen = ref(false)
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
    clientDropdownOpen.value = false
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

// Keep inline create name in sync with search
function onClientSearchInput() {
  clientDropdownOpen.value = true
  newClient.value.name = clientSearch.value
  newClient.value.mobile = ''
  newClient.value.email = ''
  addClientError.value = ''
}

// Inline product autocomplete
const addingProduct    = ref(false)
const addProductError  = ref('')
const productSearchIdx = ref(null)  // which line item has product dropdown open
const productSearch    = ref('')
const newProduct = ref({ type: 'service', name: '', price: '', unit: 'Nos', gst_rate: 18 })

const filteredProducts = computed(() => {
  // Use the active item's description as search query
  const idx = productSearchIdx.value
  const q = idx !== null && form.value.items[idx]
    ? form.value.items[idx].description?.trim().toLowerCase() || ''
    : productSearch.value.trim().toLowerCase()
  if (!q) return products.value.slice(0, 8)
  return products.value.filter(p => p.name?.toLowerCase().includes(q))
})
const showProductInlineCreate = computed(() => {
  const idx = productSearchIdx.value
  const q = idx !== null && form.value.items[idx]
    ? form.value.items[idx].description?.trim() || ''
    : productSearch.value.trim()
  return q.length >= 2 && filteredProducts.value.length === 0
})

function openProductSearch(i) {
  productSearchIdx.value = i
  productSearch.value = form.value.items[i]?.description || ''
  productHighlight.value = -1
  addProductError.value = ''
  newProduct.value = { type: 'service', name: form.value.items[i]?.description || '', price: '', unit: 'Nos', gst_rate: 18 }
}

const productHighlight = ref(-1)

function closeProductSearch() {
  productSearchIdx.value = null
  productSearch.value = ''
  productHighlight.value = -1
}

function selectProduct(i, p) {
  pickProduct(i, p.id)
  form.value.items[i].product_id = p.id
  closeProductSearch()
  // Auto-add a new empty row if this was the last item
  if (i === form.value.items.length - 1) {
    addItem()
  }
}

function onProductKeydown(i, e) {
  const list = filteredProducts.value
  if (!list.length) return

  if (e.key === 'ArrowDown') {
    e.preventDefault()
    productHighlight.value = (productHighlight.value + 1) % list.length
    scrollProductIntoView()
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    productHighlight.value = productHighlight.value <= 0 ? list.length - 1 : productHighlight.value - 1
    scrollProductIntoView()
  } else if (e.key === 'Enter') {
    e.preventDefault()
    if (productHighlight.value >= 0 && productHighlight.value < list.length) {
      selectProduct(i, list[productHighlight.value])
    } else {
      closeProductSearch()
    }
  }
}

function scrollProductIntoView() {
  nextTick(() => {
    const el = document.querySelector('.pd-active')
    if (el) el.scrollIntoView({ block: 'nearest' })
  })
}

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
    if (productSearchIdx.value !== null) {
      selectProduct(productSearchIdx.value, created)
    }
    newProduct.value = { type: 'service', name: '', price: '', unit: 'Nos', gst_rate: 18 }
  } catch (e) {
    addProductError.value = e.response?.data?.message || 'Failed to save product.'
  }
  addingProduct.value = false
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

  // Auto-focus first item description
  nextTick(() => {
    setTimeout(() => {
      const el = document.querySelector('.line-desc')
      if (el) el.focus()
    }, 150)
  })

  // Auto-start tour on first visit
  setTimeout(() => { if (!isFormTourSeen()) startFormTour() }, 1000)
})

watch(() => form.value.invoice_type, (type) => {
  if (type === 'bill_of_supply') {
    form.value.items.forEach(it => { it.gst_rate = 0 })
  }
})

function addItem() {
  form.value.items.push(blankItem())
  activeItemIndex.value = form.value.items.length - 1
  // Focus the new row's description input
  nextTick(() => {
    const descs = document.querySelectorAll('.line-desc')
    const last = descs[descs.length - 1]
    if (last) last.focus()
  })
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

// No longer auto-add row on Tab — let Tab flow naturally to next fields
function onLastFieldTab(i, e) {
  // Just let default tab behavior proceed
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

// Block Enter from submitting form — only Ctrl+Enter submits
function onFormEnter(e) {
  if (!e.ctrlKey && !e.metaKey) {
    e.preventDefault()
  }
}

async function submit() {
  error.value = ''
  // Strip empty rows (auto-added trailing blank) before saving
  const filledItems = form.value.items.filter(i => i.description?.trim())
  if (!filledItems.length) return (error.value = 'Please add at least one item.')
  loading.value = true
  try {
    const payload = { ...form.value, items: filledItems }
    if (isEdit.value) {
      await task('Invoice', 'update', { ...payload, id: route.params.id })
      emit('refresh')
      toast.success('Invoice updated.')
      router.push(`/invoices/${route.params.id}`)
    } else {
      const { data } = await task('Invoice', 'create', payload)
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

    <!-- Toolbar -->
    <div class="inv-toolbar max-lg:bg-[#1a56db] max-lg:text-white max-lg:border-none">
      <div class="flex items-center gap-3 min-w-0">
        <button type="button" @click="router.back()" class="inv-back-btn max-lg:bg-white/10 max-lg:text-white max-lg:hover:bg-white/20">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <h1 class="inv-page-title max-lg:text-white max-lg:text-lg max-lg:font-semibold">
          {{ isEdit ? 'Edit Invoice' : isDuplicate ? 'Duplicate Invoice' : 'Create Invoice' }}
          <button @click="startFormTour()" class="text-[10px] font-bold text-primary-500 hover:text-primary-700 ml-2 hidden lg:inline" title="Take a tour">Tour</button>
        </h1>
      </div>
      <div class="flex items-center gap-2">
        <!-- Shortcut hints — desktop only, inline in toolbar right -->
        <div class="hidden lg:flex items-center gap-3 mr-2 text-[10px] text-gray-400 font-medium">
          <span><kbd class="px-1 py-0.5 bg-gray-100 border border-gray-200 rounded text-[9px] font-mono">Alt+A</kbd> Add</span>
          <span><kbd class="px-1 py-0.5 bg-gray-100 border border-gray-200 rounded text-[9px] font-mono">Alt+C</kbd> Client</span>
          <span><kbd class="px-1 py-0.5 bg-gray-100 border border-gray-200 rounded text-[9px] font-mono">Alt+N</kbd> Notes</span>
          <span><kbd class="px-1 py-0.5 bg-gray-100 border border-gray-200 rounded text-[9px] font-mono">Ctrl+↵</kbd> Save</span>
        </div>
        <button type="button" @click="router.back()" class="inv-btn-secondary hidden sm:inline-flex">Cancel</button>
        <button type="submit" form="invoice-form" class="inv-btn-primary hidden sm:inline-flex" data-tour="inv-save" :disabled="loading" title="Ctrl+Enter">
          {{ loading ? 'Saving…' : isEdit ? 'Save Changes' : 'Save and Continue' }}
        </button>
        <!-- Mobile avatar -->
        <div class="lg:hidden w-8 h-8 rounded-full border border-white/20 overflow-hidden bg-white/10 flex items-center justify-center shrink-0">
          <svg class="w-4 h-4 text-white/80" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
        </div>
      </div>
    </div>

    <!-- Body: two-column on desktop -->
    <div class="inv-body">
      <form id="invoice-form" @submit.prevent="submit" @keydown.enter="onFormEnter" class="inv-layout">

        <!-- ── LEFT: Main content ── -->
        <div class="inv-main">

          <!-- Mobile Only: Invoice Details & Business Info -->
          <div class="lg:hidden space-y-4">

            <!-- Invoice Details Card -->
            <div>
              <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5 px-1">Invoice Details</p>
              <div class="inv-card p-4">
                <!-- Collapsed -->
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
                  <button type="button" @click="editingInvoiceDetails = true"
                    class="w-9 h-9 rounded-full bg-blue-50 text-[#1a56db] flex items-center justify-center shrink-0 border border-blue-100/50 hover:bg-blue-100 transition ml-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                  </button>
                </div>
                <!-- Expanded -->
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
                        <option value="proforma">Proforma Invoice</option>
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
                  <button type="button" @click="editingInvoiceDetails = false"
                    class="w-9 h-9 rounded-full bg-[#1a56db] text-white flex items-center justify-center shrink-0 border border-blue-500 hover:bg-blue-700 transition ml-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                  </button>
                </div>
              </div>
            </div>

            <!-- Business Info Card -->
            <div>
              <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5 px-1">Business Info</p>
              <div class="inv-card divide-y divide-gray-100">
                <!-- From Row -->
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
                <!-- To Row -->
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
                <!-- Expanded client search -->
                <div v-if="editingClient" class="p-4 bg-gray-50/50 space-y-3 animate-fade-in-up">
                  <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input v-model="clientSearch" type="text" placeholder="Type customer name or mobile…" class="inv-input w-full pl-9 !bg-white" @input="onClientSearchInput" />
                  </div>
                  <div v-if="filteredClients.length && clientSearch.trim()" class="max-h-40 overflow-y-auto rounded-lg border border-gray-200 divide-y divide-gray-50 bg-white shadow-sm">
                    <button v-for="c in filteredClients" :key="c.id" type="button"
                      @click="form.client_id = c.id; clientSearch = ''; editingClient = false"
                      class="w-full flex items-center gap-3 px-3 py-2.5 hover:bg-gray-50 transition text-left">
                      <div class="w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center shrink-0">
                        <span class="text-[10px] font-bold text-gray-500">{{ c.name?.charAt(0)?.toUpperCase() }}</span>
                      </div>
                      <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate">{{ c.name }}</p>
                        <p class="text-xs text-gray-400 truncate">{{ c.mobile || c.email || '' }}</p>
                      </div>
                    </button>
                  </div>
                  <div v-if="showInlineCreate" class="rounded-xl border border-gray-200 p-3 space-y-2.5 bg-white">
                    <p class="text-xs font-semibold text-gray-500">No match — create new customer:</p>
                    <input v-model="newClient.name" type="text" class="inv-input w-full text-sm !bg-white" placeholder="Customer name *" />
                    <input v-model="newClient.mobile" type="tel" class="inv-input w-full text-xs !bg-white" placeholder="Mobile (optional)" />
                    <input v-model="newClient.email" type="email" class="inv-input w-full text-xs !bg-white" placeholder="Email (optional)" />
                    <div v-if="addClientError" class="text-xs text-red-600 bg-red-50 rounded-lg px-2 py-1.5">{{ addClientError }}</div>
                    <button type="button" @click="saveNewClient" :disabled="addingClient"
                      class="w-full py-2.5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold transition flex items-center justify-center gap-1.5">
                      <svg v-if="addingClient" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                      {{ addingClient ? 'Creating…' : 'Create & Select' }}
                    </button>
                  </div>
                </div>
              </div>
            </div>

          </div><!-- /lg:hidden mobile section -->

          <!-- Line Items card -->
          <div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5 px-1 lg:hidden">Item Detail</p>
            <div class="inv-card !overflow-visible" data-tour="inv-items">
              <div class="px-5 py-3.5 border-b border-gray-100 items-center justify-between hidden lg:flex">
                <h2 class="text-sm font-semibold text-gray-800">Items</h2>
                <span class="text-xs text-gray-400">{{ form.items.length }} item{{ form.items.length > 1 ? 's' : '' }}</span>
              </div>

              <!-- Desktop: chip-based item rows -->
              <div class="hidden lg:block">
                <div>
                  <div v-for="(it, i) in form.items" :key="i" class="item-row group relative">
                    <!-- Item number badge -->
                    <div class="item-num mt-1">{{ i + 1 }}</div>

                    <!-- Left: description + chips -->
                    <div class="flex-1 min-w-0">
                      <!-- Description input + product autocomplete -->
                      <div class="relative">
                        <input v-model="it.description" type="text" class="inv-input font-medium !bg-white line-desc w-full" placeholder="Type item name or search product…"
                          @focus="openProductSearch(i)" @input="productSearch = it.description; newProduct.name = it.description; productHighlight = -1"
                          @keydown="onProductKeydown(i, $event)" />
                        <!-- Product autocomplete dropdown -->
                        <div v-if="productSearchIdx === i && it.description?.trim().length >= 1" class="absolute left-0 right-0 top-full mt-1 z-50 bg-white rounded-xl border border-gray-200 shadow-lg overflow-hidden">
                          <div v-if="filteredProducts.length" class="max-h-36 overflow-y-auto divide-y divide-gray-50">
                            <button v-for="(p, pi) in filteredProducts" :key="p.id" type="button"
                              @click="selectProduct(i, p)" @mouseenter="productHighlight = pi"
                              :class="['w-full flex items-center justify-between px-3 py-2 transition text-left text-xs', pi === productHighlight ? 'bg-blue-50 pd-active' : 'hover:bg-gray-50']">
                              <span class="font-medium text-gray-800 truncate">{{ p.name }}</span>
                              <span class="text-gray-400 tabular-nums shrink-0 ml-2">{{ inr(p.price) }}</span>
                            </button>
                          </div>
                          <div v-if="showProductInlineCreate" class="border-t border-gray-100 p-3 space-y-2 bg-gray-50/50">
                            <p class="text-[11px] font-semibold text-gray-500">No product found — save as new:</p>
                            <div class="grid grid-cols-2 gap-2">
                              <input v-model="newProduct.price" type="number" min="0" step="0.01" class="inv-input w-full text-xs" placeholder="Price (₹) *" />
                              <select v-model="newProduct.unit" class="inv-select w-full text-xs">
                                <option v-for="u in units" :key="u">{{ u }}</option>
                              </select>
                            </div>
                            <div v-if="addProductError" class="text-[11px] text-red-600 bg-red-50 rounded px-2 py-1">{{ addProductError }}</div>
                            <button type="button" @click="saveNewProduct" :disabled="addingProduct"
                              class="w-full py-1.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold transition">
                              {{ addingProduct ? 'Creating…' : 'Save as Product' }}
                            </button>
                          </div>
                        </div>
                        <!-- Click outside to close -->
                        <div v-if="productSearchIdx === i" class="fixed inset-0 z-40" @click="closeProductSearch"></div>
                      </div>

                      <!-- Chips row -->
                      <div class="item-chips">
                        <!-- Qty + Unit chip -->
                        <div class="chip-qty">
                          <input v-model="it.quantity" type="number" :min="qtyStep(it.unit)" :step="qtyStep(it.unit)"
                            class="w-12 text-center tabular-nums" />
                          <span class="text-gray-300 select-none">×</span>
                          <select v-model="it.unit" class="max-w-[52px]">
                            <option v-for="u in units" :key="u">{{ u }}</option>
                          </select>
                        </div>
                        <!-- Price chip -->
                        <div class="chip-price">
                          <span class="shrink-0">₹</span>
                          <input v-model="it.unit_price" type="number" min="0" step="0.01"
                            class="w-20 text-right tabular-nums" placeholder="0.00" />
                        </div>
                        <!-- GST chip -->
                        <div class="chip-tax">
                          <select v-model="it.gst_rate" @keydown.tab="onLastFieldTab(i, $event)">
                            <option v-for="r in gstRates" :key="r" :value="r">{{ r }}% GST</option>
                          </select>
                        </div>
                      </div>
                    </div>

                    <!-- Right: amount + delete -->
                    <div class="flex flex-col items-end gap-2 shrink-0 min-w-[72px]">
                      <span class="text-sm font-bold text-gray-800 tabular-nums">{{ inr(lineTotal(it)) }}</span>
                      <button v-if="form.items.length > 1" type="button" @click="removeItem(i)"
                        class="opacity-0 group-hover:opacity-100 w-7 h-7 flex items-center justify-center text-gray-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition" title="Remove">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Desktop Add item — dashed badge style -->
                <div class="px-5 py-3.5 border-t border-gray-100">
                  <button type="button" @click="addItem" @keydown.enter.prevent="addItem"
                    class="flex items-center gap-2 text-sm text-primary-600 font-medium hover:text-primary-700 transition focus:outline-none focus:ring-2 focus:ring-primary-300 rounded-lg px-1 py-1 -mx-1">
                    <span class="w-7 h-7 rounded-lg border-2 border-dashed border-primary-300 flex items-center justify-center text-primary-400 text-base font-bold leading-none shrink-0">+</span>
                    Add item
                  </button>
                </div>
              </div><!-- /desktop -->

              <!-- Mobile: Accordion items -->
              <div class="lg:hidden divide-y divide-gray-100">
                <div v-for="(it, i) in form.items" :key="i" class="transition-all duration-200">
                  <!-- Collapsed summary -->
                  <div class="p-4 space-y-1 hover:bg-gray-50/30">
                    <div class="flex justify-between items-start">
                      <p class="font-bold text-gray-800 text-sm leading-tight pr-4 truncate">{{ it.description || 'New Item Detail' }}</p>
                      <p class="text-xs font-semibold text-gray-500 tabular-nums shrink-0">
                        {{ it.quantity }} × {{ inr(it.unit_price || 0) }}
                      </p>
                    </div>
                    <div class="flex justify-between text-xs text-gray-400">
                      <span>Tax ({{ it.gst_rate }}%)</span>
                      <span class="tabular-nums">{{ inr(lineTaxAmount(it)) }}</span>
                    </div>
                    <div v-if="it.discount_pct > 0" class="flex justify-between text-xs text-gray-400">
                      <span>Discount ({{ it.discount_pct }}%)</span>
                      <span class="tabular-nums">-{{ inr(lineDiscountAmount(it)) }}</span>
                    </div>
                    <div class="flex justify-between items-center pt-2 text-sm font-bold text-gray-800 border-t border-gray-100/50 mt-1.5">
                      <button type="button" @click="activeItemIndex = (activeItemIndex === i ? null : i)"
                        class="text-xs text-primary-600 hover:underline flex items-center gap-1 font-bold">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        Edit Details
                      </button>
                      <span class="tabular-nums text-gray-900 font-extrabold">{{ inr(lineTotal(it)) }}</span>
                    </div>
                  </div>

                  <!-- Expanded edit fields with chips -->
                  <div v-show="activeItemIndex === i" class="px-4 pb-5 pt-3 space-y-3 bg-gray-50/70 border-t border-gray-100">
                    <div>
                      <label class="inv-label">Item Name / Description *</label>
                      <input v-model="it.description" type="text" class="inv-input w-full !bg-white text-sm" placeholder="Type item name or search product…"
                        @focus="openProductSearch(i)" @input="productSearch = it.description; newProduct.name = it.description; productHighlight = -1"
                        @keydown="onProductKeydown(i, $event)" />
                      <!-- Mobile product autocomplete -->
                      <div v-if="productSearchIdx === i && it.description?.trim().length >= 1" class="mt-1.5 space-y-1.5">
                        <div v-if="filteredProducts.length" class="max-h-36 overflow-y-auto rounded-lg border border-gray-200 divide-y divide-gray-50 bg-white">
                          <button v-for="p in filteredProducts" :key="p.id" type="button"
                            @click="selectProduct(i, p)"
                            class="w-full flex items-center justify-between px-3 py-2.5 hover:bg-gray-50 text-left text-sm">
                            <span class="font-medium text-gray-800 truncate">{{ p.name }}</span>
                            <span class="text-gray-400 text-xs tabular-nums shrink-0 ml-2">{{ inr(p.price) }}</span>
                          </button>
                        </div>
                        <div v-if="showProductInlineCreate" class="rounded-xl border border-gray-200 p-3 space-y-2 bg-white">
                          <p class="text-xs font-semibold text-gray-500">No product found — save as new:</p>
                          <div class="grid grid-cols-2 gap-2">
                            <input v-model="newProduct.price" type="number" min="0" step="0.01" class="inv-input w-full text-sm !bg-white" placeholder="Price (₹) *" />
                            <select v-model="newProduct.unit" class="inv-select w-full text-sm !bg-white">
                              <option v-for="u in units" :key="u">{{ u }}</option>
                            </select>
                          </div>
                          <div v-if="addProductError" class="text-xs text-red-600 bg-red-50 rounded-lg px-2 py-1.5">{{ addProductError }}</div>
                          <button type="button" @click="saveNewProduct" :disabled="addingProduct"
                            class="w-full py-2.5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold transition">
                            {{ addingProduct ? 'Creating…' : 'Save as Product' }}
                          </button>
                        </div>
                      </div>
                    </div>
                    <!-- Mobile chips for qty/price/gst -->
                    <div class="item-chips">
                      <div class="chip-qty">
                        <input v-model="it.quantity" type="number" :min="qtyStep(it.unit)" :step="qtyStep(it.unit)" class="w-12 text-center tabular-nums" />
                        <span class="text-gray-300 select-none">×</span>
                        <select v-model="it.unit" class="max-w-[52px]">
                          <option v-for="u in units" :key="u">{{ u }}</option>
                        </select>
                      </div>
                      <div class="chip-price">
                        <span class="shrink-0">₹</span>
                        <input v-model="it.unit_price" type="number" min="0" step="0.01" class="w-20 text-right tabular-nums" placeholder="0.00" />
                      </div>
                      <div class="chip-tax">
                        <select v-model="it.gst_rate">
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
              </div><!-- /mobile accordion -->

              <!-- Mobile: Add Item button -->
              <div class="lg:hidden flex justify-center py-4 border-t border-gray-100 bg-white">
                <button type="button" @click="addItem"
                  class="inline-flex items-center gap-1.5 px-6 py-2.5 rounded-full border-2 border-primary-600 text-primary-600 font-bold text-sm hover:bg-primary-50 transition shadow-sm">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                  Add Item
                </button>
              </div>

              <!-- Mobile: Summary totals -->
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
          </div><!-- /line items card -->

          <!-- Notes / Terms -->
          <div class="inv-card p-5 space-y-3">
            <h2 class="text-sm font-semibold text-gray-700">Notes / Terms</h2>
            <div class="grid sm:grid-cols-2 gap-3">
              <div>
                <label class="inv-label">Message to customer</label>
                <textarea v-model="form.notes" rows="3" class="inv-textarea w-full !bg-white notes-input" placeholder="Thank you for your business"></textarea>
              </div>
              <div>
                <label class="inv-label">Terms &amp; conditions</label>
                <textarea v-model="form.terms" rows="3" class="inv-textarea w-full !bg-white" placeholder="Payment due within 30 days"></textarea>
              </div>
            </div>
          </div>

          <!-- Auto-repeat -->
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

        <!-- ── RIGHT: Sidebar (hidden on mobile) ── -->
        <aside class="inv-sidebar hidden lg:block">

          <!-- Bill To — GPay-style client chip -->
          <div class="inv-card !overflow-visible" data-tour="inv-billto">
            <div class="px-5 py-3.5 border-b border-gray-100">
              <h2 class="text-sm font-semibold text-gray-800">Bill To</h2>
            </div>
            <div class="p-4">
              <!-- Selected client: gradient avatar chip + recent row -->
              <div v-if="form.client_id">
                <div class="client-chip">
                  <div class="client-av" style="background: linear-gradient(135deg, #3b7ded, #1a5fd4);">
                    {{ selectedClient?.name?.charAt(0)?.toUpperCase() }}
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-900 truncate">{{ selectedClient?.name }}</p>
                    <p class="text-xs text-gray-500 truncate">{{ selectedClient?.mobile || selectedClient?.email || '—' }}</p>
                  </div>
                  <button type="button" @click="form.client_id = ''; clientSearch = ''; clientDropdownOpen = true"
                    class="text-xs text-primary-600 font-semibold shrink-0 hover:underline">Change</button>
                </div>
                <!-- Recent clients -->
                <div v-if="clients.length > 1">
                  <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mt-3 mb-1">Recent</p>
                  <div class="recent-row flex-wrap">
                    <div v-for="(c, ci) in clients.slice(0, 5)" :key="c.id"
                      class="recent-av" :title="c.name"
                      :style="`background: linear-gradient(135deg, ${['#3b7ded','#10b981','#8b5cf6','#f59e0b','#ef4444'][ci % 5]}, ${['#1a5fd4','#059669','#7c3aed','#d97706','#dc2626'][ci % 5]})`"
                      @click="form.client_id = c.id">
                      {{ c.name?.charAt(0)?.toUpperCase() }}
                    </div>
                  </div>
                </div>
              </div>

              <!-- No client: autocomplete search -->
              <div v-else class="relative">
                <div class="relative">
                  <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                  <input v-model="clientSearch" type="text" placeholder="Type customer name or mobile…"
                    class="inv-input w-full pl-9 pr-3 client-search-input"
                    @focus="clientDropdownOpen = true"
                    @input="onClientSearchInput" />
                </div>
                <!-- Dropdown results -->
                <div v-if="clientDropdownOpen" class="absolute left-0 right-0 top-full mt-1 z-50 bg-white rounded-xl border border-gray-200 shadow-lg overflow-hidden">
                  <div v-if="filteredClients.length" class="max-h-48 overflow-y-auto divide-y divide-gray-50">
                    <button v-for="c in filteredClients" :key="c.id" type="button"
                      @click="form.client_id = c.id; clientSearch = ''; clientDropdownOpen = false"
                      class="w-full flex items-center gap-3 px-3 py-2.5 hover:bg-gray-50 transition text-left">
                      <div class="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center shrink-0">
                        <span class="text-xs font-bold text-gray-500">{{ c.name?.charAt(0)?.toUpperCase() }}</span>
                      </div>
                      <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate">{{ c.name }}</p>
                        <p class="text-xs text-gray-400 truncate">{{ c.mobile || c.email || '' }}</p>
                      </div>
                    </button>
                  </div>
                  <!-- No match → inline create -->
                  <div v-if="showInlineCreate" class="border-t border-gray-100 p-3 space-y-2.5 bg-gray-50/50">
                    <p class="text-xs font-semibold text-gray-500">No customer found — create new:</p>
                    <div>
                      <input v-model="newClient.name" type="text" class="inv-input w-full text-sm" placeholder="Customer name *" />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                      <input v-model="newClient.mobile" type="tel" class="inv-input w-full text-xs" placeholder="Mobile (optional)" />
                      <input v-model="newClient.email" type="email" class="inv-input w-full text-xs" placeholder="Email (optional)" />
                    </div>
                    <div v-if="addClientError" class="text-xs text-red-600 bg-red-50 rounded-lg px-2 py-1.5">{{ addClientError }}</div>
                    <button type="button" @click="saveNewClient" :disabled="addingClient"
                      class="w-full py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold transition flex items-center justify-center gap-1.5">
                      <svg v-if="addingClient" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                      <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                      {{ addingClient ? 'Creating…' : 'Create & Select' }}
                    </button>
                  </div>
                </div>
                <!-- Click-outside to close -->
                <div v-if="clientDropdownOpen" class="fixed inset-0 z-10" @click="clientDropdownOpen = false"></div>
              </div>
            </div>
          </div>

          <!-- Invoice Information — floating label inputs -->
          <div class="inv-card p-5 space-y-3" data-tour="inv-info">
            <h2 class="text-sm font-semibold text-gray-800">Invoice Information</h2>

            <div class="fi">
              <select v-model="form.invoice_type">
                <option value="tax_invoice">Tax Invoice (with GST)</option>
                <option value="bill_of_supply">Bill of Supply</option>
                <option value="retail">Retail Invoice</option>
                <option value="proforma">Proforma Invoice</option>
              </select>
              <label>Bill Type</label>
            </div>

            <div class="fi">
              <select v-model="form.place_of_supply">
                <option value="">Select state</option>
                <option v-for="s in states" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
              <label>Place of Supply *</label>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div class="fi">
                <input v-model="form.issue_date" type="date" />
                <label>Issued Date</label>
              </div>
              <div class="fi">
                <input v-model="form.due_date" type="date" />
                <label>Due Date</label>
              </div>
            </div>
          </div>

          <!-- Summary + Actions -->
          <div class="inv-card p-5 space-y-3" data-tour="inv-summary">
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

    <!-- Mobile sticky footer -->
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
</template>
