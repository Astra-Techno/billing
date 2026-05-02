<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api, { item, list } from '../../api'
import { inr } from '../../utils/currency'
import { fmtDateShort } from '../../utils/date'

const route   = useRoute()
const invoice  = ref(null)
const business = ref(null)
const items    = ref([])
const loading  = ref(true)
const error    = ref('')

const invoiceTitle = computed(() => {
  const map = { tax_invoice: 'Tax Invoice', bill_of_supply: 'Bill of Supply', retail: 'Retail Invoice', export: 'Export Invoice' }
  return map[invoice.value?.invoice_type] || 'Tax Invoice'
})

const isGst = computed(() => invoice.value?.invoice_type !== 'bill_of_supply')

const ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine',
  'Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen']
const tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety']
function toW(n) {
  if (!n) return ''
  if (n < 20) return ones[n] + ' '
  if (n < 100) return tens[Math.floor(n/10)] + ' ' + (ones[n%10] ? ones[n%10] + ' ' : '')
  if (n < 1000) return ones[Math.floor(n/100)] + ' Hundred ' + toW(n%100)
  if (n < 100000) return toW(Math.floor(n/1000)) + 'Thousand ' + toW(n%1000)
  if (n < 10000000) return toW(Math.floor(n/100000)) + 'Lakh ' + toW(n%100000)
  return toW(Math.floor(n/10000000)) + 'Crore ' + toW(n%10000000)
}
function amountInWords(amount) {
  const n = Math.round(+amount || 0)
  const p = Math.round(((+amount || 0) - Math.floor(+amount || 0)) * 100)
  let w = (toW(n) || 'Zero ').trim() + ' Rupees'
  if (p > 0) w += ' and ' + toW(p).trim() + ' Paise'
  return w + ' Only'
}

onMounted(async () => {
  try {
    const id = route.params.id
    const [invRes, itmRes, bizRes] = await Promise.all([
      item('Invoice', { id }),
      list('Invoice:items', { invoice_id: id }),
      item('Business'),
    ])
    invoice.value  = invRes.data?.data
    items.value    = itmRes.data?.data || []
    business.value = bizRes.data?.data || null
  } catch (e) {
    error.value = 'Could not load invoice. Please ensure you are logged in.'
    loading.value = false
    return
  }
  loading.value = false
  // Auto-print after data loads
  setTimeout(() => window.print(), 300)
})
</script>

