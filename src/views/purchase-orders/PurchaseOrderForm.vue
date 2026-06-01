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
  <div class="gpay-screen px-4 py-4 max-w-2xl lg:mx-auto space-y-5 pb-10" @input="saved = false">

    <!-- Header -->
    <div class="flex items-center gap-3 pt-2">
      <button @click="router.push('/purchase-orders')" class="p-2 -ml-2 rounded-full hover:bg-gray-100 transition-colors">
        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
      </button>
      <h1 class="page-title">{{ isEdit ? 'Edit Purchase Order' : 'New Purchase Order' }}</h1>
    </div>

    <div v-if="error" class="text-sm text-danger-600 bg-danger-50 rounded-xl px-4 py-3">{{ error }}</div>

    <!-- Supplier + Dates -->
    <div class="card card-body space-y-4 animate-fade-in-up">
      <h2 class="section-title">Supplier & Dates</h2>

      <div>
        <label class="form-label">Supplier *</label>
        <input v-model="supplierSearch" type="text" class="form-input mb-2" placeholder="Search supplier…" />
        <select v-model="form.supplier_id" class="form-select">
          <option value="">Select Supplier</option>
          <option v-for="s in filteredSuppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
        </select>
      </div>

      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="form-label">Order Date *</label>
          <input v-model="form.order_date" type="date" class="form-input" />
        </div>
        <div>
          <label class="form-label">Expected Delivery</label>
          <input v-model="form.expected_date" type="date" class="form-input" />
        </div>
      </div>
    </div>

    <!-- Items -->
    <div class="card card-body space-y-4 animate-fade-in-up anim-delay-75">
      <div class="flex items-center justify-between">
        <h2 class="section-title mb-0">Items</h2>
        <button @click="addItem" class="text-xs btn bg-primary-50 text-primary-700 hover:bg-primary-100 rounded-full px-3 py-1.5 font-bold">+ Add Row</button>
      </div>      <!-- Desktop items table (Redesigned Grid) -->
      <div class="hidden lg:block">
        <!-- Table Header -->
        <div class="grid grid-cols-12 gap-4 px-5 py-2.5 text-[10px] font-bold uppercase tracking-wider text-gray-400 bg-gray-50 border-b border-gray-100 rounded-t-2xl">
          <span class="col-span-5">Items</span>
          <span class="col-span-2 text-center">QTY / Unit</span>
          <span class="col-span-3 text-right">Price / Tax</span>
          <span class="col-span-2 text-right pr-2">Amount</span>
        </div>
        <!-- Rows -->
        <div class="divide-y divide-gray-100 bg-white border-x border-b border-gray-100 rounded-b-2xl overflow-hidden">
          <div v-for="(it, idx) in form.items" :key="idx" class="grid grid-cols-12 gap-4 px-5 py-4 items-start hover:bg-gray-50/20 transition-colors">
            <!-- Column 1: Item details + product picker -->
            <div class="col-span-5 space-y-2">
              <input v-model="it.description" type="text" class="inv-input font-medium !bg-white" placeholder="Item description" required />
              <select v-model="it.product_id" class="inv-select text-xs text-gray-400 w-full !bg-white" @change="pickProduct(idx, it.product_id)">
                <option value="">— Type manually or select product —</option>
                <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </div>

            <!-- Column 2: QTY + Unit -->
            <div class="col-span-2 space-y-2">
              <input v-model="it.quantity" type="number" min="0.01" step="0.01" class="inv-input text-center tabular-nums !bg-white" />
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
              <select v-model="it.gst_rate" class="inv-select text-center text-xs !bg-white">
                <option v-for="r in gstRates" :key="r" :value="r">{{ r }}% GST</option>
              </select>
            </div>

            <!-- Column 4: calculated line total + remove button -->
            <div class="col-span-2 flex flex-col items-end justify-between h-[86px] py-1">
              <span class="text-sm font-semibold text-gray-800 tabular-nums pr-2">
                {{ inr(parseFloat(it.quantity||0) * parseFloat(it.unit_price||0) * (1 + parseFloat(it.gst_rate||0)/100)) }}
              </span>
              <button v-if="form.items.length > 1" type="button" @click="removeItem(idx)"
                class="mr-1 w-7 h-7 flex items-center justify-center text-gray-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition" title="Remove">
                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Mobile items layout -->
      <div class="lg:hidden space-y-4">
        <div v-for="(it, idx) in form.items" :key="idx" class="bg-gray-50 rounded-2xl p-4 space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Item {{ idx + 1 }}</span>
            <button v-if="form.items.length > 1" @click="removeItem(idx)" class="text-gray-400 hover:text-red-500 p-1 rounded-full transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>

          <div>
            <label class="form-label">Product</label>
            <select v-model="it.product_id" class="form-select !bg-white" @change="pickProduct(idx, it.product_id)">
              <option value="">Type manually or select…</option>
              <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>
          <div>
            <label class="form-label">Description *</label>
            <input v-model="it.description" type="text" class="form-input !bg-white" placeholder="Item description" required />
          </div>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div>
              <label class="form-label">Qty</label>
              <input v-model="it.quantity" type="number" min="0.01" step="0.01" class="form-input !bg-white" />
            </div>
            <div>
              <label class="form-label">Unit</label>
              <select v-model="it.unit" class="form-select !bg-white">
                <option v-for="u in units" :key="u">{{ u }}</option>
              </select>
            </div>
            <div>
              <label class="form-label">Rate (₹)</label>
              <input v-model="it.unit_price" type="number" min="0" step="0.01" class="form-input !bg-white" placeholder="0.00" />
            </div>
            <div>
              <label class="form-label">GST %</label>
              <select v-model="it.gst_rate" class="form-select !bg-white">
                <option v-for="r in gstRates" :key="r" :value="r">{{ r }}%</option>
              </select>
            </div>
          </div>
          <div class="flex justify-between text-sm font-semibold text-gray-700 pt-1">
            <span>Line Total</span>
            <span>{{ inr(parseFloat(it.quantity||0) * parseFloat(it.unit_price||0) * (1 + parseFloat(it.gst_rate||0)/100)) }}</span>
          </div>
        </div>
      </div>

      <!-- Order Totals -->
      <div class="border-t border-gray-100 pt-4 space-y-2 text-sm">
        <div class="flex justify-between text-gray-600"><span>Subtotal</span><span>{{ inr(totals.subtotal) }}</span></div>
        <div class="flex justify-between text-gray-600"><span>GST</span><span>{{ inr(totals.tax) }}</span></div>
        <div class="flex justify-between font-bold text-base text-gray-900 border-t border-gray-200 pt-2">
          <span>Total</span><span>{{ inr(totals.total) }}</span>
        </div>
      </div>
    </div>

    <!-- Notes -->
    <div class="card card-body animate-fade-in-up anim-delay-150">
      <label class="form-label">Notes / Terms</label>
      <textarea v-model="form.notes" rows="3" class="form-textarea" placeholder="Any notes for the supplier…"></textarea>
    </div>

    <!-- Submit -->
    <div class="flex gap-3 animate-fade-in-up anim-delay-200">
      <button @click="router.push('/purchase-orders')" class="btn-outline flex-1">Cancel</button>
      <button @click="submit" :disabled="loading" class="btn-primary flex-1">
        {{ loading ? 'Saving…' : saved ? 'Saved ✓' : isEdit ? 'Update PO' : 'Create PO' }}
      </button>
    </div>
  </div>
</template>
