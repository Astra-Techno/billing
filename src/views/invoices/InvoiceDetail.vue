<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api, { item, list, task } from '../../api'
import { inr } from '../../utils/currency'
import { fmtDateShort, today } from '../../utils/date'
import { statusBadge, statusLabel } from '../../utils/invoice'

const props = defineProps({ panelId: { type: [String, Number], default: null } })
const emit  = defineEmits(['back', 'refresh'])

const route  = useRoute()
const router = useRouter()

const invoice  = ref(null)
const business = ref(null)
const items    = ref([])
const payments = ref([])
const loading  = ref(true)

const showPayModal    = ref(false)
const showCancelModal = ref(false)
const payForm  = ref({ amount: '', method: 'upi', reference: '', payment_date: today() })
const payError = ref('')
const paying   = ref(false)
const acting   = ref(false)

async function load() {
  loading.value = true
  const id = props.panelId || route.params.id
  try {
    const [invRes, itmRes, payRes, bizRes] = await Promise.all([
      item('Invoice', { id }),
      list('Invoice:items', { invoice_id: id }),
      list('Invoice:payments', { invoice_id: id }),
      item('Business'),
    ])
    invoice.value  = invRes.data?.data
    items.value    = itmRes.data?.data  || []
    payments.value = payRes.data?.data  || []
    business.value = bizRes.data?.data  || null
    if (invoice.value) payForm.value.amount = invoice.value.amount_due
  } catch {}
  loading.value = false
}

async function markSent() {
  acting.value = true
  try {
    await task('Invoice', 'markSent', { id: invoice.value.id })
    emit('refresh')
    await load()
  } finally { acting.value = false }
}

async function recordPayment() {
  payError.value = ''
  paying.value   = true
  try {
    await task('Payment', 'record', {
      invoice_id:   invoice.value.id,
      amount:       payForm.value.amount,
      method:       payForm.value.method,
      reference:    payForm.value.reference,
      payment_date: payForm.value.payment_date,
    })
    showPayModal.value = false
    emit('refresh')
    await load()
  } catch (e) {
    payError.value = e.response?.data?.message || 'Payment failed.'
  } finally { paying.value = false }
}

async function cancelInvoice() {
  acting.value = true
  try {
    await task('Invoice', 'cancel', { id: invoice.value.id })
    showCancelModal.value = false
    emit('refresh')
    await load()
  } finally { acting.value = false }
}

function printInvoice() {
  window.print()
}

const invoiceTitle = computed(() => {
  const map = { tax_invoice: 'Tax Invoice', bill_of_supply: 'Bill of Supply', retail: 'Retail Invoice', export: 'Export Invoice' }
  return map[invoice.value?.invoice_type] || 'Tax Invoice'
})

const isGst = computed(() => invoice.value?.invoice_type !== 'bill_of_supply')

