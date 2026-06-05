<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { list, task, item } from '../../api'
import HelpIcon from '../../components/HelpIcon.vue'
import { inr } from '../../utils/currency'
import { today } from '../../utils/date'
import { useToast } from '../../composables/useToast'
import { useFormKeys } from '../../composables/useFormKeys'

const router = useRouter()
const route  = useRoute()
const emit   = defineEmits(['refresh'])
const toast  = useToast()
useFormKeys({ formId: 'credit-note-form', autoFocus: false })

function onLastFieldTab(i, e) {}

const invoices = ref([])
const products = ref([])
const loading  = ref(false)
const saving   = ref(false)
const error    = ref('')

// Inline product autocomplete
const addingProduct    = ref(false)
const addProductError  = ref('')
const productSearchIdx = ref(null)
const productSearch    = ref('')
const newProduct = ref({ type: 'service', name: '', price: '', unit: 'Nos', gst_rate: 18 })

const blankItem = () => ({ description: '', hsn_sac: '', unit: 'Nos', quantity: 1, unit_price: '', gst_rate: 18 })

const form = ref({
  invoice_id: '',
  reason:     'return',
  issue_date: today(),
  notes:      '',
  items:      [blankItem()],
})

// When invoice_id changes, offer to prefill items from that invoice
async function prefillFromInvoice(invoiceId) {
  if (!invoiceId) return
  try {
    const [itmRes] = await Promise.all([
      list('Invoice:items', { invoice_id: invoiceId }),
    ])
    const invItems = itmRes.data?.data || []
    if (invItems.length > 0) {
      form.value.items = invItems.map(it => ({
        description: it.description,
        hsn_sac:     it.hsn_sac || '',
        unit:        it.unit || 'Nos',
        quantity:    parseFloat(it.quantity) || 1,
        unit_price:  parseFloat(it.unit_price) || 0,
        gst_rate:    parseFloat(it.gst_rate) || 0,
      }))
    }
  } catch {}
}

const reasons = [
  { value: 'return',     label: 'Goods Return' },
  { value: 'discount',   label: 'Discount / Price Adjustment' },
  { value: 'correction', label: 'Invoice Correction' },
  { value: 'other',      label: 'Other' },
]

const units    = ['Nos', 'Kg', 'Ltr', 'Hrs', 'Pcs', 'Mtr', 'Box', 'Set']
const gstRates = [0, 5, 12, 18, 28]

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
  const it = form.value.items[i]
  it.description = p.name
  it.unit        = p.unit || 'Nos'
  it.unit_price  = p.price
  it.hsn_sac     = p.hsn_sac || ''
  it.gst_rate    = parseFloat(p.gst_rate || 18)
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

// Keyboard shortcuts
function onFormShortcut(e) {
  if (e.target.closest('[class*="fixed inset-0"]')) return

  if (e.altKey && e.key === 'a') {
    e.preventDefault()
    addItem()
  }
  if (e.altKey && e.key === 'n') {
    e.preventDefault()
    const el = document.querySelector('.notes-input')
    if (el) el.focus()
  }
  if (e.key === 'Escape') {
    closeProductSearch()
  }
}
onMounted(() => document.addEventListener('keydown', onFormShortcut))
onUnmounted(() => document.removeEventListener('keydown', onFormShortcut))

async function load() {
  loading.value = true
  try {
    const [invRes, pRes] = await Promise.all([
      list('Invoice', { sort_by: 'i.created_at', sort_order: 'desc', limit: 500 }),
      list('Product'),
    ])
    invoices.value = (invRes.data?.data || []).filter(i => i.status !== 'cancelled')
    products.value = pRes.data?.data || []
    // Prefill if coming from an invoice detail page
    const fromId = route.query.from_invoice
    if (fromId) {
      form.value.invoice_id = parseInt(fromId)
      await prefillFromInvoice(fromId)
    }
  } catch (err) {
    error.value = 'Failed to load invoices.'
  }
  loading.value = false

  // Auto-focus first item description
  nextTick(() => {
    setTimeout(() => {
      const el = document.querySelector('.line-desc')
      if (el) el.focus()
    }, 150)
  })
}

function addItem() {
  form.value.items.push(blankItem())
  nextTick(() => {
    const descs = document.querySelectorAll('.line-desc')
    const last = descs[descs.length - 1]
    if (last) last.focus()
  })
}
function removeItem(i) { if (form.value.items.length > 1) form.value.items.splice(i, 1) }

function lineTotal(it) {
  const qty   = parseFloat(it.quantity   || 0)
  const price = parseFloat(it.unit_price || 0)
  const gst   = parseFloat(it.gst_rate   || 0)
  return qty * price * (1 + gst / 100)
}

