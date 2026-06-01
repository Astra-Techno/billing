<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { task, item, all, list } from '../../api'
import { inr } from '../../utils/currency'
import { today, addDays } from '../../utils/date'
import { calcInvoice } from '../../utils/invoice'
import { useToast } from '../../composables/useToast'
import { useBusinessStore } from '../../stores/business'

const router        = useRouter()
const route         = useRoute()
const emit          = defineEmits(['refresh'])
const toast         = useToast()
const businessStore = useBusinessStore()
const clients       = ref([])
const products      = ref([])
const taxRates      = ref([])
const states        = ref([])
const loading  = ref(false)
const error    = ref('')

const isEdit = computed(() => !!route.params.id)

const blankItem = () => ({ description: '', hsn_sac: '', unit: 'Nos', quantity: 1, unit_price: '', discount_pct: 0, gst_rate: 18, product_id: null })

const form = ref({
  client_id:       '',
  type:            'quote',
  issue_date:      today(),
  valid_until:     addDays(today(), 30),
  place_of_supply: '',
  notes: '', terms: '',
  items: [blankItem()],
})

const units    = ['Nos', 'Kg', 'Ltr', 'Hrs', 'Pcs', 'Mtr', 'Box', 'Set']
const gstRates = [0, 5, 12, 18, 28]
const totals   = computed(() => calcInvoice(form.value.items))

