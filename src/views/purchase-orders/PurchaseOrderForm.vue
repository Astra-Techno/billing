<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { task, item, all } from '../../api'
import { inr } from '../../utils/currency'
import { today, addDays } from '../../utils/date'
import { useToast } from '../../composables/useToast'

const router = useRouter()
const route  = useRoute()
const emit   = defineEmits(['refresh'])

const toast      = useToast()
const suppliers  = ref([])
const products   = ref([])
const loading    = ref(false)
const saved      = ref(false)
const error      = ref('')
const supplierSearch = ref('')

const isEdit = computed(() => !!route.params.id)

const filteredSuppliers = computed(() => {
  const q = supplierSearch.value.trim().toLowerCase()
  if (!q) return suppliers.value
  return suppliers.value.filter(c => c.name?.toLowerCase().includes(q) || c.mobile?.includes(q))
})

const blankItem = () => ({ description: '', hsn_sac: '', unit: 'Nos', quantity: 1, unit_price: '', gst_rate: 18, product_id: null })

const units    = ['Nos', 'Kg', 'Ltr', 'Hrs', 'Pcs', 'Mtr', 'Box', 'Set', 'Pair']
const gstRates = [0, 5, 12, 18, 28]

const form = ref({
  supplier_id:   '',
  order_date:    today(),
  expected_date: addDays(today(), 7),
  notes:         '',
  items:         [blankItem()],
})

const selectedSupplier = computed(() => suppliers.value.find(s => s.id == form.value.supplier_id))

const totals = computed(() => {
  let subtotal = 0, tax = 0
  for (const it of form.value.items) {
    const base = parseFloat(it.quantity || 0) * parseFloat(it.unit_price || 0)
    subtotal += base
    tax += base * parseFloat(it.gst_rate || 0) / 100
  }
  return { subtotal, tax, total: subtotal + tax }
})

function addItem() { form.value.items.push(blankItem()) }
function removeItem(i) { if (form.value.items.length > 1) form.value.items.splice(i, 1) }

function lineTotal(it) {
  const base = parseFloat(it.quantity || 0) * parseFloat(it.unit_price || 0)
  return base * (1 + parseFloat(it.gst_rate || 0) / 100)
}

function pickProduct(i, productId) {
  const p = products.value.find(p => p.id == productId)
  if (!p) return
  const it = form.value.items[i]
  it.description = p.name
  it.unit        = p.unit || 'Nos'
  it.unit_price  = p.price
  it.hsn_sac     = p.hsn_sac || ''
  it.gst_rate    = parseFloat(p.gst_rate || 18)
}

onMounted(async () => {
  const [cRes, pRes] = await Promise.all([all('Client'), all('Product')])
  suppliers.value = cRes.data?.data || []
  products.value  = pRes.data?.data || []

  if (isEdit.value) {
    try {
      const res  = await item('PurchaseOrder', { id: route.params.id })
      const po   = res.data?.data
      if (po) {
        form.value.supplier_id   = po.supplier_id
        form.value.order_date    = po.order_date
        form.value.expected_date = po.expected_date
        form.value.notes         = po.notes || ''
        if (po.items?.length) {
          form.value.items = po.items.map(it => ({
            description: it.description, hsn_sac: it.hsn_sac || '',
            unit: it.unit || 'Nos', quantity: it.quantity,
            unit_price: it.unit_price, gst_rate: parseFloat(it.gst_rate || 0),
            product_id: it.product_id || null,
          }))
        }
      }
    } catch { error.value = 'Could not load order data.' }
  }
})

