<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { task, item, all } from '../../api'
import { inr } from '../../utils/currency'
import { today } from '../../utils/date'
import { useToast } from '../../composables/useToast'
import { useFormKeys } from '../../composables/useFormKeys'

const router = useRouter()
const route  = useRoute()
const emit   = defineEmits(['refresh'])

const toast = useToast()
useFormKeys({ formId: 'dc-form', autoFocus: false })

// Keyboard shortcuts
function onFormShortcut(e) {
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
const loading      = ref(false)
const saved        = ref(false)
const error        = ref('')
const clientSearch = ref('')

const clientDropdownOpen = ref(false)

const isEdit = computed(() => !!route.params.id)

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

// Quick-add client
const showAddClient  = ref(false)
const addingClient   = ref(false)
const addClientError = ref('')
const newClient      = ref({ name: '', mobile: '', email: '', type: 'individual' })

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
const productSearchIdx = ref(null)
const productSearch    = ref('')
const newProduct = ref({ type: 'service', name: '', price: '', unit: 'Nos', gst_rate: 18 })

const filteredProducts = computed(() => {
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
  addProductError.value = ''
  newProduct.value = { type: 'service', name: form.value.items[i]?.description || '', price: '', unit: 'Nos', gst_rate: 18 }
}

function closeProductSearch() {
  productSearchIdx.value = null
  productSearch.value = ''
}

function selectProduct(i, p) {
  pickProduct(i, p.id)
  form.value.items[i].product_id = p.id
  closeProductSearch()
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

const blankItem = () => ({ description: '', hsn_sac: '', unit: 'Nos', quantity: 1, product_id: null })

const units = ['Nos', 'Kg', 'Ltr', 'Hrs', 'Pcs', 'Mtr', 'Box', 'Set', 'Pair']

const form = ref({
  client_id:    '',
  challan_date: today(),
  vehicle_no:   '',
  driver_name:  '',
  destination:  '',
  notes:        '',
  items:        [blankItem()],
})

const selectedClient = computed(() => clients.value.find(c => c.id == form.value.client_id))

function addItem() {
  form.value.items.push(blankItem())
  // Focus the new row's description input
  nextTick(() => {
    const descs = document.querySelectorAll('.line-desc')
    const last = descs[descs.length - 1]
    if (last) last.focus()
  })
}
function removeItem(i) { if (form.value.items.length > 1) form.value.items.splice(i, 1) }

function pickProduct(i, productId) {
  const p = products.value.find(p => p.id == productId)
  if (!p) return
  const it = form.value.items[i]
  it.description = p.name
  it.unit        = p.unit || 'Nos'
  it.hsn_sac     = p.hsn_sac || ''
}

// No longer auto-add row on Tab — let Tab flow naturally to next fields
function onLastFieldTab(i, e) {
  // Just let default tab behavior proceed
}

onMounted(async () => {
  const [cRes, pRes] = await Promise.all([all('Client'), all('Product')])
  clients.value  = cRes.data?.data || []
  products.value = pRes.data?.data || []

  if (isEdit.value) {
    try {
      const res = await item('DeliveryChallan', { id: route.params.id })
      const dc  = res.data?.data
      if (dc) {
        form.value.client_id    = dc.client_id
        form.value.challan_date = dc.challan_date
        form.value.vehicle_no   = dc.vehicle_no || ''
        form.value.driver_name  = dc.driver_name || ''
        form.value.destination  = dc.destination || ''
        form.value.notes        = dc.notes || ''
        if (dc.items?.length) {
          form.value.items = dc.items.map(it => ({
            description: it.description, hsn_sac: it.hsn_sac || '',
            unit: it.unit || 'Nos', quantity: it.quantity,
            product_id: it.product_id || null,
          }))
        }
      }
    } catch { error.value = 'Could not load challan data.' }
  }

  // Auto-focus first item description
  nextTick(() => {
    setTimeout(() => {
      const el = document.querySelector('.line-desc')
      if (el) el.focus()
    }, 150)
  })
})

async function submit() {
  error.value = ''
  if (!form.value.client_id) return (error.value = 'Please choose a customer. You must select one from the list.')
  if (!form.value.items.some(i => i.description)) return (error.value = 'Please add at least one item.')
  loading.value = true
  try {
    if (isEdit.value) {
      await task('DeliveryChallan', 'update', { ...form.value, id: route.params.id })
      emit('refresh')
      toast.success('Delivery challan updated.')
      saved.value = true
    } else {
      const res = await task('DeliveryChallan', 'create', form.value)
      emit('refresh')
      toast.success('Delivery challan created.')
      const newId = res.data?.data?.id
      router.push(newId ? `/delivery-challans/${newId}` : '/delivery-challans')
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to save. Please try again.'
  }
  loading.value = false
}
</script>

<template>
  <div class="inv-shell">

    <!-- Toolbar -->
    <div class="inv-toolbar">
      <div class="flex items-center gap-3 min-w-0">
        <button type="button" @click="router.back()" class="inv-back-btn">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <h1 class="inv-page-title">{{ isEdit ? 'Edit Delivery Challan' : 'New Delivery Challan' }}</h1>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" @click="router.back()" class="inv-btn-secondary hidden sm:inline-flex">Cancel</button>
        <button type="submit" form="dc-form" class="inv-btn-primary" :disabled="loading" title="Ctrl+Enter">
          {{ loading ? 'Saving…' : saved ? 'Saved ✓' : isEdit ? 'Save Changes' : 'Create Challan' }} <kbd v-if="!loading" class="ml-1 opacity-60 text-[10px] font-mono">⌃↵</kbd>
        </button>
      </div>
    </div>

    <!-- Keyboard shortcuts hint (desktop only) -->
    <div class="hidden lg:flex items-center gap-4 px-6 py-1.5 bg-gray-50 border-b border-gray-100 text-[10px] text-gray-400 font-medium">
      <span><kbd class="px-1 py-0.5 bg-white border border-gray-200 rounded text-[9px] font-mono">Alt+A</kbd> Add item</span>
      <span><kbd class="px-1 py-0.5 bg-white border border-gray-200 rounded text-[9px] font-mono">Alt+C</kbd> Customer</span>
      <span><kbd class="px-1 py-0.5 bg-white border border-gray-200 rounded text-[9px] font-mono">Alt+N</kbd> Notes</span>
      <span><kbd class="px-1 py-0.5 bg-white border border-gray-200 rounded text-[9px] font-mono">Ctrl+↵</kbd> Save</span>
      <span><kbd class="px-1 py-0.5 bg-white border border-gray-200 rounded text-[9px] font-mono">Esc</kbd> Close</span>
    </div>

    <!-- Body: two-column on desktop -->
    <div class="inv-body">
      <form id="dc-form" @submit.prevent="submit" @input="saved = false" class="inv-layout">

        <!-- LEFT: Main content -->
        <div class="inv-main">

          <!-- Items card -->
          <div class="inv-card !overflow-visible">
            <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
              <h2 class="text-sm font-semibold text-gray-800">Dispatched Items</h2>
              <span class="text-xs text-gray-400">{{ form.items.length }} item{{ form.items.length > 1 ? 's' : '' }}</span>
            </div>

            <!-- Desktop table -->
            <div class="hidden lg:block">
              <div class="grid grid-cols-12 gap-4 px-5 py-2.5 text-[10px] font-bold uppercase tracking-wider text-gray-400 bg-gray-50 border-b border-gray-100">
                <span class="col-span-5">Items</span>
                <span class="col-span-2 text-center">QTY / Unit</span>
                <span class="col-span-3 text-center">HSN/SAC</span>
                <span class="col-span-2 text-right pr-2">Action</span>
              </div>
              <div class="divide-y divide-gray-100">
                <div v-for="(it, i) in form.items" :key="i" class="grid grid-cols-12 gap-4 px-5 py-4 items-start hover:bg-gray-50/20 transition-colors">
                  <!-- Col 1: Combined description + product autocomplete -->
                  <div class="col-span-5 relative">
                    <input v-model="it.description" type="text" class="inv-input font-medium !bg-white line-desc" placeholder="Type item name or search product…" required
                      @focus="openProductSearch(i)" @input="productSearch = it.description; newProduct.name = it.description" />
                    <!-- Product autocomplete dropdown -->
                    <div v-if="productSearchIdx === i && it.description?.trim().length >= 1" class="absolute left-0 right-0 top-full mt-1 z-50 bg-white rounded-xl border border-gray-200 shadow-lg overflow-hidden">
                      <div v-if="filteredProducts.length" class="max-h-36 overflow-y-auto divide-y divide-gray-50">
                        <button v-for="p in filteredProducts" :key="p.id" type="button"
                          @click="selectProduct(i, p)"
                          class="w-full flex items-center justify-between px-3 py-2 hover:bg-gray-50 transition text-left text-xs">
                          <span class="font-medium text-gray-800 truncate">{{ p.name }}</span>
                          <span class="text-gray-400 tabular-nums shrink-0 ml-2">{{ p.unit || 'Nos' }}</span>
                        </button>
                      </div>
                      <!-- No match → inline create -->
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
                  <!-- Col 2: QTY + Unit -->
                  <div class="col-span-2 space-y-2">
                    <input v-model="it.quantity" type="number" min="0.001" step="0.001" class="inv-input text-center tabular-nums !bg-white" />
                    <select v-model="it.unit" class="inv-select text-center text-xs !bg-white">
                      <option v-for="u in units" :key="u">{{ u }}</option>
                    </select>
                  </div>
                  <!-- Col 3: HSN/SAC -->
                  <div class="col-span-3">
                    <input v-model="it.hsn_sac" type="text" class="inv-input text-center !bg-white" placeholder="Optional HSN/SAC" @keydown.tab="onLastFieldTab(i, $event)" />
                  </div>
                  <!-- Col 4: Remove -->
                  <div class="col-span-2 flex justify-end pt-1 pr-2">
                    <button v-if="form.items.length > 1" type="button" @click="removeItem(i)"
                      class="w-7 h-7 flex items-center justify-center text-gray-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition" title="Remove">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Mobile: stacked cards -->
            <div class="lg:hidden divide-y divide-gray-100">
              <div v-for="(it, i) in form.items" :key="i" class="p-4 space-y-3">
                <div>
                  <label class="inv-label">Item Name / Description *</label>
                  <input v-model="it.description" type="text" class="inv-input w-full !bg-white text-sm line-desc" required placeholder="Type item name or search product…"
                    @focus="openProductSearch(i)" @input="productSearch = it.description; newProduct.name = it.description" />
                  <!-- Mobile product autocomplete -->
                  <div v-if="productSearchIdx === i && it.description?.trim().length >= 1" class="mt-1.5 space-y-1.5">
                    <div v-if="filteredProducts.length" class="max-h-36 overflow-y-auto rounded-lg border border-gray-200 divide-y divide-gray-50 bg-white">
                      <button v-for="p in filteredProducts" :key="p.id" type="button"
                        @click="selectProduct(i, p)"
                        class="w-full flex items-center justify-between px-3 py-2.5 hover:bg-gray-50 text-left text-sm">
                        <span class="font-medium text-gray-800 truncate">{{ p.name }}</span>
                        <span class="text-gray-400 text-xs tabular-nums shrink-0 ml-2">{{ p.unit || 'Nos' }}</span>
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
                <div class="grid grid-cols-2 gap-2">
                  <div><label class="inv-label">Quantity</label><input v-model="it.quantity" type="number" min="0.001" step="0.001" class="inv-input w-full" /></div>
                  <div><label class="inv-label">Unit</label><select v-model="it.unit" class="inv-select w-full"><option v-for="u in units" :key="u">{{ u }}</option></select></div>
                  <div class="col-span-2"><label class="inv-label">HSN/SAC</label><input v-model="it.hsn_sac" type="text" class="inv-input w-full" placeholder="Optional" /></div>
                </div>
                <div class="flex justify-end">
                  <button v-if="form.items.length > 1" type="button" @click="removeItem(i)" class="text-xs text-red-500 font-medium">Remove Item</button>
                </div>
              </div>
            </div>

            <!-- Add item -->
            <div class="px-5 py-3 border-t border-gray-100">
              <button type="button" @click="addItem"
                class="flex items-center gap-2 text-sm text-primary-600 font-medium hover:text-primary-700 transition">
                <span class="w-5 h-5 rounded-full border-2 border-primary-300 flex items-center justify-center text-primary-500 text-xs leading-none">+</span>
                Add item
              </button>
            </div>
          </div>

          <!-- Notes -->
          <div class="inv-card p-5 space-y-3">
            <h2 class="text-sm font-semibold text-gray-700">Notes / Instructions</h2>
            <textarea v-model="form.notes" rows="3" class="inv-textarea w-full notes-input" placeholder="Any delivery instructions…"></textarea>
          </div>

        </div><!-- /inv-main -->

        <!-- RIGHT: Sidebar -->
        <aside class="inv-sidebar">

          <!-- Delivery To — Inline autocomplete -->
          <div class="inv-card !overflow-visible">
            <div class="px-5 py-3.5 border-b border-gray-100">
              <h2 class="text-sm font-semibold text-gray-800">Delivery To</h2>
            </div>
            <div class="p-4">
              <!-- Selected client chip -->
              <div v-if="form.client_id" class="flex items-center gap-3 p-3 bg-primary-50 rounded-xl border border-primary-100">
                <div class="w-9 h-9 rounded-full bg-primary-600 flex items-center justify-center shrink-0">
                  <span class="text-white text-sm font-bold">{{ selectedClient?.name?.charAt(0)?.toUpperCase() }}</span>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-semibold text-gray-900 truncate">{{ selectedClient?.name }}</p>
                  <p class="text-xs text-gray-500 truncate">{{ selectedClient?.mobile || selectedClient?.email || '—' }}</p>
                </div>
                <button type="button" @click="form.client_id = ''; clientSearch = ''; clientDropdownOpen = true" class="text-xs text-primary-600 font-semibold shrink-0 hover:underline">Change</button>
              </div>

              <!-- Autocomplete search -->
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
                  <!-- Matching clients -->
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

                  <!-- No match → inline create form -->
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

          <!-- Delivery Details -->
          <div class="inv-card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-800">Delivery Details</h2>
            <div>
              <label class="inv-label">Challan Date *</label>
              <input v-model="form.challan_date" type="date" class="inv-input w-full" />
            </div>
            <div>
              <label class="inv-label">Destination</label>
              <input v-model="form.destination" type="text" class="inv-input w-full" placeholder="Delivery address / city" />
            </div>
          </div>

          <!-- Transport Details -->
          <div class="inv-card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-800">Transport Details</h2>
            <div>
              <label class="inv-label">Vehicle Number</label>
              <input v-model="form.vehicle_no" type="text" class="inv-input w-full" placeholder="e.g. TN 01 AB 1234" />
            </div>
            <div>
              <label class="inv-label">Driver Name</label>
              <input v-model="form.driver_name" type="text" class="inv-input w-full" placeholder="Driver's name" />
            </div>
          </div>

          <!-- Error -->
          <div v-if="error" class="inv-card p-4">
            <p class="text-xs text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2">{{ error }}</p>
          </div>

        </aside>
      </form>
    </div>

    <!-- Mobile sticky footer -->
    <div class="form-footer-mobile lg:hidden">
      <button type="button" @click="router.back()" class="btn-outline">Cancel</button>
      <button type="submit" form="dc-form" class="btn-primary flex-1" :disabled="loading">
        {{ loading ? 'Saving…' : saved ? 'Saved ✓' : isEdit ? 'Update DC' : 'Create DC' }}
      </button>
    </div>
  </div>
</template>