onMounted(async () => {
  const [cRes, pRes, tRes, sRes, bizRes] = await Promise.all([all('Client'), all('Product'), all('TaxRate'), all('IndianState'), item('Business')])
  clients.value  = cRes.data?.data  || []
  products.value = pRes.data?.data  || []
  taxRates.value = tRes.data?.data  || []
  states.value   = sRes.data?.data  || []
  const bizStateId = bizRes.data?.data?.state_id
  if (bizStateId) businessStore.setStateId(bizStateId)

  if (!isEdit.value) {
    form.value.place_of_supply = businessStore.stateId || ''
  }

  if (isEdit.value) {
    try {
      const [qRes, iRes] = await Promise.all([
        item('Quote', { id: route.params.id }),
        list('Quote:items', { quote_id: route.params.id }),
      ])
      const q = qRes.data?.data
      if (q) {
        form.value.client_id       = q.client_id
        form.value.type            = q.type
        form.value.issue_date      = q.issue_date
        form.value.valid_until     = q.valid_until
        form.value.place_of_supply = q.place_of_supply || businessStore.stateId || ''
        form.value.notes           = q.notes  || ''
        form.value.terms           = q.terms  || ''
      }
      const its = iRes.data?.data || []
      if (its.length) {
        form.value.items = its.map(it => ({
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
    } catch {
      error.value = 'Could not load quotation data. Please try again.'
    }
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
  const qty = parseFloat(it.quantity || 0), price = parseFloat(it.unit_price || 0)
  const disc = parseFloat(it.discount_pct || 0), gst = parseFloat(it.gst_rate || 0)
  return qty * price * (1 - disc / 100) * (1 + gst / 100)
}

async function submit() {
  error.value = ''
  if (!form.value.client_id) return (error.value = 'Please choose a customer.')
  if (!form.value.items.some(i => i.description)) return (error.value = 'Please add at least one item.')
  loading.value = true
  try {
    if (isEdit.value) {
      await task('Quote', 'update', { ...form.value, id: route.params.id })
      emit('refresh')
      toast.success('Quote updated.')
      router.push('/quotes/' + route.params.id)
    } else {
      const { data } = await task('Quote', 'create', form.value)
      emit('refresh')
      toast.success('Quote created.')
      router.push('/quotes/' + data.data.quote_id)
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save. Please check and try again.'
  } finally { loading.value = false }
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
        <h1 class="inv-page-title">{{ isEdit ? 'Edit Quotation' : 'New Quotation' }}</h1>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" @click="router.back()" class="inv-btn-secondary hidden sm:inline-flex">Cancel</button>
        <button type="submit" form="quote-form" class="inv-btn-primary" :disabled="loading">
          {{ loading ? 'Saving…' : isEdit ? 'Save Changes' : 'Create Quotation' }}
        </button>
      </div>
    </div>

    <!-- Body: two-column on desktop -->
    <div class="inv-body">
      <form id="quote-form" @submit.prevent="submit" class="inv-layout">

        <!-- LEFT: Main content -->
        <div class="inv-main">

          <!-- Items card -->
          <div class="inv-card">
            <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
              <h2 class="text-sm font-semibold text-gray-800">Items / Services</h2>
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
                <div v-for="(it, i) in form.items" :key="i" class="grid grid-cols-12 gap-4 px-5 py-4 items-start hover:bg-gray-50/20 transition-colors">
                  <!-- Col 1: Description + product picker -->
                  <div class="col-span-5 space-y-2">
                    <input v-model="it.description" type="text" class="inv-input font-medium !bg-white" placeholder="What are you offering?" required />
                    <select v-if="products.length" v-model="it.product_id" class="inv-select text-xs text-gray-400 w-full !bg-white" @change="pickProduct(i, it.product_id)">
                      <option :value="null">— Select from products —</option>
                      <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                  </div>
                  <!-- Col 2: QTY + Unit -->
                  <div class="col-span-2 space-y-2">
                    <input v-model="it.quantity" type="number" min="0.001" step="0.001" class="inv-input text-center tabular-nums !bg-white" />
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
                <div v-if="products.length" class="flex items-center gap-2">
                  <div class="flex-1">
                    <label class="inv-label">Product</label>
                    <select v-model="it.product_id" class="inv-select w-full" @change="pickProduct(i, it.product_id)">
                      <option :value="null">— Type manually —</option>
                      <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                  </div>
                </div>
                <div><label class="inv-label">Description *</label><input v-model="it.description" type="text" class="inv-input w-full" required /></div>
                <div class="grid grid-cols-2 gap-2">
                  <div><label class="inv-label">Qty</label><input v-model="it.quantity" type="number" class="inv-input w-full" /></div>
                  <div><label class="inv-label">Unit</label><select v-model="it.unit" class="inv-select w-full"><option v-for="u in units" :key="u">{{ u }}</option></select></div>
                  <div><label class="inv-label">Price (₹)</label><input v-model="it.unit_price" type="number" class="inv-input w-full" /></div>
                  <div><label class="inv-label">GST</label><select v-model="it.gst_rate" class="inv-select w-full"><option v-for="r in gstRates" :key="r" :value="r">{{ r }}%</option></select></div>
                </div>
                <div class="flex items-center justify-between">
                  <p class="text-sm font-bold">{{ inr(lineTotal(it)) }}</p>
                  <button v-if="form.items.length > 1" type="button" @click="removeItem(i)" class="text-xs text-red-500 font-medium">Remove</button>
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

          <!-- Notes / Terms -->
          <div class="inv-card p-5 space-y-3">
            <h2 class="text-sm font-semibold text-gray-700">Notes / Terms</h2>
            <div class="grid sm:grid-cols-2 gap-3">
              <div>
                <label class="inv-label">Message to customer</label>
                <textarea v-model="form.notes" rows="3" class="inv-textarea w-full" placeholder="e.g. Thank you for considering us!"></textarea>
              </div>
              <div>
                <label class="inv-label">Terms &amp; conditions</label>
                <textarea v-model="form.terms" rows="3" class="inv-textarea w-full" placeholder="e.g. Prices valid for 30 days."></textarea>
              </div>
            </div>
          </div>

        </div><!-- /inv-main -->

        <!-- RIGHT: Sidebar -->
        <aside class="inv-sidebar">

          <!-- Quote To -->
          <div class="inv-card">
            <div class="px-5 py-3.5 border-b border-gray-100">
              <h2 class="text-sm font-semibold text-gray-800">Quote To</h2>
              <p class="text-xs text-gray-400 mt-0.5">*Select one customer</p>
            </div>
            <div class="p-4">
              <label class="inv-label">Customer *</label>
              <select v-model="form.client_id" class="inv-select w-full" required>
                <option value="">Choose a customer…</option>
                <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
          </div>

          <!-- Quote Details -->
          <div class="inv-card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-800">Quote Details</h2>
            <div>
              <label class="inv-label">Document Type</label>
              <select v-model="form.type" class="inv-select w-full">
                <option value="quote">Quotation</option>
                <option value="proforma">Proforma Invoice</option>
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
                <label class="inv-label">Quote Date</label>
                <input v-model="form.issue_date" type="date" class="inv-input w-full" />
              </div>
              <div>
                <label class="inv-label">Valid Until</label>
                <input v-model="form.valid_until" type="date" class="inv-input w-full" />
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
    <div class="form-footer-mobile lg:hidden">
      <div class="flex-1 min-w-0">
        <p class="text-[10px] font-bold text-google-muted uppercase">Total</p>
        <p class="text-lg font-bold text-primary-600 tabular-nums">{{ inr(totals.total) }}</p>
      </div>
      <button type="button" @click="router.back()" class="btn-outline">Cancel</button>
      <button type="submit" form="quote-form" class="btn-primary" :disabled="loading">
        {{ loading ? '…' : isEdit ? 'Save' : 'Create' }}
      </button>
    </div>
  </div>
</template>
