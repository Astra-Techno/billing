<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { task, item, all, list } from '../../api'
import { inr } from '../../utils/currency'
import { today, addDays } from '../../utils/date'
import { calcInvoice } from '../../utils/invoice'

const router   = useRouter()
const route    = useRoute()
const emit     = defineEmits(['refresh'])
const clients  = ref([])
const products = ref([])
const taxRates = ref([])
const loading  = ref(false)
const error    = ref('')

const isEdit = computed(() => !!route.params.id)

const blankItem = () => ({ description: '', hsn_sac: '', unit: 'Nos', quantity: 1, unit_price: '', discount_pct: 0, gst_rate: 18, product_id: null })

const form = ref({
  client_id:   '',
  type:        'quote',
  issue_date:  today(),
  valid_until: addDays(today(), 30),
  notes: '', terms: '',
  items: [blankItem()],
})

const units    = ['Nos', 'Kg', 'Ltr', 'Hrs', 'Pcs', 'Mtr', 'Box', 'Set']
const gstRates = [0, 5, 12, 18, 28]
const totals   = computed(() => calcInvoice(form.value.items))

onMounted(async () => {
  const [cRes, pRes, tRes] = await Promise.all([all('Client'), all('Product'), all('TaxRate')])
  clients.value  = cRes.data?.data  || []
  products.value = pRes.data?.data  || []
  taxRates.value = tRes.data?.data  || []

  if (isEdit.value) {
    try {
      const [qRes, iRes] = await Promise.all([
        item('Quote', { id: route.params.id }),
        list('Quote:items', { quote_id: route.params.id }),
      ])
      const q = qRes.data?.data
      if (q) {
        form.value.client_id   = q.client_id
        form.value.type        = q.type
        form.value.issue_date  = q.issue_date
        form.value.valid_until = q.valid_until
        form.value.notes       = q.notes  || ''
        form.value.terms       = q.terms  || ''
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
      router.push('/quotes/' + route.params.id)
    } else {
      const { data } = await task('Quote', 'create', form.value)
      emit('refresh')
      router.push('/quotes/' + data.data.quote_id)
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save. Please check and try again.'
  } finally { loading.value = false }
}
</script>

<template>
  <div class="max-w-3xl mx-auto space-y-5">
    <div class="flex items-center gap-3">
      <button @click="router.back()" class="p-2 rounded-lg hover:bg-gray-100">
        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      </button>
      <div>
        <h1 class="page-title">{{ isEdit ? 'Edit Quotation' : 'New Quotation' }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ isEdit ? 'Update the quotation details' : 'Send a price estimate to your customer' }}</p>
      </div>
    </div>

    <form @submit.prevent="submit" class="space-y-5">
      <div class="card card-body space-y-4">
        <h2 class="section-title">Quotation Details</h2>
        <div class="grid sm:grid-cols-2 gap-4">
          <div class="sm:col-span-2">
            <label class="form-label">Customer *</label>
            <select v-model="form.client_id" class="form-select" required>
              <option value="">Choose a customer…</option>
              <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
          <div>
            <label class="form-label">Document Type</label>
            <select v-model="form.type" class="form-select">
              <option value="quote">Quotation</option>
              <option value="proforma">Proforma Invoice</option>
            </select>
          </div>
          <div>
            <label class="form-label">Quote Date</label>
            <input v-model="form.issue_date" type="date" class="form-input" />
          </div>
          <div>
            <label class="form-label">Valid Until <span class="text-gray-400 font-normal text-xs">(offer expires)</span></label>
            <input v-model="form.valid_until" type="date" class="form-input" />
          </div>
        </div>
      </div>

      <div class="card">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <h2 class="section-title mb-0">Items / Services</h2>
          <span class="text-xs text-gray-400">What are you quoting?</span>
        </div>
        <div class="divide-y divide-gray-100">
          <div v-for="(it, i) in form.items" :key="i" class="p-4 space-y-3">
            <div v-if="products.length">
              <label class="form-label">Pick from your Products <span class="text-gray-400 font-normal">(or type manually below)</span></label>
              <select v-model="it.product_id" class="form-select" @change="pickProduct(i, it.product_id)">
                <option :value="null">— Type manually —</option>
                <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </div>
            <div>
              <label class="form-label">Description *</label>
              <input v-model="it.description" type="text" class="form-input" placeholder="What are you offering?" required />
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
              <div><label class="form-label">Unit</label>
                <select v-model="it.unit" class="form-select"><option v-for="u in units" :key="u">{{ u }}</option></select>
              </div>
              <div><label class="form-label">Quantity</label>
                <input v-model="it.quantity" type="number" min="0.001" step="0.001" class="form-input" />
              </div>
              <div><label class="form-label">Price (₹)</label>
                <input v-model="it.unit_price" type="number" min="0" step="0.01" class="form-input" placeholder="0.00" />
              </div>
              <div><label class="form-label">GST Rate %</label>
                <select v-model="it.gst_rate" class="form-select">
                  <option v-for="r in gstRates" :key="r" :value="r">{{ r }}%</option>
                </select>
              </div>
              <div class="col-span-2 flex items-center justify-between">
                <p class="text-sm font-semibold text-gray-800">Total: {{ inr(lineTotal(it)) }}</p>
                <button v-if="form.items.length > 1" type="button" @click="removeItem(i)" class="text-xs text-danger-500">Remove</button>
              </div>
            </div>
          </div>
        </div>
        <div class="px-5 py-3 border-t border-gray-100">
          <button type="button" @click="addItem" class="btn-outline btn-sm">+ Add Another Item</button>
        </div>
      </div>

      <div class="card card-body">
        <div class="max-w-xs ml-auto space-y-1.5 text-sm">
          <div class="flex justify-between text-gray-600"><span>Subtotal</span><span>{{ inr(totals.subtotal) }}</span></div>
          <div v-if="totals.tax > 0" class="flex justify-between text-gray-600"><span>GST</span><span>{{ inr(totals.tax) }}</span></div>
          <div class="flex justify-between font-bold text-base text-gray-900 border-t border-gray-200 pt-2">
            <span>Grand Total</span><span>{{ inr(totals.total) }}</span>
          </div>
        </div>
      </div>

      <div class="card card-body grid sm:grid-cols-2 gap-4">
        <div>
          <label class="form-label">Message to Customer <span class="text-gray-400 font-normal">(printed on quote)</span></label>
          <textarea v-model="form.notes" rows="3" class="form-input" placeholder="e.g. Thank you for considering us!"></textarea>
        </div>
        <div>
          <label class="form-label">Terms & Conditions</label>
          <textarea v-model="form.terms" rows="3" class="form-input" placeholder="e.g. Prices valid for 30 days."></textarea>
        </div>
      </div>

      <div v-if="error" class="text-sm text-danger-500 bg-danger-50 rounded-lg px-4 py-3">{{ error }}</div>
      <div class="flex gap-3 pb-6">
        <button type="button" @click="router.back()" class="btn-outline flex-1">Cancel</button>
        <button type="submit" class="btn-primary flex-1" :disabled="loading">
          {{ loading ? 'Saving…' : isEdit ? 'Save Changes' : 'Create Quotation' }}
        </button>
      </div>
    </form>
  </div>
</template>