const downloading = ref(false)
async function downloadPdf() {
  downloading.value = true
  try {
    const res = await api.post('task/Invoice/pdf', { id: invoice.value.id }, { responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
    const a = Object.assign(document.createElement('a'), { href: url, download: `${invoice.value.number}.pdf` })
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    // Backend PDF not available — fallback to browser print
    window.print()
  } finally { downloading.value = false }
}

function shareWhatsApp() {
  const inv = invoice.value
  const due  = parseFloat(inv.amount_due  || 0)
  const paid = parseFloat(inv.amount_paid || 0)
  let msg = `Dear ${inv.client_name},\n\nInvoice *${inv.number}* — *${inr(inv.total)}*`
  if (due <= 0) {
    msg += `\n\n✅ Paid in full. Thank you!`
  } else if (paid > 0) {
    msg += `\n\nPaid: ${inr(paid)} | *Balance Due: ${inr(due)}*\nDue by: ${fmtDateShort(inv.due_date)}`
  } else {
    msg += `\nDue by: *${fmtDateShort(inv.due_date)}*`
  }
  if (business.value?.upi_id) msg += `\n\nPay via UPI: *${business.value.upi_id}*`
  msg += `\n\nThank you for your business!`
  window.open('https://wa.me/?text=' + encodeURIComponent(msg), '_blank')
}

function shareEmail() {
  const inv = invoice.value
  const due = parseFloat(inv.amount_due || 0)
  const subject = `Invoice ${inv.number} — ${inr(inv.total)}`
  const lines = [
    `Dear ${inv.client_name},`,
    ``,
    `Please find the details of Invoice ${inv.number}.`,
    ``,
    `Invoice No  : ${inv.number}`,
    `Invoice Date: ${fmtDateShort(inv.issue_date)}`,
    `Due Date    : ${fmtDateShort(inv.due_date)}`,
    `Total Amount: ${inr(inv.total)}`,
    due > 0 ? `Balance Due : ${inr(due)}` : `Status      : PAID`,
    business.value?.upi_id ? `\nPay via UPI : ${business.value.upi_id}` : ``,
    ``,
    `Thank you for your business!`,
    business.value?.name ? `\n— ${business.value.name}` : ``,
  ]
  window.location.href = `mailto:${inv.client_email || ''}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(lines.join('\n'))}`
}

const methods = [
  { value: 'upi',        label: 'UPI' },
  { value: 'cash',       label: 'Cash' },
  { value: 'neft',       label: 'NEFT / RTGS' },
  { value: 'cheque',     label: 'Cheque' },
  { value: 'netbanking', label: 'Net Banking' },
  { value: 'card',       label: 'Card' },
  { value: 'imps',       label: 'IMPS' },
  { value: 'other',      label: 'Other' },
]

function amountInWords(amount) {
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
  const n = Math.round(+amount || 0)
  const p = Math.round((( +amount || 0) - Math.floor(+amount || 0)) * 100)
  let w = (toW(n) || 'Zero ').trim() + ' Rupees'
  if (p > 0) w += ' and ' + toW(p).trim() + ' Paise'
  return w + ' Only'
}

watch(() => props.panelId, v => { if (v) load() })
onMounted(load)
</script>

<template>
  <div class="max-w-3xl mx-auto space-y-5">

    <!-- Back Navigation -->
    <div v-if="!panelId" class="flex items-center gap-3 pt-2">
      <button @click="router.push('/invoices')" class="p-2 -ml-2 rounded-full hover:bg-gray-100 transition-colors">
        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
      </button>
    </div>

    <div v-if="loading" class="flex justify-center p-12">
      <div class="w-8 h-8 border-4 border-primary-100 border-t-primary-600 rounded-full animate-spin"></div>
    </div>

    <template v-else-if="invoice">

      <!-- HERO: Massive Total and Status -->
      <div class="flex flex-col items-center justify-center text-center animate-fade-in-up mt-4 mb-2">
        <div class="w-16 h-16 rounded-full bg-blue-50 text-primary-600 flex items-center justify-center mb-3">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </div>
        <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">{{ invoice.client_name }}</p>
        <h1 class="text-5xl font-extrabold tracking-tight text-gray-900">{{ inr(invoice.total) }}</h1>
        <div class="flex items-center gap-2 mt-3">
          <p class="text-sm font-semibold text-gray-600">Bill {{ invoice.number }}</p>
          <span :class="statusBadge(invoice.status)" class="text-[10px] px-2 py-0.5">{{ statusLabel(invoice.status) }}</span>
        </div>
        <p v-if="invoice.amount_due > 0 && invoice.status !== 'paid'" class="text-danger-600 font-bold mt-3 bg-danger-50 px-4 py-1.5 rounded-full text-sm tracking-wide">
          Balance: {{ inr(invoice.amount_due) }}
        </p>
      </div>

      <!-- Action Pills -->
      <div class="flex flex-wrap justify-center gap-2 w-full max-w-lg mx-auto animate-fade-in-up delay-75 mb-6" v-if="invoice.status !== 'cancelled'">
        
        <button v-if="invoice.status === 'draft'" @click="markSent" :disabled="acting" class="flex-1 min-w-[120px] btn bg-primary-600 text-white hover:bg-primary-700 shadow-soft-blue flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
          <span class="text-xs">Mark Sent</span>
        </button>

        <button v-if="['sent','partial','overdue'].includes(invoice.status)" @click="showPayModal = true" class="flex-1 min-w-[120px] btn bg-emerald-600 text-white hover:bg-emerald-700 shadow-soft flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <span class="text-xs">Pay</span>
        </button>

        <button @click="shareWhatsApp" class="flex-1 min-w-[80px] btn bg-green-50 text-green-700 border border-green-100 hover:bg-green-100 shadow-soft flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6 text-green-600" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.137.565 4.147 1.554 5.887L0 24l6.305-1.524A11.94 11.94 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.818 9.818 0 01-5.006-1.375l-.359-.214-3.735.902.948-3.632-.234-.373A9.818 9.818 0 1112 21.818z"/></svg>
          <span class="text-xs">Share</span>
        </button>

        <button @click="shareEmail" class="flex-1 min-w-[80px] btn bg-sky-50 text-sky-700 border border-sky-100 hover:bg-sky-100 shadow-soft flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6 text-sky-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
          <span class="text-xs">Email</span>
        </button>

        <button @click="printInvoice" class="flex-1 min-w-[80px] btn bg-gray-50 text-gray-800 border border-gray-100 hover:bg-gray-100 shadow-soft flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
          <span class="text-xs">Print</span>
        </button>

        <button @click="downloadPdf" :disabled="downloading" class="flex-1 min-w-[80px] btn bg-red-50 text-red-700 border border-red-100 hover:bg-red-100 shadow-soft flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg v-if="downloading" class="w-6 h-6 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
          <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
          <span class="text-xs">{{ downloading ? '…' : 'PDF' }}</span>
        </button>
        
        <RouterLink v-if="invoice.status !== 'cancelled'" :to="`/invoices/${invoice.id}/edit`" class="flex-1 min-w-[80px] btn bg-gray-50 text-gray-800 border border-gray-100 hover:bg-gray-100 shadow-soft flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
          <span class="text-xs">Edit</span>
        </RouterLink>

        <button v-if="invoice.status !== 'paid'" @click="showCancelModal = true" class="flex-1 min-w-[80px] btn bg-red-50 text-red-600 border border-red-100 hover:bg-red-100 shadow-soft flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
          <span class="text-xs">Cancel</span>
        </button>

      </div>

      <!-- Invoice body — proper Indian GST Tax Invoice -->
      <div class="bg-white rounded-[2rem] shadow-soft border-0 overflow-hidden animate-fade-in-up delay-150">

        <!-- Top: TAX INVOICE title row + Business info row -->
        <div class="px-5 pt-5 pb-4 border-b border-gray-200 space-y-4">
          <!-- Title row -->
          <div class="flex items-center justify-between gap-2">
            <p class="text-xl sm:text-2xl font-black text-primary-700 uppercase tracking-widest leading-none">{{ invoiceTitle }}</p>
            <p class="text-sm font-bold text-gray-700 shrink-0">{{ invoice.number }}</p>
          </div>
          <!-- Business info row -->
          <div class="flex items-start gap-3">
            <img v-if="business?.logo" :src="business.logo" class="w-12 h-12 object-contain rounded-xl border border-gray-100 shrink-0" alt="logo" />
            <div class="min-w-0">
              <p class="text-base font-bold text-gray-900 leading-tight">{{ business?.name || invoice.business_name }}</p>
              <p v-if="business?.address_line1" class="text-xs text-gray-500 mt-0.5">{{ business.address_line1 }}<span v-if="business.address_line2">, {{ business.address_line2 }}</span></p>
              <p v-if="business?.city || business?.state_name" class="text-xs text-gray-500">
                {{ [business?.city, business?.state_name, business?.pincode].filter(Boolean).join(', ') }}
              </p>
              <p v-if="business?.mobile || business?.email" class="text-xs text-gray-500 mt-0.5 truncate">
                {{ [business?.mobile, business?.email].filter(Boolean).join(' · ') }}
              </p>
              <div class="flex flex-wrap gap-x-3 mt-1">
                <p v-if="business?.gstin || invoice.business_gstin" class="text-[11px] text-gray-500 font-mono">GSTIN: {{ business?.gstin || invoice.business_gstin }}</p>
                <p v-if="business?.pan" class="text-[11px] text-gray-500 font-mono">PAN: {{ business.pan }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Bill To (left) + Invoice Details (right) -->
        <div class="grid grid-cols-2 border-b border-gray-200">
          <!-- Bill To -->
          <div class="px-5 py-4 border-r border-gray-200">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Bill To</p>
            <p class="font-bold text-gray-900 text-sm">{{ invoice.client_name }}</p>
            <p v-if="invoice.client_company" class="text-xs text-gray-600">{{ invoice.client_company }}</p>
            <p v-if="invoice.client_address1" class="text-xs text-gray-600 mt-0.5">{{ invoice.client_address1 }}<span v-if="invoice.client_address2">, {{ invoice.client_address2 }}</span></p>
            <p v-if="invoice.client_city || invoice.client_pincode" class="text-xs text-gray-600">
              {{ [invoice.client_city, invoice.client_pincode].filter(Boolean).join(' – ') }}
            </p>
            <div class="mt-1.5 space-y-0.5">
              <p v-if="invoice.client_gstin" class="text-[11px] text-gray-500 font-mono">GSTIN: {{ invoice.client_gstin }}</p>
              <p v-if="invoice.client_mobile" class="text-[11px] text-gray-500">Mob: {{ invoice.client_mobile }}</p>
              <p v-if="invoice.client_email" class="text-[11px] text-gray-500">{{ invoice.client_email }}</p>
            </div>
          </div>
          <!-- Invoice Details -->
          <div class="px-5 py-4">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Invoice Details</p>
            <table class="text-xs w-full">
              <tr><td class="text-gray-400 pb-1 pr-3">Invoice No</td><td class="font-semibold text-gray-800 pb-1">{{ invoice.number }}</td></tr>
              <tr><td class="text-gray-400 pb-1 pr-3">Invoice Date</td><td class="font-medium text-gray-700 pb-1">{{ fmtDateShort(invoice.issue_date) }}</td></tr>
              <tr><td class="text-gray-400 pb-1 pr-3">Due Date</td><td class="font-medium text-gray-700 pb-1">{{ fmtDateShort(invoice.due_date) }}</td></tr>
              <tr><td class="text-gray-400 pr-3">Place of Supply</td><td class="font-medium text-gray-700">{{ invoice.place_of_supply_name || invoice.supply_type }}</td></tr>
            </table>
          </div>
        </div>

        <!-- Items table -->
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-800 text-white">
              <tr>
                <th class="px-3 py-2.5 text-left text-xs font-semibold w-7">#</th>
                <th class="px-3 py-2.5 text-left text-xs font-semibold">Description</th>
                <th class="px-3 py-2.5 text-center text-xs font-semibold">HSN/SAC</th>
                <th class="px-3 py-2.5 text-right text-xs font-semibold">Qty</th>
                <th class="px-3 py-2.5 text-right text-xs font-semibold">Rate</th>
                <th class="px-3 py-2.5 text-right text-xs font-semibold">Taxable</th>
                <th v-if="isGst" class="px-3 py-2.5 text-right text-xs font-semibold">Tax</th>
                <th class="px-3 py-2.5 text-right text-xs font-semibold">Amount</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="(it, idx) in items" :key="it.id">
                <td class="px-3 py-3 text-gray-400 text-xs">{{ idx + 1 }}</td>
                <td class="px-3 py-3">
                  <p class="font-medium text-gray-800">{{ it.description }}</p>
                  <p v-if="it.unit" class="text-xs text-gray-400">{{ it.unit }}</p>
                </td>
                <td class="px-3 py-3 text-center font-mono text-xs text-gray-500">{{ it.hsn_sac || '—' }}</td>
                <td class="px-3 py-3 text-right text-gray-700">{{ it.quantity }}</td>
                <td class="px-3 py-3 text-right text-gray-700">{{ inr(it.unit_price) }}</td>
                <td class="px-3 py-3 text-right text-gray-700">{{ inr(it.taxable_amt) }}</td>
                <td v-if="isGst" class="px-3 py-3 text-right text-xs">
                  <div v-if="it.cgst_amt > 0" class="space-y-0.5">
                    <div class="text-gray-600">CGST {{ it.cgst_rate }}%: {{ inr(it.cgst_amt) }}</div>
                    <div class="text-gray-600">SGST {{ it.sgst_rate }}%: {{ inr(it.sgst_amt) }}</div>
                  </div>
                  <div v-else-if="it.igst_amt > 0" class="text-gray-600">IGST {{ it.igst_rate }}%: {{ inr(it.igst_amt) }}</div>
                  <div v-else class="text-gray-400">Nil</div>
                </td>
                <td class="px-3 py-3 text-right font-semibold text-gray-900">{{ inr(it.total) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Totals + Amount in words -->
        <div class="p-5 border-t border-gray-200 flex flex-col sm:flex-row gap-6">
          <!-- Amount in words -->
          <div class="flex-1">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Amount in Words</p>
            <p class="text-sm font-medium text-gray-700 italic">{{ amountInWords(invoice.total) }}</p>
          </div>
          <!-- Totals -->
          <div class="sm:w-64 space-y-1.5 text-sm">
            <div class="flex justify-between text-gray-600"><span>Subtotal</span><span>{{ inr(invoice.subtotal) }}</span></div>
            <div v-if="invoice.cgst_total > 0" class="flex justify-between text-gray-600"><span>CGST</span><span>{{ inr(invoice.cgst_total) }}</span></div>
            <div v-if="invoice.sgst_total > 0" class="flex justify-between text-gray-600"><span>SGST</span><span>{{ inr(invoice.sgst_total) }}</span></div>
            <div v-if="invoice.igst_total > 0" class="flex justify-between text-gray-600"><span>IGST</span><span>{{ inr(invoice.igst_total) }}</span></div>
            <div v-if="invoice.discount > 0" class="flex justify-between text-danger-500"><span>Discount</span><span>-{{ inr(invoice.discount) }}</span></div>
            <div v-if="invoice.round_off != 0" class="flex justify-between text-gray-400"><span>Round Off</span><span>{{ inr(invoice.round_off) }}</span></div>
            <div class="flex justify-between font-bold text-base text-gray-900 border-t border-gray-200 pt-2">
              <span>Total</span><span>{{ inr(invoice.total) }}</span>
            </div>
            <div v-if="invoice.amount_paid > 0" class="flex justify-between text-success-700"><span>Amount Paid</span><span>{{ inr(invoice.amount_paid) }}</span></div>
            <div v-if="invoice.amount_due > 0" class="flex justify-between font-bold text-danger-600 border-t border-gray-200 pt-2">
              <span>Balance Due</span><span>{{ inr(invoice.amount_due) }}</span>
            </div>
          </div>
        </div>

        <!-- Bank details + Notes/Terms -->
        <div class="px-5 pb-5 border-t border-gray-100 pt-4 grid sm:grid-cols-2 gap-6">
          <div v-if="business?.bank_name || business?.upi_id">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Payment Details</p>
            <div class="space-y-1 text-sm">
              <div v-if="business.bank_name" class="flex gap-2"><span class="text-gray-400 w-20 shrink-0">Bank</span><span class="text-gray-700">{{ business.bank_name }}</span></div>
              <div v-if="business.bank_account_no" class="flex gap-2"><span class="text-gray-400 w-20 shrink-0">A/C No</span><span class="font-mono text-gray-700">{{ business.bank_account_no }}</span></div>
              <div v-if="business.bank_ifsc" class="flex gap-2"><span class="text-gray-400 w-20 shrink-0">IFSC</span><span class="font-mono text-gray-700">{{ business.bank_ifsc }}</span></div>
              <div v-if="business.bank_account_name" class="flex gap-2"><span class="text-gray-400 w-20 shrink-0">Name</span><span class="text-gray-700">{{ business.bank_account_name }}</span></div>
              <div v-if="business.upi_id" class="flex gap-2"><span class="text-gray-400 w-20 shrink-0">UPI ID</span><span class="font-mono text-gray-700">{{ business.upi_id }}</span></div>
            </div>
          </div>
          <div class="space-y-3">
            <div v-if="invoice.notes || business?.invoice_notes">
              <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Notes</p>
              <p class="text-sm text-gray-600">{{ invoice.notes || business?.invoice_notes }}</p>
            </div>
            <div v-if="invoice.terms || business?.invoice_terms">
              <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Terms & Conditions</p>
              <p class="text-sm text-gray-600">{{ invoice.terms || business?.invoice_terms }}</p>
            </div>
          </div>
        </div>

        <!-- Authorized Signatory -->
        <div class="px-5 py-4 border-t border-gray-100 flex justify-end">
          <div class="text-center min-w-[180px]">
            <p class="text-xs text-gray-500 mb-12">For {{ business?.name || invoice.business_name }}</p>
            <div class="border-t border-gray-400 pt-1">
              <p class="text-xs text-gray-500">Authorized Signatory</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Payment history -->
      <div v-if="payments.length" class="mt-8 animate-fade-in-up delay-200">
        <h2 class="font-bold text-gray-800 text-sm mb-4 px-1">Payment History</h2>
        <div class="bg-white rounded-[2rem] shadow-soft border-0 overflow-hidden divide-y divide-gray-50">
          <div v-for="p in payments" :key="p.id" class="flex items-center justify-between px-5 py-3">
            <div>
              <p class="text-sm font-medium text-gray-800">{{ p.method?.toUpperCase() }}</p>
              <p class="text-xs text-gray-400">{{ fmtDateShort(p.payment_date) }}{{ p.reference ? ' · ' + p.reference : '' }}</p>
            </div>
            <p class="font-semibold text-success-700">{{ inr(p.amount) }}</p>
          </div>
        </div>
      </div>

    </template>

    <!-- Record Payment Modal -->
    <div v-if="showPayModal" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/40">
      <div class="bg-white rounded-2xl w-full max-w-md shadow-xl">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <h3 class="font-semibold text-gray-800">Record Payment</h3>
          <button @click="showPayModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
        <div class="p-5 space-y-4">
          <div>
            <label class="form-label">Amount (₹) *</label>
            <input v-model="payForm.amount" type="number" min="0.01" step="0.01" class="form-input text-lg font-semibold" />
          </div>
          <div>
            <label class="form-label">Payment Method *</label>
            <div class="grid grid-cols-3 gap-2">
              <button v-for="m in methods" :key="m.value" type="button"
                @click="payForm.method = m.value"
                class="py-2 rounded-lg text-xs font-medium border transition"
                :class="payForm.method === m.value ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'">
                {{ m.label }}
              </button>
            </div>
          </div>
          <div>
            <label class="form-label">Reference / UTR / Cheque No</label>
            <input v-model="payForm.reference" type="text" class="form-input" placeholder="Optional" />
          </div>
          <div>
            <label class="form-label">Payment Date *</label>
            <input v-model="payForm.payment_date" type="date" class="form-input" />
          </div>
          <div v-if="payError" class="text-sm text-danger-500 bg-danger-50 rounded-lg px-3 py-2">{{ payError }}</div>
        </div>
        <div class="px-5 pb-5 flex gap-3">
          <button @click="showPayModal = false" class="btn-outline flex-1">Cancel</button>
          <button @click="recordPayment" :disabled="paying" class="btn-primary flex-1">
            {{ paying ? 'Recording…' : 'Record Payment' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Cancel confirmation -->
    <div v-if="showCancelModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
      <div class="bg-white rounded-2xl w-full max-w-sm shadow-xl p-6 text-center">
        <div class="w-12 h-12 rounded-full bg-danger-50 flex items-center justify-center mx-auto mb-4">
          <svg class="w-6 h-6 text-danger-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </div>
        <h3 class="font-semibold text-gray-800 mb-2">Cancel Bill?</h3>
        <p class="text-sm text-gray-500 mb-5">This cannot be undone. The bill will be marked as cancelled.</p>
        <div class="flex gap-3">
          <button @click="showCancelModal = false" class="btn-outline flex-1">Keep It</button>
          <button @click="cancelInvoice" :disabled="acting" class="btn-danger flex-1">Yes, Cancel</button>
        </div>
      </div>
    </div>

  </div>
</template>

<style>
@media print {
  header, nav, footer,
  .btn-primary, .btn-outline, button,
  [class*="fixed"], [class*="sticky"],
  #payment-history { display: none !important; }

  body, html { background: white !important; margin: 0; }
  .max-w-3xl { max-width: 100% !important; padding: 0 !important; }

  /* Hide status bar and action row — only print the invoice card */
  .max-w-3xl > div:first-child,       /* back button */
  .max-w-3xl > div:nth-child(2),      /* status bar */
  .max-w-3xl > div:nth-child(3) { display: none !important; }   /* action buttons */

  .card {
    box-shadow: none !important;
    border: 1px solid #d1d5db !important;
    border-radius: 0 !important;
    page-break-inside: avoid;
  }
  thead { background-color: #1f2937 !important; color: white !important; }
  * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
}
</style>