async function submit() {
  error.value = ''
  if (!form.value.supplier_id) return (error.value = 'Please select a supplier.')
  if (!form.value.items.some(i => i.description)) return (error.value = 'Please add at least one item.')
  loading.value = true
  try {
    if (isEdit.value) {
      await task('PurchaseOrder', 'update', { ...form.value, id: route.params.id })
      emit('refresh')
      toast.success('Purchase order updated.')
      saved.value = true
    } else {
      const res = await task('PurchaseOrder', 'create', form.value)
      emit('refresh')
      toast.success('Purchase order created.')
      const newId = res.data?.data?.id
      router.push(newId ? `/purchase-orders/${newId}` : '/purchase-orders')
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
        <button type="button" @click="router.push('/purchase-orders')" class="inv-back-btn">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <h1 class="inv-page-title">{{ isEdit ? 'Edit Purchase Order' : 'New Purchase Order' }}</h1>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" @click="router.push('/purchase-orders')" class="inv-btn-secondary hidden sm:inline-flex">Cancel</button>
        <button type="submit" form="po-form" class="inv-btn-primary" :disabled="loading">
          {{ loading ? 'Saving…' : saved ? 'Saved ✓' : isEdit ? 'Save Changes' : 'Create PO' }}
        </button>
      </div>
    </div>

    <!-- Body: two-column on desktop -->
    <div class="inv-body">
      <form id="po-form" @submit.prevent="submit" @input="saved = false" class="inv-layout">

        <!-- LEFT: Main content -->
        <div class="inv-main">

          <!-- Items card -->
          <div class="inv-card">
            <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
              <h2 class="text-sm font-semibold text-gray-800">Items</h2>
              <span class="text-xs text-gray-400">{{ form.items.length }} item{{ form.items.length > 1 ? 's' : '' }}</span>
            </div>

            <!-- Desktop table -->
            <div class="hidden lg:block">
              <div class="grid grid-cols-12 gap-4 px-5 py-2.5 text-[10px] font-bold uppercase tracking-wider text-gray-400 bg-gray-50 border-b border-gray-100">
                <span class="col-span-5">Items</span>
                <span class="col-span-2 text-center">QTY / Unit</span>
                <span class="col-span-3 text-right">Price / Tax</span>
                <span class="col-span-2 text-right pr-2">Amount</span>
              </div>
              <div class="divide-y divide-gray-100">
                <div v-for="(it, idx) in form.items" :key="idx" class="grid grid-cols-12 gap-4 px-5 py-4 items-start hover:bg-gray-50/20 transition-colors">
                  <!-- Col 1: Description + product picker -->
                  <div class="col-span-5 space-y-2">
                    <input v-model="it.description" type="text" class="inv-input font-medium !bg-white" placeholder="Item description" required />
                    <select v-model="it.product_id" class="inv-select text-xs text-gray-400 w-full !bg-white" @change="pickProduct(idx, it.product_id)">
                      <option value="">— Type manually or select product —</option>
                      <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                  </div>
                  <!-- Col 2: QTY + Unit -->
                  <div class="col-span-2 space-y-2">
                    <input v-model="it.quantity" type="number" min="0.01" step="0.01" class="inv-input text-center tabular-nums !bg-white" />
                    <select v-model="it.unit" class="inv-select text-center text-xs !bg-white">
                      <option v-for="u in units" :key="u">{{ u }}</option>
                    </select>
                  </div>
                  <!-- Col 3: Price + GST -->
                  <div class="col-span-3 space-y-2">
                    <div class="relative">
                      <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₹</span>
                      <input v-model="it.unit_price" type="number" min="0" step="0.01" class="inv-input pl-7 text-right tabular-nums !bg-white" placeholder="0.00" />
                    </div>
                    <select v-model="it.gst_rate" class="inv-select text-center text-xs !bg-white">
                      <option v-for="r in gstRates" :key="r" :value="r">{{ r }}% GST</option>
                    </select>
                  </div>
                  <!-- Col 4: Amount + remove -->
                  <div class="col-span-2 flex flex-col items-end justify-between h-[86px] py-1">
                    <span class="text-sm font-semibold text-gray-800 tabular-nums pr-2">{{ inr(lineTotal(it)) }}</span>
                    <button v-if="form.items.length > 1" type="button" @click="removeItem(idx)"
                      class="mr-1 w-7 h-7 flex items-center justify-center text-gray-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition" title="Remove">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Mobile: stacked cards -->
            <div class="lg:hidden space-y-0 divide-y divide-gray-100">
              <div v-for="(it, idx) in form.items" :key="idx" class="p-4 space-y-3">
                <div class="flex items-center justify-between">
                  <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Item {{ idx + 1 }}</span>
                  <button v-if="form.items.length > 1" @click="removeItem(idx)" class="text-gray-400 hover:text-red-500 p-1 rounded-full transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                  </button>
                </div>
                <div>
                  <label class="inv-label">Product</label>
                  <select v-model="it.product_id" class="inv-select w-full" @change="pickProduct(idx, it.product_id)">
                    <option value="">Type manually or select…</option>
                    <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                  </select>
                </div>
                <div><label class="inv-label">Description *</label><input v-model="it.description" type="text" class="inv-input w-full" placeholder="Item description" required /></div>
                <div class="grid grid-cols-2 gap-2">
                  <div><label class="inv-label">Qty</label><input v-model="it.quantity" type="number" min="0.01" step="0.01" class="inv-input w-full" /></div>
                  <div><label class="inv-label">Unit</label><select v-model="it.unit" class="inv-select w-full"><option v-for="u in units" :key="u">{{ u }}</option></select></div>
                  <div><label class="inv-label">Rate (₹)</label><input v-model="it.unit_price" type="number" min="0" step="0.01" class="inv-input w-full" placeholder="0.00" /></div>
                  <div><label class="inv-label">GST %</label><select v-model="it.gst_rate" class="inv-select w-full"><option v-for="r in gstRates" :key="r" :value="r">{{ r }}%</option></select></div>
                </div>
                <div class="flex justify-between text-sm font-semibold text-gray-700 pt-1">
                  <span>Line Total</span>
                  <span>{{ inr(lineTotal(it)) }}</span>
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
            <h2 class="text-sm font-semibold text-gray-700">Notes / Terms</h2>
            <textarea v-model="form.notes" rows="3" class="inv-textarea w-full" placeholder="Any notes for the supplier…"></textarea>
          </div>

        </div><!-- /inv-main -->

        <!-- RIGHT: Sidebar -->
        <aside class="inv-sidebar">

          <!-- Supplier -->
          <div class="inv-card">
            <div class="px-5 py-3.5 border-b border-gray-100">
              <h2 class="text-sm font-semibold text-gray-800">Supplier</h2>
              <p class="text-xs text-gray-400 mt-0.5">*Select one supplier</p>
            </div>
            <div class="p-4">
              <div v-if="form.supplier_id" class="flex items-center gap-3 p-3 bg-primary-50 rounded-xl border border-primary-100">
                <div class="w-9 h-9 rounded-full bg-primary-600 flex items-center justify-center shrink-0">
                  <span class="text-white text-sm font-bold">{{ selectedSupplier?.name?.charAt(0)?.toUpperCase() }}</span>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-semibold text-gray-900 truncate">{{ selectedSupplier?.name }}</p>
                  <p class="text-xs text-gray-500 truncate">{{ selectedSupplier?.mobile || selectedSupplier?.email || '—' }}</p>
                </div>
                <button type="button" @click="form.supplier_id = ''; supplierSearch = ''" class="text-xs text-primary-600 font-semibold shrink-0 hover:underline">Change</button>
              </div>
              <div v-else class="space-y-2">
                <div class="relative">
                  <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                  <input v-model="supplierSearch" type="text" placeholder="Search supplier…" class="inv-input w-full pl-9" />
                </div>
                <div v-if="supplierSearch || filteredSuppliers.length > 0" class="max-h-48 overflow-y-auto rounded-lg border border-gray-200 divide-y divide-gray-50 bg-white shadow-sm">
                  <div v-if="!filteredSuppliers.length" class="px-3 py-3 text-center text-sm text-gray-400">No match for "{{ supplierSearch }}"</div>
                  <button v-for="s in filteredSuppliers" :key="s.id" type="button"
                    @click="form.supplier_id = s.id; supplierSearch = ''"
                    class="w-full flex items-center gap-3 px-3 py-2.5 hover:bg-gray-50 transition text-left">
                    <div class="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center shrink-0">
                      <span class="text-xs font-bold text-gray-500">{{ s.name?.charAt(0)?.toUpperCase() }}</span>
                    </div>
                    <div class="min-w-0">
                      <p class="text-sm font-medium text-gray-800 truncate">{{ s.name }}</p>
                      <p class="text-xs text-gray-400 truncate">{{ s.mobile || s.email || 'Supplier' }}</p>
                    </div>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Order Details -->
          <div class="inv-card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-800">Order Details</h2>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="inv-label">Order Date *</label>
                <input v-model="form.order_date" type="date" class="inv-input w-full" />
              </div>
              <div>
                <label class="inv-label">Expected Delivery</label>
                <input v-model="form.expected_date" type="date" class="inv-input w-full" />
              </div>
            </div>
          </div>

          <!-- Summary -->
          <div class="inv-card p-5 space-y-3">
            <div class="space-y-2 text-sm">
              <div class="flex justify-between text-gray-500">
                <span>Subtotal</span>
                <span class="font-medium text-gray-800 tabular-nums">{{ inr(totals.subtotal) }}</span>
              </div>
              <div v-if="totals.tax > 0" class="flex justify-between text-gray-500">
                <span>GST</span>
                <span class="font-medium text-gray-800 tabular-nums">{{ inr(totals.tax) }}</span>
              </div>
              <div v-else class="flex justify-between text-gray-400 text-xs">
                <span>GST</span><span>—</span>
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
    <div class="form-footer-mobile lg:hidden">
      <div class="flex-1 min-w-0">
        <p class="text-[10px] font-bold text-google-muted uppercase">Total</p>
        <p class="text-lg font-bold text-primary-600 tabular-nums">{{ inr(totals.total) }}</p>
      </div>
      <button type="button" @click="router.push('/purchase-orders')" class="btn-outline">Cancel</button>
      <button type="submit" form="po-form" class="btn-primary" :disabled="loading">
        {{ loading ? '…' : saved ? '✓' : isEdit ? 'Update' : 'Create' }}
      </button>
    </div>
  </div>
</template>
