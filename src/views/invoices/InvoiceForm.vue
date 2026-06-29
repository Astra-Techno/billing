<script setup>
import { ref, shallowRef, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
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

// Keyboard shortcuts — full bill creation without mouse on desktop
function onFormShortcut(e) {
  if (e.target.closest('[class*="fixed inset-0"]')) return

  if ((e.ctrlKey || e.metaKey) && e.key === 's') {
    e.preventDefault()
    document.getElementById('invoice-form')?.requestSubmit()
    return
  }
  if (e.altKey && e.key === 'a') {
    e.preventDefault()
    addItem()
  }
  if (e.altKey && e.key === 'c') {
    e.preventDefault()
    form.value.client_id = ''
    clientDropdownOpen.value = true
    clientHighlight.value = -1
    nextTick(() => document.querySelector('.client-search-input')?.focus())
  }
  if (e.altKey && e.key === 'i') {
    e.preventDefault()
    focusItemDescription(form.value.items.length - 1)
  }
  if (e.altKey && e.key === 'n') {
    e.preventDefault()
    document.querySelector('.notes-input')?.focus()
  }
  if (e.key === 'Escape') {
    closeProductSearch()
    clientDropdownOpen.value = false
    editingClient.value = false
  }
}
onMounted(() => document.addEventListener('keydown', onFormShortcut))
onUnmounted(() => {
  document.removeEventListener('keydown', onFormShortcut)
  clearTimeout(productDebounceTimer)
})

// shallowRef: large display-only arrays — no need for deep reactivity on each item's properties
const clients      = shallowRef([])
const products     = shallowRef([])
const taxRates     = shallowRef([])
const states       = shallowRef([])
const loading      = ref(false)
const dataLoading  = ref(true)
const clientSearch = ref('')

const activeItemIndex = ref(0)
const businessName    = ref('')
const invoiceNumber   = ref('')

const editingInvoiceDetails = ref(false)
const editingClient         = ref(false)

const filteredClients = computed(() => {
  if (!clientDropdownOpen.value && !editingClient.value) return []
  const q = clientSearch.value.trim().toLowerCase()
  if (!q) return clients.value.slice(0, 8)
  return clients.value.filter(c =>
    c.name?.toLowerCase().includes(q) ||
    c.mobile?.includes(q) ||
    c.company?.toLowerCase().includes(q)
  ).slice(0, 8)
})
const clientHighlight = ref(-1)
const showInlineCreate = computed(() => clientSearch.value.trim().length >= 2 && filteredClients.value.length === 0)
const clientDropdownOpen = ref(false)
const error    = ref('')

// Quick-add client modal
const showAddClient = ref(false)
const addingClient = ref(false)
const addClientError = ref('')
const newClient = ref({ name: '', mobile: '', email: '', type: 'individual' })

function selectClient(c) {
  form.value.client_id = c.id
  clientSearch.value = ''
  clientDropdownOpen.value = false
  editingClient.value = false
  clientHighlight.value = -1
  nextTick(() => focusItemDescription(form.value.items.length - 1))
}

function onClientKeydown(e) {
  const list = filteredClients.value
  if (e.key === 'ArrowDown' && list.length) {
    e.preventDefault()
    clientHighlight.value = (clientHighlight.value + 1) % list.length
  } else if (e.key === 'ArrowUp' && list.length) {
    e.preventDefault()
    clientHighlight.value = clientHighlight.value <= 0 ? list.length - 1 : clientHighlight.value - 1
  } else if (e.key === 'Enter') {
    e.preventDefault()
    if (clientHighlight.value >= 0 && list[clientHighlight.value]) {
      selectClient(list[clientHighlight.value])
    } else if (list.length === 1) {
      selectClient(list[0])
    }
  }
}

async function saveNewClient() {
  addClientError.value = ''
  if (!newClient.value.name) return addClientError.value = 'Customer name is required.'
  addingClient.value = true
  try {
    const res = await task('Client', 'create', { ...newClient.value, type: 'individual' })
    const created = res.data?.data
    clients.value = [...clients.value, created].sort((a, b) => (a.name || '').localeCompare(b.name || ''))
    form.value.client_id = created.id
    showAddClient.value = false
    clientSearch.value = ''
    clientDropdownOpen.value = false
    editingClient.value = false
    newClient.value = { name: '', mobile: '', email: '', type: 'individual' }
    nextTick(() => focusItemDescription(0))
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
  clientHighlight.value = -1
  newClient.value.name = clientSearch.value
  newClient.value.mobile = ''
  newClient.value.email = ''
  addClientError.value = ''
}

// Inline product autocomplete
const addingProduct      = ref(false)
const addProductError    = ref('')
const productSearchIdx   = ref(null)
const productSearchQuery = ref('')
const productSearchDebounced = ref('')
let productDebounceTimer = null
const newProduct = ref({ type: 'service', name: '', price: '', unit: 'Nos', gst_rate: 18 })

const filteredProducts = computed(() => {
  if (productSearchIdx.value === null) return []
  const q = productSearchDebounced.value.trim().toLowerCase()
  if (!q) return products.value.slice(0, 6)
  return products.value.filter(p => p.name?.toLowerCase().includes(q)).slice(0, 6)
})
const showProductInlineCreate = computed(() =>
  productSearchQuery.value.trim().length >= 2 && filteredProducts.value.length === 0
)

function openProductSearch(i) {
  productSearchIdx.value = i
  const desc = form.value.items[i]?.description || ''
  productSearchQuery.value = desc
  productSearchDebounced.value = desc
  productHighlight.value = -1
  addProductError.value = ''
  newProduct.value = { type: 'service', name: desc, price: '', unit: 'Nos', gst_rate: 18 }
}

function onDescInput(i, it) {
  productSearchQuery.value = it.description
  productSearchDebounced.value = it.description
  newProduct.value.name = it.description
  productHighlight.value = -1
  clearTimeout(productDebounceTimer)
  productDebounceTimer = setTimeout(() => {
    productSearchDebounced.value = it.description
  }, 100)
}

const productHighlight = ref(-1)

function closeProductSearch() {
  productSearchIdx.value = null
  productSearchQuery.value = ''
  productHighlight.value = -1
}

function selectProduct(i, p) {
  pickProduct(i, p.id)
  form.value.items[i].product_id = p.id
  closeProductSearch()
  ensureTrailingEmptyRow({ focus: false })
  if (window.innerWidth < 1024) {
    // Mobile: expand the new empty row so the user can immediately type the next product
    activeItemIndex.value = form.value.items.length - 1
    nextTick(() => focusItemDescription(form.value.items.length - 1))
  } else {
    focusItemField(i, 'qty')
  }
}

function onProductKeydown(i, e) {
  const list = filteredProducts.value

  if (e.key === 'ArrowDown' && list.length) {
    e.preventDefault()
    productHighlight.value = (productHighlight.value + 1) % list.length
    scrollProductIntoView()
  } else if (e.key === 'ArrowUp' && list.length) {
    e.preventDefault()
    productHighlight.value = productHighlight.value <= 0 ? list.length - 1 : productHighlight.value - 1
    scrollProductIntoView()
  } else if (e.key === 'Enter') {
    e.preventDefault()
    if (productHighlight.value >= 0 && productHighlight.value < list.length) {
      selectProduct(i, list[productHighlight.value])
    } else if (list.length === 1) {
      selectProduct(i, list[0])
    } else {
      closeProductSearch()
      ensureTrailingEmptyRow({ focus: true })
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
    products.value = [...products.value, created].sort((a, b) => a.name.localeCompare(b.name))
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

let itemKeySeq = 0
const blankItem = () => ({
  _key: ++itemKeySeq,
  description: '', hsn_sac: '', unit: 'Nos', quantity: 1, unit_price: '', discount_pct: 0, gst_rate: 18, product_id: null,
})

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
// Only track the 4 numeric fields that affect the total — typing in description,
// hsn_sac, unit etc. no longer causes totals to re-calculate or the summary to re-render.
const totals = computed(() => calcInvoice(
  form.value.items.map(it => ({
    quantity:     it.quantity,
    unit_price:   it.unit_price,
    discount_pct: it.discount_pct,
    gst_rate:     it.gst_rate,
  }))
))

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
      _key: ++itemKeySeq,
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
  try {
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
            _key: ++itemKeySeq,
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

  focusInitialField()
  } finally {
    dataLoading.value = false
  }
})

function focusInitialField() {
  nextTick(() => {
    setTimeout(() => {
      if (!form.value.client_id && window.innerWidth >= 1024) {
        document.querySelector('.client-search-input')?.focus()
      } else {
        focusItemDescription(0)
      }
    }, 80)
  })
}

watch(() => form.value.items.length, (n) => {
  if (window.innerWidth < 1024) activeItemIndex.value = n - 1
})

watch(() => form.value.invoice_type, (type) => {
  if (type === 'bill_of_supply') {
    form.value.items.forEach(it => { it.gst_rate = 0 })
  }
})

function addItem() {
  form.value.items.push(blankItem())
  focusItemDescription(form.value.items.length - 1)
}

function isRowEmpty(it) {
  return !it.description?.trim() && (it.unit_price === '' || it.unit_price == null)
}

/** Keep one blank row after the last filled line item (spreadsheet-style entry). */
function ensureTrailingEmptyRow({ focus = false } = {}) {
  const items = form.value.items
  const last = items[items.length - 1]
  if (!isRowEmpty(last)) {
    items.push(blankItem())
    if (focus) focusItemDescription(items.length - 1)
  } else if (focus) {
    focusItemDescription(items.length - 1)
  }
}

function focusItemField(index, field) {
  activeItemIndex.value = index
  nextTick(() => {
    const sel = {
      desc:  `[data-line-desc="${index}"]`,
      qty:   `[data-line-qty="${index}"]`,
      price: `[data-line-price="${index}"]`,
    }
    document.querySelector(sel[field])?.focus()
  })
}

function focusItemDescription(index) {
  focusItemField(index, 'desc')
}

/** Blur handler — defer so dropdown product clicks register before we add a row. */
function onItemDescriptionBlur(i) {
  setTimeout(() => {
    if (productSearchIdx.value === i) return
    if (i !== form.value.items.length - 1) return
    if (!form.value.items[i].description?.trim()) return
    ensureTrailingEmptyRow()
  }, 120)
}

function onItemPriceBlur(i) {
  if (i !== form.value.items.length - 1) return
  const it = form.value.items[i]
  if (!it.description?.trim() || it.unit_price === '' || it.unit_price == null) return
  ensureTrailingEmptyRow()
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

// Tab from GST on last row → add next line and focus its description
function onLastFieldTab(i, e) {
  handleLineItemTab(i, e, form.value.items, () => ensureTrailingEmptyRow({ focus: true }), '[data-line-desc]')
}

function onPriceKeydown(i, e) {
  if (e.key === 'Enter') {
    e.preventDefault()
    if (i === form.value.items.length - 1) ensureTrailingEmptyRow({ focus: true })
    else focusItemField(i + 1, 'desc')
  }
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
        </h1>
      </div>
      <div class="flex items-center gap-2">
        <!-- Shortcut hints — desktop only, inline in toolbar right -->
        <div class="hidden lg:flex items-center gap-3 mr-2 text-[10px] text-gray-400 font-medium">
          <span><kbd class="px-1 py-0.5 bg-gray-100 border border-gray-200 rounded text-[9px] font-mono">Alt+C</kbd> Client</span>
          <span><kbd class="px-1 py-0.5 bg-gray-100 border border-gray-200 rounded text-[9px] font-mono">Alt+I</kbd> Items</span>
          <span><kbd class="px-1 py-0.5 bg-gray-100 border border-gray-200 rounded text-[9px] font-mono">↑↓↵</kbd> Pick</span>
          <span><kbd class="px-1 py-0.5 bg-gray-100 border border-gray-200 rounded text-[9px] font-mono">Ctrl+S</kbd> Save</span>
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
      <div v-if="dataLoading" class="max-w-7xl mx-auto w-full p-8 flex items-center justify-center min-h-[40vh]">
        <div class="text-center">
          <div class="w-8 h-8 border-2 border-primary-200 border-t-primary-600 rounded-full animate-spin mx-auto mb-3"></div>
          <p class="text-sm text-gray-500">Loading bill form…</p>
        </div>
      </div>
      <form v-else id="invoice-form" @submit.prevent="submit" @keydown.enter="onFormEnter" class="inv-layout">

        <!-- ── LEFT: Main content ── -->
        <div class="inv-main">

          <!-- Line Items card (first on mobile for one-hand entry) -->
          <div>
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5 px-1 lg:hidden">Add Items</p>
            <div class="inv-card !overflow-visible" data-tour="inv-items">
              <div class="px-5 py-3.5 border-b border-gray-100 items-center justify-between hidden lg:flex">
                <h2 class="text-sm font-semibold text-gray-800">Items</h2>
                <span class="text-xs text-gray-400">{{ form.items.length }} item{{ form.items.length > 1 ? 's' : '' }}</span>
              </div>

              <!-- Desktop: chip-based item rows -->
              <div class="hidden lg:block">
                <div>
                  <div v-for="(it, i) in form.items" :key="it._key" class="item-row group relative">
                    <!-- Item number badge -->
                    <div class="item-num mt-1">{{ i + 1 }}</div>

                    <!-- Left: description + chips -->
                    <div class="flex-1 min-w-0">
                      <!-- Description input + product autocomplete -->
                      <div class="relative">
                        <input v-model="it.description" type="text" :data-line-desc="i" class="inv-input font-medium !bg-white line-desc w-full" placeholder="Type item name or search product…"
                          @focus="openProductSearch(i)" @input="onDescInput(i, it)"
                          @blur="onItemDescriptionBlur(i)"
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
                      </div>

                      <!-- Chips row -->
                      <div class="item-chips">
                        <!-- Qty + Unit chip -->
                        <div class="chip-qty">
                          <input v-model="it.quantity" type="number" :data-line-qty="i" :min="qtyStep(it.unit)" :step="qtyStep(it.unit)"
                            class="w-12 text-center tabular-nums" />
                          <span class="text-gray-300 select-none">×</span>
                          <select v-model="it.unit" class="max-w-[52px]">
                            <option v-for="u in units" :key="u">{{ u }}</option>
                          </select>
                        </div>
                        <!-- Price chip -->
                        <div class="chip-price">
                          <span class="shrink-0">₹</span>
                          <input v-model="it.unit_price" type="number" :data-line-price="i" min="0" step="0.01"
                            class="w-20 text-right tabular-nums" placeholder="0.00"
                            @blur="onItemPriceBlur(i)"
                            @keydown="onPriceKeydown(i, $event)" />
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

              <!-- Mobile: compact list — tap row to edit, active row always expanded -->
              <div class="lg:hidden divide-y divide-gray-100">
                <div v-for="(it, i) in form.items" :key="it._key">
                  <button v-if="activeItemIndex !== i" type="button" @click="activeItemIndex = i"
                    class="w-full p-4 text-left hover:bg-gray-50/50 flex items-center justify-between gap-3">
                    <div class="min-w-0 flex-1">
                      <p class="font-semibold text-gray-800 text-sm truncate">{{ it.description || `Item ${i + 1}` }}</p>
                      <p class="text-xs text-gray-400 mt-0.5">{{ it.quantity }} × {{ inr(it.unit_price || 0) }} · {{ it.gst_rate }}% GST</p>
                    </div>
                    <span class="text-sm font-bold text-gray-900 tabular-nums shrink-0">{{ inr(lineTotal(it)) }}</span>
                  </button>

                  <div v-else class="px-4 pb-5 pt-4 space-y-3 bg-gray-50/70 border-t border-gray-100">
                    <div>
                      <label class="inv-label">Item Name / Description *</label>
                      <input v-model="it.description" type="text" :data-line-desc="i" class="inv-input w-full !bg-white text-sm line-desc" placeholder="Item name or search product…"
                        @focus="openProductSearch(i)" @input="onDescInput(i, it)"
                        @blur="onItemDescriptionBlur(i)"
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
                        <input v-model="it.unit_price" type="number" :data-line-price="i" min="0" step="0.01" class="w-20 text-right tabular-nums" placeholder="0.00"
                          @blur="onItemPriceBlur(i)"
                          @keydown="onPriceKeydown(i, $event)" />
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

          <!-- Mobile: customer & dates (collapsed by default — items first) -->
          <details class="lg:hidden inv-card group">
            <summary class="px-4 py-3.5 text-sm font-semibold text-gray-700 cursor-pointer list-none flex items-center justify-between">
              <span>Customer &amp; bill details</span>
              <span class="text-xs font-normal text-gray-400 truncate max-w-[50%]">{{ selectedClient?.name || 'Walk-in / optional' }}</span>
            </summary>
            <div class="px-4 pb-4 space-y-3 border-t border-gray-100">
              <div class="relative">
                <input v-model="clientSearch" type="text" placeholder="Customer name or mobile…" class="inv-input w-full client-search-input !bg-white"
                  @focus="editingClient = true; clientDropdownOpen = true" @input="onClientSearchInput" @keydown="onClientKeydown" />
                <div v-if="filteredClients.length && clientSearch.trim()" class="mt-1 max-h-36 overflow-y-auto rounded-lg border border-gray-200 divide-y bg-white">
                  <button v-for="(c, ci) in filteredClients" :key="c.id" type="button" @click="selectClient(c)"
                    :class="['w-full px-3 py-2.5 text-left text-sm', ci === clientHighlight ? 'bg-primary-50' : 'hover:bg-gray-50']">
                    {{ c.name }} <span class="text-gray-400 text-xs">{{ c.mobile }}</span>
                  </button>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-2">
                <input v-model="form.issue_date" type="date" class="inv-input text-sm !bg-white" />
                <input v-model="form.due_date" type="date" class="inv-input text-sm !bg-white" />
              </div>
            </div>
          </details>

          <!-- Notes / Terms (desktop; mobile users save from footer) -->
          <div class="inv-card p-5 space-y-3 hidden lg:block">
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

          <!-- Auto-repeat (desktop only) -->
          <div class="inv-card p-5 hidden lg:block">
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
                      @click="form.client_id = c.id; clientSearch = ''">
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
                    @focus="clientDropdownOpen = true; clientHighlight = -1"
                    @input="onClientSearchInput"
                    @keydown="onClientKeydown" />
                </div>
                <!-- Dropdown results -->
                <div v-if="clientDropdownOpen" class="absolute left-0 right-0 top-full mt-1 z-50 bg-white rounded-xl border border-gray-200 shadow-lg overflow-hidden">
                  <div v-if="filteredClients.length" class="max-h-48 overflow-y-auto divide-y divide-gray-50">
                    <button v-for="(c, ci) in filteredClients" :key="c.id" type="button"
                      @click="selectClient(c)"
                      :class="['w-full flex items-center gap-3 px-3 py-2.5 transition text-left', ci === clientHighlight ? 'bg-primary-50' : 'hover:bg-gray-50']">
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

    <!-- Shared overlay for product dropdown (one layer, not per row) -->
    <div v-if="productSearchIdx !== null" class="fixed inset-0 z-40" @click="closeProductSearch"></div>

    <!-- Mobile sticky footer — thumb zone -->
    <div v-if="!dataLoading" class="form-footer-mobile lg:hidden bg-white/95 backdrop-blur-md border-t border-gray-100 px-4 py-3 flex items-center gap-3 safe-area-pb">
      <div class="shrink-0 min-w-[88px]">
        <p class="text-[10px] text-gray-400 font-medium uppercase">Total</p>
        <p class="text-lg font-extrabold text-primary-600 tabular-nums leading-tight">{{ inr(totals.total) }}</p>
      </div>
      <button type="submit" form="invoice-form" :disabled="loading"
        class="flex-1 py-4 px-4 rounded-2xl font-bold text-base text-white flex items-center justify-center gap-2 transition disabled:opacity-60 min-h-[52px]"
        style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);">
        {{ loading ? 'Saving…' : isEdit ? 'Save Bill' : 'Save Bill' }}
      </button>
    </div>
  </div>
</template>
