<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { item, list, task } from '../../api'
import { inr } from '../../utils/currency'
import { fmtDateShort } from '../../utils/date'

const route  = useRoute()
const router = useRouter()
const emit   = defineEmits(['refresh'])

const pi       = ref(null)
const items    = ref([])
const payments = ref([])
const loading  = ref(true)
const acting   = ref(false)
const actError = ref('')

// Payment form
const showPay  = ref(false)
const payForm  = ref({ amount: '', method: 'cash', reference: '', payment_date: new Date().toISOString().slice(0,10) })
const paying   = ref(false)

async function load() {
  loading.value = true
  try {
    const id = route.params.id
    const [piRes, itmRes, payRes] = await Promise.all([
      item('PurchaseInvoice', { id }),
      list('PurchaseInvoice:items', { pi_id: id }),
      list('PurchaseInvoice:payments', { pi_id: id }),
    ])
    pi.value       = piRes.data?.data
    items.value    = itmRes.data?.data || []
    payments.value = payRes.data?.data || []
    if (pi.value) payForm.value.amount = pi.value.amount_due
  } catch {}
  loading.value = false
}

async function doAction(action) {
  acting.value  = true
  actError.value = ''
  try {
    await task('PurchaseInvoice', action, { id: pi.value.id })
    emit('refresh')
    await load()
  } catch (e) {
    actError.value = e.response?.data?.message || 'Action failed.'
  } finally { acting.value = false }
}

async function recordPayment() {
  paying.value = true
  actError.value = ''
  try {
    await task('PurchaseInvoice', 'pay', {
      id: pi.value.id,
      amount: parseFloat(payForm.value.amount),
      method: payForm.value.method,
      reference: payForm.value.reference,
      payment_date: payForm.value.payment_date,
    })
    showPay.value = false
    emit('refresh')
    await load()
  } catch (e) {
    actError.value = e.response?.data?.message || 'Payment failed.'
  } finally { paying.value = false }
}

async function deletePayment(payId) {
  if (!confirm('Delete this payment?')) return
  try {
    await task('PurchaseInvoice', 'deletePayment', { id: pi.value.id, payment_id: payId })
    emit('refresh')
    await load()
  } catch (e) {
    actError.value = e.response?.data?.message || 'Failed.'
  }
}

onMounted(load)

const badgeClass = s => ({ draft: 'badge-gray', recorded: 'badge-blue', partial: 'badge-yellow', paid: 'badge-green', cancelled: 'badge-red' }[s] || 'badge-gray')
const statusLabel = s => ({ draft: 'Draft', recorded: 'Recorded', partial: 'Partial', paid: 'Paid', cancelled: 'Cancelled' }[s] || s)
const methodLabel = m => ({ cash:'Cash',upi:'UPI',neft:'NEFT',rtgs:'RTGS',imps:'IMPS',cheque:'Cheque',card:'Card',netbanking:'Net Banking',other:'Other' }[m] || m)
</script>