<template>
  <div class="print-page">
    <div v-if="loading" class="flex items-center justify-center min-h-screen">
      <div class="w-8 h-8 border-4 border-blue-100 border-t-blue-600 rounded-full animate-spin"></div>
    </div>
    <div v-else-if="error" class="flex items-center justify-center min-h-screen text-red-600 text-sm p-8 text-center">{{ error }}</div>

    <div v-else-if="invoice" class="invoice-doc">

      <!-- Title row -->
      <div class="flex items-center justify-between mb-4 pb-3 border-b-2 border-gray-800">
        <p class="text-2xl font-black text-blue-800 uppercase tracking-widest">{{ invoiceTitle }}</p>
        <p class="text-base font-bold text-gray-700">{{ invoice.number }}</p>
      </div>

      <!-- Business info -->
      <div class="flex items-start gap-3 mb-4 pb-3 border-b border-gray-200">
        <img v-if="business?.logo" :src="business.logo" class="w-12 h-12 object-contain rounded-lg border border-gray-100 shrink-0" alt="logo" />
        <div>
          <p class="text-base font-bold text-gray-900">{{ business?.name || invoice.business_name }}</p>
          <p v-if="business?.address_line1" class="text-xs text-gray-500">{{ business.address_line1 }}<span v-if="business.address_line2">, {{ business.address_line2 }}</span></p>
          <p v-if="business?.city" class="text-xs text-gray-500">{{ [business?.city, business?.state_name, business?.pincode].filter(Boolean).join(', ') }}</p>
          <p v-if="business?.mobile || business?.email" class="text-xs text-gray-500">{{ [business?.mobile, business?.email].filter(Boolean).join(' · ') }}</p>
          <p v-if="business?.gstin || invoice.business_gstin" class="text-xs text-gray-500 font-mono">GSTIN: {{ business?.gstin || invoice.business_gstin }}</p>
        </div>
      </div>

      <!-- Bill To + Invoice Details -->
      <div class="grid grid-cols-2 gap-4 mb-4 pb-3 border-b border-gray-200">
        <div>
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Bill To</p>
          <p class="font-bold text-gray-900 text-sm">{{ invoice.client_name }}</p>
          <p v-if="invoice.client_company" class="text-xs text-gray-600">{{ invoice.client_company }}</p>
          <p v-if="invoice.client_address1" class="text-xs text-gray-600">{{ invoice.client_address1 }}<span v-if="invoice.client_address2">, {{ invoice.client_address2 }}</span></p>
          <p v-if="invoice.client_city" class="text-xs text-gray-600">{{ [invoice.client_city, invoice.client_pincode].filter(Boolean).join(' – ') }}</p>
          <p v-if="invoice.client_gstin" class="text-xs text-gray-500 font-mono">GSTIN: {{ invoice.client_gstin }}</p>
          <p v-if="invoice.client_mobile" class="text-xs text-gray-500">{{ invoice.client_mobile }}</p>
        </div>
        <div>
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Invoice Details</p>
          <table class="text-xs w-full">
            <tr><td class="text-gray-400 pb-1 pr-3">Invoice No</td><td class="font-semibold text-gray-800">{{ invoice.number }}</td></tr>
            <tr><td class="text-gray-400 pb-1 pr-3">Invoice Date</td><td class="font-medium text-gray-700">{{ fmtDateShort(invoice.issue_date) }}</td></tr>
            <tr><td class="text-gray-400 pb-1 pr-3">Due Date</td><td class="font-medium text-gray-700">{{ fmtDateShort(invoice.due_date) }}</td></tr>
            <tr><td class="text-gray-400 pr-3">Place of Supply</td><td class="font-medium text-gray-700">{{ invoice.place_of_supply_name || invoice.supply_type }}</td></tr>
          </table>
        </div>
      </div>

      <!-- Items -->
      <table class="w-full text-sm mb-4">
        <thead class="bg-gray-800 text-white">
          <tr>
            <th class="px-2 py-2 text-left text-xs font-semibold w-7">#</th>
            <th class="px-2 py-2 text-left text-xs font-semibold">Description</th>
            <th class="px-2 py-2 text-center text-xs font-semibold">HSN/SAC</th>
            <th class="px-2 py-2 text-right text-xs font-semibold">Qty</th>
            <th class="px-2 py-2 text-right text-xs font-semibold">Rate</th>
            <th class="px-2 py-2 text-right text-xs font-semibold">Taxable</th>
            <th v-if="isGst" class="px-2 py-2 text-right text-xs font-semibold">Tax</th>
            <th class="px-2 py-2 text-right text-xs font-semibold">Amount</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(it, idx) in items" :key="it.id" class="border-b border-gray-100">
            <td class="px-2 py-2 text-gray-400 text-xs">{{ idx + 1 }}</td>
            <td class="px-2 py-2">
              <p class="font-medium text-gray-800">{{ it.description }}</p>
              <p v-if="it.unit" class="text-xs text-gray-400">{{ it.unit }}</p>
            </td>
            <td class="px-2 py-2 text-center font-mono text-xs text-gray-500">{{ it.hsn_sac || '—' }}</td>
            <td class="px-2 py-2 text-right text-gray-700">{{ it.quantity }}</td>
            <td class="px-2 py-2 text-right text-gray-700">{{ inr(it.unit_price) }}</td>
            <td class="px-2 py-2 text-right text-gray-700">{{ inr(it.taxable_amt) }}</td>
            <td v-if="isGst" class="px-2 py-2 text-right text-xs">
              <div v-if="it.cgst_amt > 0" class="space-y-0.5">
                <div class="text-gray-600">CGST {{ it.cgst_rate }}%: {{ inr(it.cgst_amt) }}</div>
                <div class="text-gray-600">SGST {{ it.sgst_rate }}%: {{ inr(it.sgst_amt) }}</div>
              </div>
              <div v-else-if="it.igst_amt > 0" class="text-gray-600">IGST {{ it.igst_rate }}%: {{ inr(it.igst_amt) }}</div>
              <div v-else class="text-gray-400">Nil</div>
            </td>
            <td class="px-2 py-2 text-right font-semibold text-gray-900">{{ inr(it.total) }}</td>
          </tr>
        </tbody>
      </table>

      <!-- Totals + Words -->
      <div class="flex gap-6 mb-4 pb-4 border-b border-gray-200">
        <div class="flex-1">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Amount in Words</p>
          <p class="text-sm font-medium text-gray-700 italic">{{ amountInWords(invoice.total) }}</p>
        </div>
        <div class="w-52 space-y-1 text-xs">
          <div class="flex justify-between text-gray-600"><span>Subtotal</span><span>{{ inr(invoice.subtotal) }}</span></div>
          <div v-if="invoice.cgst_total > 0" class="flex justify-between text-gray-600"><span>CGST</span><span>{{ inr(invoice.cgst_total) }}</span></div>
          <div v-if="invoice.sgst_total > 0" class="flex justify-between text-gray-600"><span>SGST</span><span>{{ inr(invoice.sgst_total) }}</span></div>
          <div v-if="invoice.igst_total > 0" class="flex justify-between text-gray-600"><span>IGST</span><span>{{ inr(invoice.igst_total) }}</span></div>
          <div v-if="invoice.discount > 0" class="flex justify-between text-red-500"><span>Discount</span><span>-{{ inr(invoice.discount) }}</span></div>
          <div v-if="invoice.round_off != 0" class="flex justify-between text-gray-400"><span>Round Off</span><span>{{ inr(invoice.round_off) }}</span></div>
          <div class="flex justify-between font-bold text-sm text-gray-900 border-t border-gray-300 pt-1">
            <span>Total</span><span>{{ inr(invoice.total) }}</span>
          </div>
          <div v-if="invoice.amount_paid > 0" class="flex justify-between text-green-700"><span>Amount Paid</span><span>{{ inr(invoice.amount_paid) }}</span></div>
          <div v-if="invoice.amount_due > 0" class="flex justify-between font-bold text-red-600 border-t border-gray-300 pt-1">
            <span>Balance Due</span><span>{{ inr(invoice.amount_due) }}</span>
          </div>
        </div>
      </div>

      <!-- Bank + Notes -->
      <div class="grid grid-cols-2 gap-6 mb-6">
        <div v-if="business?.bank_name || business?.upi_id">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Payment Details</p>
          <div class="space-y-0.5 text-xs">
            <div v-if="business.bank_name" class="flex gap-2"><span class="text-gray-400 w-16 shrink-0">Bank</span><span>{{ business.bank_name }}</span></div>
            <div v-if="business.bank_account_no" class="flex gap-2"><span class="text-gray-400 w-16 shrink-0">A/C No</span><span class="font-mono">{{ business.bank_account_no }}</span></div>
            <div v-if="business.bank_ifsc" class="flex gap-2"><span class="text-gray-400 w-16 shrink-0">IFSC</span><span class="font-mono">{{ business.bank_ifsc }}</span></div>
            <div v-if="business.upi_id" class="flex gap-2"><span class="text-gray-400 w-16 shrink-0">UPI ID</span><span class="font-mono">{{ business.upi_id }}</span></div>
          </div>
        </div>
        <div>
          <div v-if="invoice.notes || business?.invoice_notes" class="mb-2">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-0.5">Notes</p>
            <p class="text-xs text-gray-600">{{ invoice.notes || business?.invoice_notes }}</p>
          </div>
          <div v-if="invoice.terms || business?.invoice_terms">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-0.5">Terms</p>
            <p class="text-xs text-gray-600">{{ invoice.terms || business?.invoice_terms }}</p>
          </div>
        </div>
      </div>

      <!-- Signature -->
      <div class="grid grid-cols-2 gap-8 mt-8">
        <div class="text-center"><div class="border-t border-gray-400 pt-2 mt-12"><p class="text-xs font-semibold text-gray-500">Customer Signature</p></div></div>
        <div class="text-center"><div class="border-t border-gray-400 pt-2 mt-12"><p class="text-xs font-semibold text-gray-500">Authorised Signatory</p></div></div>
      </div>

    </div>
  </div>