const grandTotal = () => form.value.items.reduce((s, it) => s + lineTotal(it), 0)

async function save() {
  error.value = ''
  if (!form.value.invoice_id) return (error.value = 'Please select an invoice.')
  if (!form.value.items.some(i => i.description)) return (error.value = 'Add at least one item.')

  saving.value = true
  try {
    const res = await task('CreditNote', 'create', form.value)
    emit('refresh')
    toast.success('Credit note created.')
    router.push('/credit-notes')
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to create credit note.'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="inv-shell">

    <!-- Toolbar -->
    <div class="inv-toolbar">
      <div class="flex items-center gap-3 min-w-0">
        <button type="button" @click="router.push('/credit-notes')" class="inv-back-btn">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <h1 class="inv-page-title flex items-center gap-2">New Credit Note <HelpIcon section="returns" /></h1>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" @click="router.push('/credit-notes')" class="inv-btn-secondary hidden sm:inline-flex">Cancel</button>
        <button type="submit" form="credit-note-form" class="inv-btn-primary" :disabled="saving || loading" title="Ctrl+Enter">
          <svg v-if="saving" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
          {{ saving ? 'Creating…' : 'Create Credit Note' }} <kbd v-if="!saving" class="ml-1 opacity-60 text-[10px] font-mono">⌃↵</kbd>
        </button>
      </div>
    </div>

    <!-- Keyboard shortcuts hint (desktop only) -->
    <div class="hidden lg:flex items-center gap-4 px-6 py-1.5 bg-gray-50 border-b border-gray-100 text-[10px] text-gray-400 font-medium">
      <span><kbd class="px-1 py-0.5 bg-white border border-gray-200 rounded text-[9px] font-mono">Alt+A</kbd> Add item</span>
      <span><kbd class="px-1 py-0.5 bg-white border border-gray-200 rounded text-[9px] font-mono">Alt+N</kbd> Notes</span>
      <span><kbd class="px-1 py-0.5 bg-white border border-gray-200 rounded text-[9px] font-mono">Ctrl+↵</kbd> Save</span>
      <span><kbd class="px-1 py-0.5 bg-white border border-gray-200 rounded text-[9px] font-mono">Esc</kbd> Close</span>
    </div>

    <!-- Body: two-column on desktop -->
    <div class="inv-body">
      <form id="credit-note-form" @submit.prevent="save" class="inv-layout">

        <!-- LEFT: Main content -->
        <div class="inv-main">

          <!-- Items card -->
          <div class="inv-card !overflow-visible">
            <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
              <h2 class="text-sm font-semibold text-gray-800">Items to Credit</h2>
              <span class="text-xs text-gray-400">{{ form.items.length }} item{{ form.items.length > 1 ? 's' : '' }}</span>
            </div>

            <div v-if="loading" class="p-10 text-center text-gray-400 text-sm">Loading…</div>

            <template v-else>
              <!-- Desktop table -->
              <div class="hidden lg:block">
                <div class="grid grid-cols-12 gap-4 px-5 py-2.5 text-[10px] font-bold uppercase tracking-wider text-gray-400 bg-gray-50 border-b border-gray-100">
                  <span class="col-span-5">Items</span>
                  <span class="col-span-2 text-center">QTY</span>
                  <span class="col-span-3 text-right">Price / Tax</span>
                  <span class="col-span-2 text-right pr-2">Amount</span>
                </div>
                <div class="divide-y divide-gray-100">
                  <div v-for="(it, i) in form.items" :key="i" class="grid grid-cols-12 gap-4 px-5 py-4 items-start hover:bg-gray-50/20 transition-colors">
                    <!-- Col 1: Description + product autocomplete -->
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
                            <span class="text-gray-400 tabular-nums shrink-0 ml-2">{{ inr(p.price) }}</span>
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
                    <!-- Col 2: QTY -->
                    <div class="col-span-2">
                      <input v-model="it.quantity" type="number" min="0.001" step="0.001" class="inv-input text-center tabular-nums !bg-white" placeholder="1.00" />
                    </div>
                    <!-- Col 3: Rate + Tax/GST -->
                    <div class="col-span-3 space-y-2">
                      <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₹</span>
                        <input v-model="it.unit_price" type="number" min="0" step="0.01" class="inv-input pl-7 text-right tabular-nums !bg-white" placeholder="0.00" />
                      </div>
                      <select v-model="it.gst_rate" class="inv-select text-center text-xs !bg-white" @keydown.tab="onLastFieldTab(i, $event)">
                        <option v-for="r in gstRates" :key="r" :value="r">{{ r }}% GST</option>
                      </select>
                    </div>
                    <!-- Col 4: Total + remove -->
                    <div class="col-span-2 flex flex-col items-end justify-between h-[86px] py-1">
                      <span class="text-sm font-semibold text-gray-800 tabular-nums pr-2">{{ inr(lineTotal(it)) }}</span>
                      <button v-if="form.items.length > 1" type="button" @click="removeItem(i)"
                        class="mr-1 w-7 h-7 flex items-center justify-center text-gray-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition" title="Remove">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Mobile: stacked cards -->
              <div class="lg:hidden divide-y divide-gray-100">
                <div v-for="(it, i) in form.items" :key="i" class="p-4 space-y-3">
                  <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Item {{ i + 1 }}</span>
                    <button v-if="form.items.length > 1" @click="removeItem(i)" class="text-gray-400 hover:text-red-500 p-1 rounded-full transition-colors">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                  </div>
                  <div>
                    <label class="inv-label">Item Name / Description *</label>
                    <input v-model="it.description" type="text" class="inv-input w-full !bg-white text-sm" required placeholder="Type item name or search product…"
                      @focus="openProductSearch(i)" @input="productSearch = it.description; newProduct.name = it.description" />
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
                  <div class="grid grid-cols-2 gap-2">
                    <div><label class="inv-label">Qty</label><input v-model="it.quantity" type="number" class="inv-input w-full" /></div>
                    <div><label class="inv-label">Rate ₹</label><input v-model="it.unit_price" type="number" class="inv-input w-full" /></div>
                    <div><label class="inv-label">GST</label><select v-model="it.gst_rate" class="inv-select w-full"><option v-for="r in gstRates" :key="r" :value="r">{{ r }}%</option></select></div>
                    <div class="flex items-end pb-1"><span class="text-sm font-bold text-gray-900">{{ inr(lineTotal(it)) }}</span></div>
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
            </template>
          </div>

          <!-- Notes -->
          <div class="inv-card p-5 space-y-3">
            <h2 class="text-sm font-semibold text-gray-700">Notes</h2>
            <label class="inv-label">Additional information</label>
            <textarea v-model="form.notes" rows="3" class="inv-textarea w-full notes-input" placeholder="Reason or additional info…"></textarea>
          </div>

        </div><!-- /inv-main -->

        <!-- RIGHT: Sidebar -->
        <aside class="inv-sidebar">

          <!-- Invoice Reference -->
          <div class="inv-card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-800">Invoice Reference</h2>
            <div>
              <label class="inv-label">Against Invoice *</label>
              <select v-model="form.invoice_id" class="inv-select w-full" @change="prefillFromInvoice(form.invoice_id)">
                <option value="">Select Invoice</option>
                <option v-for="inv in invoices" :key="inv.id" :value="inv.id">
                  {{ inv.number }} — {{ inv.client_name }} ({{ inr(inv.total) }})
                </option>
              </select>
            </div>
          </div>

          <!-- Credit Note Details -->
          <div class="inv-card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-800">Credit Note Details</h2>
            <div>
              <label class="inv-label">Reason *</label>
              <select v-model="form.reason" class="inv-select w-full">
                <option v-for="r in reasons" :key="r.value" :value="r.value">{{ r.label }}</option>
              </select>
            </div>
            <div>
              <label class="inv-label">Date *</label>
              <input v-model="form.issue_date" type="date" class="inv-input w-full" />
            </div>
          </div>

          <!-- Summary -->
          <div class="inv-card p-5 space-y-3">
            <div class="space-y-2 text-sm">
              <div class="flex justify-between items-center pt-1">
                <span class="font-semibold text-gray-800">Credit Total</span>
                <span class="text-xl font-bold tabular-nums text-primary-600">{{ inr(grandTotal()) }}</span>
              </div>
            </div>
            <div v-if="error" class="text-xs text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2">{{ error }}</div>
          </div>

        </aside>
      </form>
    </div>

    <!-- Mobile sticky footer -->
    <div class="form-footer-mobile lg:hidden">
      <div class="flex-1 min-w-0">
        <p class="text-[10px] font-bold text-google-muted uppercase">Credit Total</p>
        <p class="text-lg font-bold text-primary-600 tabular-nums">{{ inr(grandTotal()) }}</p>
      </div>
      <button type="button" @click="router.push('/credit-notes')" class="btn-outline">Cancel</button>
      <button type="submit" form="credit-note-form" class="btn-primary" :disabled="saving || loading">
        {{ saving ? '…' : 'Create' }}
      </button>
    </div>
  </div>
</template>