<template>
  <div class="gpay-screen px-4 py-4 max-w-3xl lg:mx-auto space-y-5 pb-10">

    <div class="flex items-center gap-3 pt-2">
      <button @click="router.push('/purchases')" class="p-2 -ml-2 rounded-full hover:bg-gray-100 transition-colors">
        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
      </button>
    </div>

    <div v-if="loading" class="flex justify-center p-12">
      <div class="w-8 h-8 border-4 border-primary-100 border-t-primary-600 rounded-full animate-spin"></div>
    </div>

    <template v-else-if="pi">

      <!-- Hero -->
      <div class="flex flex-col items-center text-center animate-fade-in-up mt-4 mb-2">
        <div class="w-16 h-16 rounded-full bg-orange-50 text-orange-600 flex items-center justify-center mb-3">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
        </div>
        <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">{{ pi.supplier_name }}</p>
        <h1 class="text-5xl font-extrabold tracking-tight text-gray-900">{{ inr(pi.total) }}</h1>
        <div class="flex items-center gap-2 mt-3">
          <p class="text-sm font-semibold text-gray-600">{{ pi.number }}</p>
          <span :class="badgeClass(pi.status)" class="text-[10px] px-2 py-0.5">{{ statusLabel(pi.status) }}</span>
        </div>
        <p v-if="pi.amount_due > 0 && pi.status !== 'draft'" class="text-sm font-bold text-red-500 mt-1">Balance due: {{ inr(pi.amount_due) }}</p>
      </div>

      <!-- Action Pills -->
      <div class="flex flex-wrap justify-center gap-2 w-full max-w-lg mx-auto animate-fade-in-up mb-4">
        <button v-if="pi.status === 'draft'" @click="doAction('record')" :disabled="acting"
          class="flex-1 min-w-[100px] btn bg-primary-600 text-white hover:bg-primary-700 shadow-gpay flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          <span class="text-xs">Record & Post Stock</span>
        </button>

        <button v-if="pi.status === 'recorded' || pi.status === 'partial'" @click="showPay = true"
          class="flex-1 min-w-[100px] btn bg-emerald-600 text-white hover:bg-emerald-700 shadow-soft flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1"/></svg>
          <span class="text-xs">Record Payment</span>
        </button>

        <RouterLink v-if="pi.status === 'draft'" :to="`/purchases/${pi.id}/edit`"
          class="flex-1 min-w-[100px] btn bg-gray-50 text-gray-800 border border-gray-100 hover:bg-gray-100 shadow-soft flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
          <span class="text-xs">Edit</span>
        </RouterLink>

        <button v-if="pi.status !== 'paid' && pi.status !== 'cancelled'" @click="doAction('cancel')" :disabled="acting"
          class="flex-1 min-w-[100px] btn bg-red-50 text-red-600 border border-red-100 hover:bg-red-100 shadow-soft flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
          <span class="text-xs">Cancel</span>
        </button>
      </div>

      <div v-if="actError" class="text-sm text-danger-600 bg-danger-50 rounded-xl px-4 py-3">{{ actError }}</div>

      <!-- Document -->
      <div class="bg-white rounded-[2rem] shadow-soft border-0 overflow-hidden animate-fade-in-up anim-delay-75">

        <div class="px-5 pt-5 pb-4 border-b border-gray-200 space-y-3">
          <div class="flex items-center justify-between">
            <p class="text-xl font-black text-orange-700 uppercase tracking-widest">Purchase Bill</p>
            <p class="text-sm font-bold text-gray-700">{{ pi.number }}</p>
          </div>
          <p v-if="pi.supplier_inv_no" class="text-xs text-gray-500">Supplier Ref: <b>{{ pi.supplier_inv_no }}</b></p>
        </div>

        <div class="grid grid-cols-2 border-b border-gray-200">
          <div class="px-5 py-4 border-r border-gray-200">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Supplier</p>
            <p class="font-bold text-gray-900 text-sm">{{ pi.supplier_name }}</p>
            <p v-if="pi.supplier_company" class="text-[11px] text-gray-600">{{ pi.supplier_company }}</p>
            <p v-if="pi.supplier_gstin" class="text-[11px] text-gray-500 font-mono mt-1">GSTIN: {{ pi.supplier_gstin }}</p>
            <p v-if="pi.supplier_mobile" class="text-[11px] text-gray-500 mt-0.5">{{ pi.supplier_mobile }}</p>
          </div>
          <div class="px-5 py-4">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Bill Details</p>
            <table class="text-xs w-full">
              <tr><td class="text-gray-400 pb-1 pr-3">Bill No.</td><td class="font-semibold text-gray-800 pb-1">{{ pi.number }}</td></tr>
              <tr><td class="text-gray-400 pb-1 pr-3">Bill Date</td><td class="font-medium text-gray-700 pb-1">{{ fmtDateShort(pi.invoice_date) }}</td></tr>
              <tr v-if="pi.due_date"><td class="text-gray-400 pr-3">Due Date</td><td class="font-medium text-gray-700">{{ fmtDateShort(pi.due_date) }}</td></tr>
              <tr v-if="pi.location_name"><td class="text-gray-400 pr-3 pt-1">Location</td><td class="font-medium text-gray-700 pt-1">{{ pi.location_name }}</td></tr>
            </table>
          </div>
        </div>

        <!-- Items -->
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-800 text-white">
              <tr>
                <th class="px-3 py-2.5 text-left text-xs font-semibold w-7">#</th>
                <th class="px-3 py-2.5 text-left text-xs font-semibold">Description</th>
                <th class="px-3 py-2.5 text-center text-xs font-semibold">HSN/SAC</th>
                <th class="px-3 py-2.5 text-right text-xs font-semibold">Qty</th>
                <th class="px-3 py-2.5 text-right text-xs font-semibold">Rate</th>
                <th class="px-3 py-2.5 text-right text-xs font-semibold">GST</th>
                <th class="px-3 py-2.5 text-right text-xs font-semibold">Amount</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="(it, idx) in items" :key="it.id">
                <td class="px-3 py-3 text-gray-400 text-xs">{{ idx + 1 }}</td>
                <td class="px-3 py-3"><p class="font-medium text-gray-800">{{ it.description }}</p><p v-if="it.unit" class="text-xs text-gray-400">{{ it.unit }}</p></td>
                <td class="px-3 py-3 text-center font-mono text-xs text-gray-500">{{ it.hsn_sac || '—' }}</td>
                <td class="px-3 py-3 text-right text-gray-700">{{ it.quantity }}</td>
                <td class="px-3 py-3 text-right text-gray-700">{{ inr(it.unit_price) }}</td>
                <td class="px-3 py-3 text-right text-xs text-gray-600">{{ it.gst_rate }}%</td>
                <td class="px-3 py-3 text-right font-semibold text-gray-900">{{ inr(it.total) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Totals -->
        <div class="p-5 border-t border-gray-200 flex justify-end">
          <div class="w-64 space-y-1.5 text-sm">
            <div class="flex justify-between text-gray-600"><span>Subtotal</span><span>{{ inr(pi.subtotal) }}</span></div>
            <div v-if="pi.discount > 0" class="flex justify-between text-gray-600"><span>Discount</span><span>-{{ inr(pi.discount) }}</span></div>
            <div v-if="pi.cgst_total > 0" class="flex justify-between text-gray-600"><span>CGST</span><span>{{ inr(pi.cgst_total) }}</span></div>
            <div v-if="pi.sgst_total > 0" class="flex justify-between text-gray-600"><span>SGST</span><span>{{ inr(pi.sgst_total) }}</span></div>
            <div v-if="pi.igst_total > 0" class="flex justify-between text-gray-600"><span>IGST</span><span>{{ inr(pi.igst_total) }}</span></div>
            <div v-if="pi.round_off" class="flex justify-between text-gray-500 text-xs"><span>Round off</span><span>{{ pi.round_off > 0 ? '+' : '' }}{{ pi.round_off }}</span></div>
            <div class="flex justify-between font-bold text-base text-gray-900 border-t border-gray-200 pt-2"><span>Total</span><span>{{ inr(pi.total) }}</span></div>
            <div v-if="pi.amount_paid > 0" class="flex justify-between text-emerald-600"><span>Paid</span><span>{{ inr(pi.amount_paid) }}</span></div>
            <div v-if="pi.amount_due > 0" class="flex justify-between font-bold text-red-600"><span>Balance Due</span><span>{{ inr(pi.amount_due) }}</span></div>
          </div>
        </div>

        <div v-if="pi.notes" class="px-5 pb-5 border-t border-gray-100 pt-4">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Notes</p>
          <p class="text-sm text-gray-600">{{ pi.notes }}</p>
        </div>
      </div>

      <!-- Payments -->
      <div v-if="payments.length" class="bg-white rounded-[2rem] shadow-soft border-0 overflow-hidden animate-fade-in-up">
        <div class="px-5 pt-4 pb-3 border-b border-gray-200">
          <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">Payments Made</p>
        </div>
        <div class="divide-y divide-gray-100">
          <div v-for="p in payments" :key="p.id" class="px-5 py-3 flex items-center justify-between">
            <div>
              <p class="text-sm font-semibold text-gray-900">{{ inr(p.amount) }} <span class="text-xs text-gray-400 font-normal">via {{ methodLabel(p.method) }}</span></p>
              <p class="text-xs text-gray-500">{{ fmtDateShort(p.payment_date) }}<template v-if="p.reference"> · {{ p.reference }}</template></p>
            </div>
            <button @click="deletePayment(p.id)" class="text-xs text-red-500 hover:text-red-700" title="Delete payment">✕</button>
          </div>
        </div>
      </div>

      <!-- Payment Modal -->
      <div v-if="showPay" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-4">
          <h3 class="font-bold text-lg">Record Payment</h3>
          <div><label class="form-label">Amount</label><input v-model.number="payForm.amount" type="number" step="0.01" class="form-input" /></div>
          <div><label class="form-label">Method</label>
            <select v-model="payForm.method" class="form-input">
              <option value="cash">Cash</option><option value="upi">UPI</option><option value="neft">NEFT</option>
              <option value="rtgs">RTGS</option><option value="imps">IMPS</option><option value="cheque">Cheque</option>
              <option value="card">Card</option><option value="netbanking">Net Banking</option><option value="other">Other</option>
            </select>
          </div>
          <div><label class="form-label">Reference / UTR</label><input v-model="payForm.reference" type="text" class="form-input" /></div>
          <div><label class="form-label">Payment Date</label><input v-model="payForm.payment_date" type="date" class="form-input" /></div>
          <div class="flex gap-3 pt-2">
            <button @click="showPay = false" class="btn bg-gray-100 text-gray-700 hover:bg-gray-200 flex-1 border-0" :disabled="paying">Cancel</button>
            <button @click="recordPayment" class="btn bg-primary-600 text-white hover:bg-primary-700 flex-1 border-0" :disabled="paying">{{ paying ? 'Saving…' : 'Save Payment' }}</button>
          </div>
        </div>
      </div>

    </template>
  </div>
</template>