</template>

<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: white; color: #111; }
.print-page { max-width: 900px; margin: 0 auto; padding: 20px; }
.invoice-doc { background: white; }
@media print {
  .print-page { padding: 0; max-width: 100%; }
  body { margin: 0; }
}
.flex { display: flex; }
.grid { display: grid; }
.grid-cols-2 { grid-template-columns: 1fr 1fr; }
.gap-4 { gap: 1rem; }
.gap-6 { gap: 1.5rem; }
.gap-3 { gap: 0.75rem; }
.gap-2 { gap: 0.5rem; }
.gap-8 { gap: 2rem; }
.items-center { align-items: center; }
.items-start { align-items: flex-start; }
.justify-between { justify-content: space-between; }
.text-left { text-align: left; }
.text-right { text-align: right; }
.text-center { text-align: center; }
.font-mono { font-family: monospace; }
.font-bold { font-weight: 700; }
.font-semibold { font-weight: 600; }
.font-medium { font-weight: 500; }
.font-black { font-weight: 900; }
.text-xs { font-size: 0.75rem; }
.text-sm { font-size: 0.875rem; }
.text-base { font-size: 1rem; }
.text-2xl { font-size: 1.5rem; }
.uppercase { text-transform: uppercase; }
.tracking-widest { letter-spacing: 0.1em; }
.italic { font-style: italic; }
.min-h-screen { min-height: 100vh; }
.shrink-0 { flex-shrink: 0; }
.flex-1 { flex: 1; }
.w-7 { width: 1.75rem; }
.w-8 { width: 2rem; }
.w-12 { width: 3rem; }
.w-16 { width: 4rem; }
.w-52 { width: 13rem; }
.h-8 { height: 2rem; }
.h-12 { height: 3rem; }
.mt-1 { margin-top: 0.25rem; }
.mt-2 { margin-top: 0.5rem; }
.mt-4 { margin-top: 1rem; }
.mt-8 { margin-top: 2rem; }
.mt-12 { margin-top: 3rem; }
.mb-1 { margin-bottom: 0.25rem; }
.mb-2 { margin-bottom: 0.5rem; }
.mb-4 { margin-bottom: 1rem; }
.mb-6 { margin-bottom: 1.5rem; }
.p-8 { padding: 2rem; }
.px-2 { padding-left: 0.5rem; padding-right: 0.5rem; }
.py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
.pb-3 { padding-bottom: 0.75rem; }
.pb-4 { padding-bottom: 1rem; }
.pt-1 { padding-top: 0.25rem; }
.pt-2 { padding-top: 0.5rem; }
.pr-3 { padding-right: 0.75rem; }
.pb-1 { padding-bottom: 0.25rem; }
.space-y-0\.5 > * + * { margin-top: 0.125rem; }
.space-y-1 > * + * { margin-top: 0.25rem; }
.border-b { border-bottom: 1px solid; }
.border-b-2 { border-bottom: 2px solid; }
.border-t { border-top: 1px solid; }
.border-gray-100 { border-color: #f3f4f6; }
.border-gray-200 { border-color: #e5e7eb; }
.border-gray-300 { border-color: #d1d5db; }
.border-gray-400 { border-color: #9ca3af; }
.border-gray-800 { border-color: #1f2937; }
.bg-gray-800 { background-color: #1f2937; }
.text-white { color: white; }
.text-gray-400 { color: #9ca3af; }
.text-gray-500 { color: #6b7280; }
.text-gray-600 { color: #4b5563; }
.text-gray-700 { color: #374151; }
.text-gray-800 { color: #1f2937; }
.text-gray-900 { color: #111827; }
.text-blue-800 { color: #1e40af; }
.text-green-700 { color: #15803d; }
.text-red-500 { color: #ef4444; }
.text-red-600 { color: #dc2626; }
.rounded-lg { border-radius: 0.5rem; }
.border { border: 1px solid #e5e7eb; }
.object-contain { object-fit: contain; }
.border-collapse { border-collapse: collapse; }
table { width: 100%; border-collapse: collapse; }
.animate-spin { animation: spin 1s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
