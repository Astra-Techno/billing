<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api, { item, list, task } from '../../api'
import { inr } from '../../utils/currency'
import { fmtDateShort, today } from '../../utils/date'
import { statusBadge, statusLabel } from '../../utils/invoice'
import QRCode from 'qrcode'

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
const upiQrDataUrl = ref('')

// E-way Bill
const showEwbModal  = ref(false)
const ewb           = ref(null)   // existing EWB for this invoice
const ewbGenerating = ref(false)
const ewbError      = ref('')
const ewbForm       = ref({ mode: 'road', distance: '', transporter: '', vehicle_no: '', vehicle_type: 'Regular' })

function setQuickAmount(pct) {
  const due = parseFloat(invoice.value?.amount_due || 0)
  payForm.value.amount = pct === 1 ? due.toFixed(2) : (Math.round(due * pct * 100) / 100).toFixed(2)
}

async function load() {
  loading.value = true
  const id = props.panelId || route.params.id
  try {
    const [invRes, itmRes, payRes, bizRes, ewbRes] = await Promise.all([
      item('Invoice', { id }),
      list('Invoice:items', { invoice_id: id }),
      list('Invoice:payments', { invoice_id: id }),
      item('Business'),
      list('EwayBill', { 'filter.invoice_id': id, sort_by: 'created_at', sort_order: 'desc' }).catch(() => null),
    ])
    invoice.value  = invRes.data?.data
    items.value    = itmRes.data?.data  || []
    payments.value = payRes.data?.data  || []
    business.value = bizRes.data?.data  || null
    ewb.value      = ewbRes?.data?.data?.[0] || null
    if (invoice.value) payForm.value.amount = invoice.value.amount_due

    // Generate UPI QR for collect payment card
    const upiId = business.value?.upi_id
    if (upiId && invoice.value?.amount_due > 0) {
      const upiStr = `upi://pay?pa=${encodeURIComponent(upiId)}&pn=${encodeURIComponent(business.value?.name || '')}&am=${parseFloat(invoice.value.amount_due).toFixed(2)}&cu=INR&tn=${encodeURIComponent(invoice.value.number)}`
      try { upiQrDataUrl.value = await QRCode.toDataURL(upiStr, { width: 140, margin: 1 }) } catch {}
    }
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
  window.open('/print/invoice/' + invoice.value.id, '_blank')
}

function printDeliveryChallan() {
  window.open('/print/invoice/' + invoice.value.id + '?mode=dc', '_blank')
}

function printProforma() {
  window.open('/print/invoice/' + invoice.value.id + '?mode=proforma', '_blank')
}

const invoiceTitle = computed(() => {
  const map = { tax_invoice: 'Tax Invoice', bill_of_supply: 'Bill of Supply', retail: 'Retail Invoice', export: 'Export Invoice', proforma: 'Proforma Invoice' }
  return map[invoice.value?.invoice_type] || 'Tax Invoice'
})

const isGst = computed(() => invoice.value?.invoice_type !== 'bill_of_supply')

const downloading = ref(false)
async function downloadPdf(mode = '') {
  downloading.value = true
  try {
    const modeParam = mode ? `?mode=${mode}` : ''
    const res = await api.get(`invoice/${invoice.value.id}/pdf${modeParam}`, { responseType: 'blob' })
    const prefix = mode === 'dc' ? 'DC-' : mode === 'proforma' ? 'PROFORMA-' : ''
    const url = URL.createObjectURL(new Blob([res.data], { type: 'application/pdf' }))
    const a = Object.assign(document.createElement('a'), { href: url, download: `${prefix}${invoice.value.number}.pdf` })
    a.click()
    URL.revokeObjectURL(url)
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

// EWB validity: NIC rules — distance → days
function ewbValidityDays(km) {
  const d = parseFloat(km) || 0
  if (d <= 0)    return null
  if (d <= 100)  return 1
  if (d <= 300)  return 3
  if (d <= 500)  return 5
  if (d <= 1000) return 7
  return 15
}
function ewbValidUntil(km) {
  const days = ewbValidityDays(km)
  if (!days) return null
  const d = new Date()
  d.setDate(d.getDate() + days)
  d.setHours(23, 59, 0, 0)
  return fmtDateShort(d.toISOString().split('T')[0]) + ', 11:59 PM'
}

async function generateEwb() {
  ewbError.value = ''
  if (!ewbForm.value.distance) return (ewbError.value = 'Please enter the distance in km.')
  if (!ewbForm.value.vehicle_no.trim()) return (ewbError.value = 'Vehicle number is required.')
  ewbGenerating.value = true
  try {
    const res = await task('EwayBill', 'create', {
      invoice_id:   invoice.value.id,
      mode:         ewbForm.value.mode,
      distance:     parseFloat(ewbForm.value.distance),
      transporter:  ewbForm.value.transporter,
      vehicle_no:   ewbForm.value.vehicle_no.toUpperCase(),
      vehicle_type: ewbForm.value.vehicle_type,
    })
    ewb.value = res.data?.data
    showEwbModal.value = false
    ewbForm.value = { mode: 'road', distance: '', transporter: '', vehicle_no: '', vehicle_type: 'Regular' }
  } catch (e) {
    ewbError.value = e.response?.data?.message || 'Failed to generate E-way Bill. Please try again.'
  }
  ewbGenerating.value = false
}

async function cancelEwb() {
  if (!confirm('Cancel this E-way Bill? This cannot be undone.')) return
  try {
    await task('EwayBill', 'cancel', { id: ewb.value.id })
    ewb.value = { ...ewb.value, status: 'cancelled' }
  } catch (e) {
    alert(e.response?.data?.message || 'Failed to cancel E-way Bill.')
  }
}

watch(() => props.panelId, v => { if (v) load() })
onMounted(load)
</script>

<template>
  <div class="inv-detail-page gpay-screen px-4 py-4 lg:px-6 lg:py-6 max-w-4xl lg:mx-auto space-y-4 pt-2 sm:pt-4 pb-24">

    <!-- Sticky Header: Invozen mockup style on mobile -->
    <div v-if="invoice" class="lg:hidden sticky top-0 z-30 bg-[#1a56db] text-white flex items-center justify-between gap-3 px-4 py-3 shrink-0 shadow-sm -mx-4 -mt-4 mb-4">
      <div class="flex items-center gap-3">
        <button type="button" @click="router.back()" class="w-8 h-8 flex items-center justify-center rounded-lg bg-white/10 text-white">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <span class="text-base font-semibold">Invoice Preview</span>
      </div>
      <RouterLink :to="`/invoices/${invoice.id}/edit`" class="w-8 h-8 flex items-center justify-center rounded-lg bg-white/10 text-white">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
      </RouterLink>
    </div>

    <div v-if="loading" class="flex justify-center p-12">
      <div class="w-8 h-8 border-4 border-primary-100 border-t-primary-600 rounded-full animate-spin"></div>
    </div>

    <template v-else-if="invoice">

      <!-- Success Badge (Invozen Preview Mockup Style) -->
      <div v-if="route.query.newly_created === 'true'" class="w-full bg-white rounded-[2rem] border border-gray-100 p-6 text-center space-y-4 shadow-sm animate-fade-in-up">
        <!-- checkmark in dashed circle -->
        <div class="relative w-16 h-16 mx-auto flex items-center justify-center">
          <!-- dotted outer ring -->
          <div class="absolute inset-0 rounded-full border-2 border-dashed border-emerald-400 animate-spin" style="animation-duration: 20s;"></div>
          <!-- inner green circle with check -->
          <div class="w-12 h-12 rounded-full bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-500 shadow-sm">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          </div>
        </div>
        <div>
          <h2 class="text-lg font-black text-gray-900 tracking-tight">Your Invoice Is Ready</h2>
          <p class="text-xs text-gray-400 mt-1">Print the invoice or send it to your customer</p>
        </div>
      </div>

      <!-- Summary + actions (desktop) -->
      <div class="inv-detail-shell bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden animate-fade-in-up">
        <div class="px-5 sm:px-6 py-5 flex flex-col lg:flex-row lg:items-start lg:justify-between gap-5">
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2 mb-2">
              <span class="text-xs font-medium text-gray-500 tracking-wide">{{ invoice.number }}</span>
              <span :class="statusBadge(invoice.status)" class="px-2 py-0.5 text-[11px] font-semibold rounded-md">{{ statusLabel(invoice.status) }}</span>
            </div>
            <RouterLink v-if="invoice.client_id" :to="`/clients/${invoice.client_id}`"
              class="text-xl sm:text-2xl font-semibold text-gray-900 hover:text-primary-600 transition-colors leading-snug block">
              {{ invoice.client_name }}
            </RouterLink>
            <h1 v-else class="text-xl sm:text-2xl font-semibold text-gray-900 leading-snug">{{ invoice.client_name }}</h1>
            <p class="text-sm text-gray-500 mt-1.5">
              Issued {{ fmtDateShort(invoice.issue_date) }}
              <span class="text-gray-300 mx-1">·</span>
              Due {{ fmtDateShort(invoice.due_date) }}
            </p>
          </div>

          <div class="lg:text-right shrink-0 lg:pl-6 lg:border-l lg:border-gray-100">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total amount</p>
            <p class="text-2xl sm:text-3xl font-bold text-gray-900 tabular-nums tracking-tight mt-0.5">{{ inr(invoice.total) }}</p>
            <p v-if="invoice.status === 'paid'" class="mt-2 text-xs font-semibold text-emerald-700">Paid in full</p>
            <p v-else-if="invoice.amount_due > 0" class="mt-2 text-sm font-semibold text-red-600 tabular-nums">
              Balance due {{ inr(invoice.amount_due) }}
            </p>
          </div>
        </div>

        <!-- Desktop action toolbar -->
        <div v-if="invoice.status !== 'cancelled'" class="hidden lg:flex flex-wrap items-center gap-2 px-5 sm:px-6 py-3 bg-gray-50/80 border-t border-gray-100">
          <button v-if="invoice.status === 'draft'" @click="markSent" :disabled="acting"
            class="inv-detail-btn inv-detail-btn--primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
            {{ acting ? 'Sending…' : 'Mark as sent' }}
          </button>

          <button v-if="['sent','partial','overdue'].includes(invoice.status)" @click="showPayModal = true"
            class="inv-detail-btn inv-detail-btn--success">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Record payment
          </button>

          <span class="inv-detail-divider" />

          <button @click="shareWhatsApp" class="inv-detail-btn inv-detail-btn--ghost" title="Share on WhatsApp">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.137.565 4.147 1.554 5.887L0 24l6.305-1.524A11.94 11.94 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.818 9.818 0 01-5.006-1.375l-.359-.214-3.735.902.948-3.632-.234-.373A9.818 9.818 0 1112 21.818z"/></svg>
            Share
          </button>

          <button @click="shareEmail" class="inv-detail-btn inv-detail-btn--ghost">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            Email
          </button>

          <span class="inv-detail-divider" />

          <button @click="printInvoice" class="inv-detail-btn inv-detail-btn--ghost">Print</button>
          <button @click="downloadPdf()" :disabled="downloading" class="inv-detail-btn inv-detail-btn--ghost">
            {{ downloading ? 'PDF…' : 'PDF' }}
          </button>
          <button @click="printDeliveryChallan" class="inv-detail-btn inv-detail-btn--ghost" title="Delivery challan (no prices)">DC print</button>
          <button @click="downloadPdf('dc')" :disabled="downloading" class="inv-detail-btn inv-detail-btn--ghost">DC PDF</button>

          <span class="inv-detail-divider" />

          <button @click="showEwbModal = true" class="inv-detail-btn inv-detail-btn--ghost">
            {{ ewb ? 'View EWB' : 'E-way bill' }}
          </button>

          <RouterLink :to="`/invoices/${invoice.id}/edit`" class="inv-detail-btn inv-detail-btn--ghost">
            Edit
          </RouterLink>

          <div class="relative ml-auto group">
            <button type="button" class="inv-detail-btn inv-detail-btn--ghost inv-detail-btn--icon" aria-label="More actions">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg>
            </button>
            <div class="absolute right-0 top-full mt-1 w-44 bg-white rounded-lg shadow-lg border border-gray-200 py-1 opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-opacity z-30">
              <RouterLink v-if="invoice.status === 'paid' || invoice.status === 'partial'" :to="`/credit-notes/new?from_invoice=${invoice.id}`"
                class="block px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Issue credit note</RouterLink>
              <button v-if="invoice.status !== 'paid'" type="button" @click="showCancelModal = true"
                class="w-full text-left px-3 py-2 text-sm text-red-600 hover:bg-red-50">Cancel invoice</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Mobile summary (unchanged layout, toned down) -->
      <div class="lg:hidden bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="flex items-center gap-2 mb-1">
              <span class="text-xs text-gray-500">{{ invoice.number }}</span>
              <span :class="statusBadge(invoice.status)" class="px-2 py-0.5 text-[11px] font-semibold rounded-md">{{ statusLabel(invoice.status) }}</span>
            </div>
            <p class="font-semibold text-gray-900 truncate">{{ invoice.client_name }}</p>
            <p class="text-xs text-gray-500 mt-1">Due {{ fmtDateShort(invoice.due_date) }}</p>
          </div>
          <p class="text-lg font-bold text-gray-900 tabular-nums shrink-0">{{ inr(invoice.total) }}</p>
        </div>
      </div>

      <!-- UPI Collect Payment card — shown when balance due and business has UPI -->
      <div v-if="invoice.status !== 'cancelled' && invoice.amount_due > 0 && (business?.upi_qr_image || business?.upi_id)"
        class="w-full max-w-lg mx-auto mb-5 animate-fade-in-up delay-100">
        <div class="bg-white rounded-xl border border-emerald-100 p-5">
          <p class="text-xs font-semibold text-emerald-700 uppercase tracking-wide mb-3">Collect payment</p>
          <div class="flex items-center gap-5">
            <!-- QR code -->
            <div class="shrink-0 text-center">
              <img v-if="business.upi_qr_image" :src="business.upi_qr_image"
                class="w-32 h-32 object-contain border border-emerald-200 rounded-xl bg-white p-1" alt="UPI QR" />
              <img v-else-if="upiQrDataUrl" :src="upiQrDataUrl"
                class="w-32 h-32 border border-emerald-200 rounded-xl bg-white p-1" alt="UPI QR" />
              <p class="text-[10px] text-emerald-600 font-medium mt-1">Scan to Pay</p>
            </div>
            <!-- Details -->
            <div class="flex-1 min-w-0 space-y-2">
              <div>
                <p class="text-[10px] text-emerald-600 font-semibold uppercase tracking-wider">Balance Due</p>
                <p class="text-2xl font-extrabold text-emerald-800">{{ inr(invoice.amount_due) }}</p>
              </div>
              <div v-if="business.upi_id">
                <p class="text-[10px] text-emerald-600 font-semibold uppercase tracking-wider">UPI ID</p>
                <p class="text-sm font-bold text-gray-800 font-mono break-all">{{ business.upi_id }}</p>
              </div>
              <p class="text-[11px] text-emerald-600">Show this QR to customer — they scan from any UPI app</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Invoice document preview -->
      <div class="inv-detail-document bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden animate-fade-in-up">

        <!-- Header -->
        <div class="px-6 pt-6 pb-5 border-b border-gray-200 bg-white">
          <div class="flex items-start justify-between gap-4 mb-5">
            <div>
              <p class="text-lg font-bold text-gray-900 tracking-tight">{{ invoiceTitle }}</p>
              <p class="text-sm text-gray-500 mt-0.5">{{ invoice.number }}</p>
            </div>
            <img v-if="business?.logo" :src="business.logo" class="w-14 h-14 object-contain rounded-lg border border-gray-100 shrink-0" alt="logo" />
          </div>
          <div class="grid sm:grid-cols-2 gap-4 text-sm">
            <div>
              <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">From</p>
              <p class="font-semibold text-gray-900">{{ business?.name || invoice.business_name }}</p>
              <p v-if="business?.address_line1" class="text-gray-600 mt-0.5 text-xs leading-relaxed">{{ business.address_line1 }}<span v-if="business.address_line2">, {{ business.address_line2 }}</span></p>
              <p v-if="business?.city || business?.state_name" class="text-gray-600 text-xs">
                {{ [business?.city, business?.state_name, business?.pincode].filter(Boolean).join(', ') }}
              </p>
              <p v-if="business?.gstin || invoice.business_gstin" class="text-xs text-gray-500 font-mono mt-1.5">GSTIN {{ business?.gstin || invoice.business_gstin }}</p>
              <p v-if="business?.email || business?.mobile" class="text-xs text-gray-500 mt-0.5">{{ [business?.email, business?.mobile].filter(Boolean).join(' · ') }}</p>
            </div>
          </div>
        </div>

        <!-- Bill To + Invoice Details -->
        <div class="grid sm:grid-cols-2 border-b border-gray-200 text-sm">
          <div class="px-6 py-4 sm:border-r border-gray-200">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Bill to</p>
            <p class="font-semibold text-gray-900">{{ invoice.client_name }}</p>
            <p v-if="invoice.client_company" class="text-gray-600 text-xs mt-0.5">{{ invoice.client_company }}</p>
            <p v-if="invoice.client_address1" class="text-gray-600 text-xs mt-1 leading-relaxed">{{ invoice.client_address1 }}<span v-if="invoice.client_address2">, {{ invoice.client_address2 }}</span></p>
            <p v-if="invoice.client_city || invoice.client_pincode" class="text-gray-600 text-xs">
              {{ [invoice.client_city, invoice.client_pincode].filter(Boolean).join(' – ') }}
            </p>
            <div class="mt-2 space-y-0.5 text-xs text-gray-500">
              <p v-if="invoice.client_gstin">GSTIN {{ invoice.client_gstin }}</p>
              <p v-if="invoice.client_mobile">{{ invoice.client_mobile }}</p>
              <p v-if="invoice.client_email">{{ invoice.client_email }}</p>
            </div>
          </div>
          <div class="px-6 py-4 bg-gray-50/50">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Invoice details</p>
            <dl class="space-y-1.5 text-xs">
              <div class="flex justify-between gap-4"><dt class="text-gray-500">Invoice date</dt><dd class="font-medium text-gray-800 text-right">{{ fmtDateShort(invoice.issue_date) }}</dd></div>
              <div class="flex justify-between gap-4"><dt class="text-gray-500">Due date</dt><dd class="font-medium text-gray-800 text-right">{{ fmtDateShort(invoice.due_date) }}</dd></div>
              <div class="flex justify-between gap-4"><dt class="text-gray-500">Place of supply</dt><dd class="font-medium text-gray-800 text-right">{{ invoice.place_of_supply_name || invoice.supply_type }}</dd></div>
            </dl>
          </div>
        </div>

        <!-- Items table -->
        <div class="overflow-x-auto">
          <table class="inv-detail-table w-full text-sm">
            <thead>
              <tr>
                <th class="w-8 text-left">#</th>
                <th class="text-left">Description</th>
                <th class="hidden sm:table-cell text-center w-20">HSN/SAC</th>
                <th class="text-right w-16">Qty</th>
                <th class="hidden sm:table-cell text-right w-24">Rate</th>
                <th class="hidden sm:table-cell text-right w-24">Taxable</th>
                <th v-if="isGst" class="hidden sm:table-cell text-right w-28">Tax</th>
                <th class="text-right w-28">Amount</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(it, idx) in items" :key="it.id">
                <td class="text-gray-400 text-xs">{{ idx + 1 }}</td>
                <td>
                  <p class="font-medium text-gray-800">{{ it.description }}</p>
                  <p v-if="it.unit" class="text-xs text-gray-400">{{ it.unit }}</p>
                  <p class="sm:hidden text-xs text-gray-500 mt-0.5 tabular-nums">{{ it.quantity }} × {{ inr(it.unit_price) }}</p>
                </td>
                <td class="hidden sm:table-cell text-center font-mono text-xs text-gray-500">{{ it.hsn_sac || '—' }}</td>
                <td class="text-right text-gray-700 tabular-nums">{{ it.quantity }}</td>
                <td class="hidden sm:table-cell text-right text-gray-700 tabular-nums">{{ inr(it.unit_price) }}</td>
                <td class="hidden sm:table-cell text-right text-gray-700 tabular-nums">{{ inr(it.taxable_amt) }}</td>
                <td v-if="isGst" class="hidden sm:table-cell text-right text-xs text-gray-600">
                  <div v-if="it.cgst_amt > 0" class="space-y-0.5 tabular-nums">
                    <div>CGST {{ it.cgst_rate }}% · {{ inr(it.cgst_amt) }}</div>
                    <div>SGST {{ it.sgst_rate }}% · {{ inr(it.sgst_amt) }}</div>
                  </div>
                  <div v-else-if="it.igst_amt > 0">IGST {{ it.igst_rate }}% · {{ inr(it.igst_amt) }}</div>
                  <div v-else class="text-gray-400">—</div>
                </td>
                <td class="text-right font-semibold text-gray-900 tabular-nums">{{ inr(it.total) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Totals + Amount in words -->
        <div class="px-6 py-5 border-t border-gray-200 flex flex-col sm:flex-row gap-6 bg-gray-50/30">
          <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Amount in words</p>
            <p class="text-sm text-gray-700 leading-relaxed">{{ amountInWords(invoice.total) }}</p>
          </div>
          <div class="sm:w-72 shrink-0 space-y-2 text-sm border border-gray-200 rounded-lg bg-white p-4">
            <div class="flex justify-between text-gray-600"><span>Subtotal</span><span class="tabular-nums">{{ inr(invoice.subtotal) }}</span></div>
            <div v-if="invoice.cgst_total > 0" class="flex justify-between text-gray-600"><span>CGST</span><span class="tabular-nums">{{ inr(invoice.cgst_total) }}</span></div>
            <div v-if="invoice.sgst_total > 0" class="flex justify-between text-gray-600"><span>SGST</span><span class="tabular-nums">{{ inr(invoice.sgst_total) }}</span></div>
            <div v-if="invoice.igst_total > 0" class="flex justify-between text-gray-600"><span>IGST</span><span class="tabular-nums">{{ inr(invoice.igst_total) }}</span></div>
            <div v-if="invoice.discount > 0" class="flex justify-between text-red-600"><span>Discount</span><span class="tabular-nums">-{{ inr(invoice.discount) }}</span></div>
            <div v-if="invoice.round_off != 0" class="flex justify-between text-gray-500"><span>Round off</span><span class="tabular-nums">{{ inr(invoice.round_off) }}</span></div>
            <div class="flex justify-between font-bold text-gray-900 border-t border-gray-200 pt-2 text-base">
              <span>Total</span><span class="tabular-nums">{{ inr(invoice.total) }}</span>
            </div>
            <div v-if="invoice.amount_paid > 0" class="flex justify-between text-emerald-700"><span>Paid</span><span class="tabular-nums">{{ inr(invoice.amount_paid) }}</span></div>
            <div v-if="invoice.amount_due > 0" class="flex justify-between font-semibold text-red-600 border-t border-gray-100 pt-2">
              <span>Balance due</span><span class="tabular-nums">{{ inr(invoice.amount_due) }}</span>
            </div>
          </div>
        </div>

        <!-- Bank details + Notes/Terms -->
        <div class="px-6 py-5 border-t border-gray-200 grid sm:grid-cols-2 gap-6 text-sm">
          <div v-if="business?.bank_name || business?.upi_id">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Payment details</p>
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
              <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Notes</p>
              <p class="text-sm text-gray-600">{{ invoice.notes || business?.invoice_notes }}</p>
            </div>
            <div v-if="invoice.terms || business?.invoice_terms">
              <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Terms & conditions</p>
              <p class="text-sm text-gray-600">{{ invoice.terms || business?.invoice_terms }}</p>
            </div>
          </div>
        </div>

        <!-- Authorized Signatory -->
        <div class="px-6 py-5 border-t border-gray-200 flex justify-end bg-white">
          <div class="text-center min-w-[200px]">
            <p class="text-xs text-gray-500 mb-10">For {{ business?.name || invoice.business_name }}</p>
            <div class="border-t border-gray-300 pt-2">
              <p class="text-xs font-medium text-gray-600">Authorized signatory</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Payment history -->
      <div v-if="payments.length" class="mt-2 animate-fade-in-up">
        <h2 class="text-sm font-semibold text-gray-800 mb-3 px-1">Payment history</h2>
        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden divide-y divide-gray-100">
          <div v-for="p in payments" :key="p.id" class="flex items-center justify-between px-5 py-3">
            <div>
              <p class="text-sm font-medium text-gray-800">{{ p.method?.toUpperCase() }}</p>
              <p class="text-xs text-gray-400">{{ fmtDateShort(p.payment_date) }}{{ p.reference ? ' · ' + p.reference : '' }}</p>
            </div>
            <p class="font-semibold text-success-700">{{ inr(p.amount) }}</p>
          </div>
        </div>
      </div>

      <!-- E-way Bill status card (shown if EWB exists) -->
      <div v-if="ewb" class="mt-4 animate-fade-in-up">
        <div class="bg-white rounded-[2rem] shadow-soft border overflow-hidden"
          :class="ewb.status === 'active' ? 'border-indigo-100' : ewb.status === 'cancelled' ? 'border-gray-200 opacity-70' : 'border-amber-100'">
          <div class="px-5 py-4 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                :class="ewb.status === 'active' ? 'bg-indigo-50' : ewb.status === 'cancelled' ? 'bg-gray-100' : 'bg-amber-50'">
                <svg class="w-5 h-5" :class="ewb.status === 'active' ? 'text-indigo-600' : ewb.status === 'cancelled' ? 'text-gray-400' : 'text-amber-600'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
              </div>
              <div>
                <p class="text-[10px] font-bold uppercase tracking-widest mb-0.5"
                  :class="ewb.status === 'active' ? 'text-indigo-500' : ewb.status === 'cancelled' ? 'text-gray-400' : 'text-amber-600'">E-way Bill</p>
                <p class="font-bold text-gray-900 font-mono text-sm tracking-wider">{{ ewb.ewb_number || '—' }}</p>
              </div>
            </div>
            <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full border"
              :class="ewb.status === 'active' ? 'bg-indigo-50 text-indigo-700 border-indigo-100' : ewb.status === 'cancelled' ? 'bg-gray-100 text-gray-500 border-gray-200' : 'bg-amber-50 text-amber-700 border-amber-100'">
              {{ ewb.status?.toUpperCase() }}
            </span>
          </div>
          <div class="px-5 pb-4 grid grid-cols-3 gap-3 text-xs border-t border-gray-50 pt-3">
            <div>
              <p class="text-gray-400 font-medium mb-0.5">Valid Until</p>
              <p class="font-semibold text-gray-700">{{ ewb.valid_until ? fmtDateShort(ewb.valid_until) : '—' }}</p>
            </div>
            <div>
              <p class="text-gray-400 font-medium mb-0.5">Vehicle</p>
              <p class="font-semibold text-gray-700 font-mono">{{ ewb.vehicle_no || '—' }}</p>
            </div>
            <div>
              <p class="text-gray-400 font-medium mb-0.5">Distance</p>
              <p class="font-semibold text-gray-700">{{ ewb.distance ? ewb.distance + ' km' : '—' }}</p>
            </div>
          </div>
          <div v-if="ewb.status === 'active'" class="px-5 pb-4 flex gap-2">
            <button @click="showEwbModal = true" class="flex-1 py-2 rounded-xl text-xs font-semibold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition-colors border border-indigo-100">
              View Details
            </button>
            <button @click="cancelEwb" class="px-4 py-2 rounded-xl text-xs font-semibold text-gray-500 hover:text-red-600 hover:bg-red-50 transition-colors border border-gray-200">
              Cancel EWB
            </button>
          </div>
        </div>
      </div>

    </template>

    <!-- Mobile Floating Circular Action Buttons (Invozen Preview Mockup Style) -->
    <div v-if="invoice && invoice.status !== 'cancelled'" class="lg:hidden fixed bottom-6 left-1/2 -translate-x-1/2 z-40 bg-gray-900/90 backdrop-blur-md px-4 py-2.5 rounded-full shadow-2xl flex items-center gap-4 border border-white/10">
      <!-- Print -->
      <button @click="printInvoice" class="w-10 h-10 rounded-full bg-white/15 text-white flex items-center justify-center hover:bg-white/20 transition active:scale-95" title="Print Invoice">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
      </button>
      <!-- Download PDF -->
      <button @click="downloadPdf" :disabled="downloading" class="w-10 h-10 rounded-full bg-white/15 text-white flex items-center justify-center hover:bg-white/20 transition active:scale-95" title="Download PDF">
        <svg v-if="downloading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
        <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
      </button>
      <!-- WhatsApp Share -->
      <button @click="shareWhatsApp" class="w-10 h-10 rounded-full bg-white/15 text-white flex items-center justify-center hover:bg-white/20 transition active:scale-95" title="Share via WhatsApp">
        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.137.565 4.147 1.554 5.887L0 24l6.305-1.524A11.94 11.94 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.818 9.818 0 01-5.006-1.375l-.359-.214-3.735.902.948-3.632-.234-.373A9.818 9.818 0 1112 21.818z"/></svg>
      </button>
      <!-- E-way Bill -->
      <button @click="showEwbModal = true" class="w-10 h-10 rounded-full flex items-center justify-center hover:bg-white/20 transition active:scale-95"
        :class="ewb && ewb.status === 'active' ? 'bg-indigo-400/30 text-indigo-200' : 'bg-white/15 text-white'" title="E-way Bill">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
      </button>
      <!-- Edit Link -->
      <RouterLink :to="`/invoices/${invoice.id}/edit`" class="w-10 h-10 rounded-full bg-white/15 text-white flex items-center justify-center hover:bg-white/20 transition active:scale-95" title="Edit Invoice">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
      </RouterLink>
    </div>

    <!-- Record Payment Modal -->
    <div v-if="showPayModal" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/40">
      <div class="bg-white rounded-2xl w-full max-w-md shadow-xl">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <h3 class="font-semibold text-gray-800">Record Payment</h3>
          <button @click="showPayModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
        <div class="p-5 space-y-4">
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="form-label mb-0">Amount (₹) *</label>
              <div class="flex gap-1.5">
                <button type="button" @click="setQuickAmount(0.25)"
                  class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 hover:bg-primary-100 hover:text-primary-700 transition-colors">25%</button>
                <button type="button" @click="setQuickAmount(0.5)"
                  class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 hover:bg-primary-100 hover:text-primary-700 transition-colors">50%</button>
                <button type="button" @click="setQuickAmount(0.75)"
                  class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 hover:bg-primary-100 hover:text-primary-700 transition-colors">75%</button>
                <button type="button" @click="setQuickAmount(1)"
                  class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 hover:bg-emerald-200 transition-colors">Full</button>
              </div>
            </div>
            <input v-model="payForm.amount" type="number" min="0.01" :max="invoice?.amount_due" step="0.01" class="form-input text-lg font-semibold" />
            <p v-if="invoice?.amount_paid > 0" class="text-xs text-gray-400 mt-1">
              Already paid: {{ inr(invoice.amount_paid) }} · Balance: {{ inr(invoice.amount_due) }}
            </p>
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

    <!-- E-way Bill Modal -->
    <div v-if="showEwbModal" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/40" @click.self="showEwbModal = false">
      <div class="bg-white rounded-2xl w-full max-w-lg shadow-xl overflow-hidden max-h-[90vh] flex flex-col">

        <!-- Header -->
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between shrink-0">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center">
              <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
            </div>
            <div>
              <h3 class="font-semibold text-gray-900 text-sm">{{ ewb ? 'E-way Bill Details' : 'Generate E-way Bill' }}</h3>
              <p class="text-[11px] text-gray-400">{{ invoice?.number }}</p>
            </div>
          </div>
          <button @click="showEwbModal = false" class="w-7 h-7 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition flex items-center justify-center text-lg leading-none">✕</button>
        </div>

        <div class="overflow-y-auto flex-1">

          <!-- If EWB already exists: show read-only details -->
          <div v-if="ewb" class="p-5 space-y-4">
            <div class="bg-indigo-50 rounded-xl p-4 text-center border border-indigo-100">
              <p class="text-[10px] font-bold text-indigo-400 uppercase tracking-widest mb-1">EWB Number</p>
              <p class="text-2xl font-black text-indigo-700 font-mono tracking-widest">{{ ewb.ewb_number || '—' }}</p>
              <span class="mt-2 inline-block text-[10px] font-bold px-2.5 py-0.5 rounded-full"
                :class="ewb.status === 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600'">
                {{ ewb.status?.toUpperCase() }}
              </span>
            </div>
            <div class="grid grid-cols-2 gap-3 text-sm">
              <div class="bg-gray-50 rounded-xl p-3">
                <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wider mb-1">Valid Until</p>
                <p class="font-semibold text-gray-800">{{ ewb.valid_until ? fmtDateShort(ewb.valid_until) : '—' }}</p>
              </div>
              <div class="bg-gray-50 rounded-xl p-3">
                <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wider mb-1">Distance</p>
                <p class="font-semibold text-gray-800">{{ ewb.distance ? ewb.distance + ' km' : '—' }}</p>
              </div>
              <div class="bg-gray-50 rounded-xl p-3">
                <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wider mb-1">Vehicle No.</p>
                <p class="font-bold text-gray-800 font-mono">{{ ewb.vehicle_no || '—' }}</p>
              </div>
              <div class="bg-gray-50 rounded-xl p-3">
                <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wider mb-1">Mode</p>
                <p class="font-semibold text-gray-800 capitalize">{{ ewb.mode || '—' }}</p>
              </div>
            </div>
            <div v-if="ewb.transporter" class="bg-gray-50 rounded-xl p-3 text-sm">
              <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wider mb-1">Transporter</p>
              <p class="font-semibold text-gray-800">{{ ewb.transporter }}</p>
            </div>
          </div>

          <!-- Generate form -->
          <div v-else class="p-5 space-y-5">

            <!-- Part A — auto-filled from invoice -->
            <div>
              <div class="flex items-center gap-2 mb-3">
                <span class="text-[10px] font-black uppercase tracking-wider text-indigo-600 bg-indigo-50 border border-indigo-100 px-2.5 py-1 rounded-full">Part A — Transaction & Goods</span>
                <span class="text-[10px] text-gray-400 font-medium">Auto-filled from invoice</span>
              </div>
              <div class="grid grid-cols-2 gap-2.5">
                <div>
                  <label class="form-label">Document No.</label>
                  <input type="text" :value="invoice?.number" readonly class="form-input bg-green-50 border-green-200 text-gray-600 cursor-default text-xs" />
                </div>
                <div>
                  <label class="form-label">Document Date</label>
                  <input type="text" :value="fmtDateShort(invoice?.issue_date)" readonly class="form-input bg-green-50 border-green-200 text-gray-600 cursor-default text-xs" />
                </div>
                <div>
                  <label class="form-label">From GSTIN</label>
                  <input type="text" :value="business?.gstin || invoice?.business_gstin || '—'" readonly class="form-input bg-green-50 border-green-200 text-gray-600 cursor-default font-mono text-xs" />
                </div>
                <div>
                  <label class="form-label">To GSTIN</label>
                  <input type="text" :value="invoice?.client_gstin || '—'" readonly class="form-input bg-green-50 border-green-200 text-gray-600 cursor-default font-mono text-xs" />
                </div>
                <div>
                  <label class="form-label">Taxable Value (₹)</label>
                  <input type="text" :value="invoice?.subtotal ? invoice.subtotal.toLocaleString('en-IN') : '—'" readonly class="form-input bg-green-50 border-green-200 text-gray-600 cursor-default text-xs" />
                </div>
                <div>
                  <label class="form-label">Invoice Value (₹)</label>
                  <input type="text" :value="invoice?.total ? invoice.total.toLocaleString('en-IN') : '—'" readonly class="form-input bg-green-50 border-green-200 text-gray-600 cursor-default text-xs" />
                </div>
              </div>
            </div>

            <div class="border-t border-gray-100"></div>

            <!-- Part B — Transport Details -->
            <div>
              <span class="text-[10px] font-black uppercase tracking-wider text-emerald-700 bg-emerald-50 border border-emerald-100 px-2.5 py-1 rounded-full mb-3 inline-block">Part B — Transport Details</span>

              <!-- Mode of Transport -->
              <div class="mb-3">
                <label class="form-label">Mode of Transport</label>
                <div class="grid grid-cols-4 gap-2">
                  <button v-for="m in [{ val:'road', icon:'🚛', label:'Road' }, { val:'rail', icon:'🚂', label:'Rail' }, { val:'air', icon:'✈️', label:'Air' }, { val:'ship', icon:'🚢', label:'Ship' }]"
                    :key="m.val" type="button"
                    @click="ewbForm.mode = m.val"
                    class="py-2.5 rounded-xl text-xs font-semibold border transition-all"
                    :class="ewbForm.mode === m.val ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'">
                    <div class="text-base mb-0.5">{{ m.icon }}</div>
                    {{ m.label }}
                  </button>
                </div>
              </div>

              <!-- Distance -->
              <div class="mb-3">
                <label class="form-label">Approximate Distance (km) *</label>
                <input v-model="ewbForm.distance" type="number" min="1" max="4000" class="form-input" placeholder="e.g. 320" />
                <!-- AI Validity Hint -->
                <div v-if="ewbForm.distance && ewbValidityDays(ewbForm.distance)" class="mt-2 flex items-start gap-2 bg-indigo-50 border border-indigo-100 rounded-xl px-3 py-2.5">
                  <svg class="w-4 h-4 text-indigo-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                  <p class="text-xs text-indigo-700">
                    At <strong>{{ ewbForm.distance }} km</strong>, this EWB will be valid for
                    <strong>{{ ewbValidityDays(ewbForm.distance) }} day{{ ewbValidityDays(ewbForm.distance) > 1 ? 's' : '' }}</strong>
                    — until <strong>{{ ewbValidUntil(ewbForm.distance) }}</strong>.
                  </p>
                </div>
              </div>

              <!-- Vehicle -->
              <div class="grid grid-cols-2 gap-2.5 mb-3">
                <div>
                  <label class="form-label">Vehicle Number *</label>
                  <input v-model="ewbForm.vehicle_no" type="text" class="form-input uppercase tracking-wider" placeholder="DL01AB1234" style="text-transform:uppercase;" />
                </div>
                <div>
                  <label class="form-label">Vehicle Type</label>
                  <select v-model="ewbForm.vehicle_type" class="form-input">
                    <option>Regular</option>
                    <option>Over Dimensional Cargo</option>
                  </select>
                </div>
              </div>

              <!-- Transporter -->
              <div>
                <label class="form-label">Transporter Name <span class="text-gray-400 font-normal">(optional)</span></label>
                <input v-model="ewbForm.transporter" type="text" class="form-input" placeholder="e.g. Fast Logistics Pvt Ltd" />
              </div>
            </div>

            <div v-if="ewbError" class="text-sm text-danger-600 bg-danger-50 border border-danger-100 rounded-xl px-4 py-3">{{ ewbError }}</div>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-5 pb-5 pt-3 border-t border-gray-100 flex gap-3 shrink-0">
          <button @click="showEwbModal = false" class="btn-outline flex-1">{{ ewb ? 'Close' : 'Cancel' }}</button>
          <button v-if="!ewb" @click="generateEwb" :disabled="ewbGenerating" class="btn-primary flex-2 flex-1">
            <span v-if="ewbGenerating" class="flex items-center gap-2 justify-center">
              <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
              Generating…
            </span>
            <span v-else>Generate E-way Bill</span>
          </button>
          <button v-if="ewb && ewb.status === 'active'" @click="cancelEwb(); showEwbModal = false" class="btn-danger flex-1">Cancel EWB</button>
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

<style scoped>
.inv-detail-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.5rem 0.75rem;
  font-size: 0.8125rem;
  font-weight: 500;
  border-radius: 0.5rem;
  transition: background-color 0.15s, color 0.15s, border-color 0.15s;
  white-space: nowrap;
}
.inv-detail-btn--primary {
  background: #4f46e5;
  color: #fff;
}
.inv-detail-btn--primary:hover:not(:disabled) { background: #4338ca; }
.inv-detail-btn--primary:disabled { opacity: 0.6; }
.inv-detail-btn--success {
  background: #059669;
  color: #fff;
}
.inv-detail-btn--success:hover { background: #047857; }
.inv-detail-btn--ghost {
  background: #fff;
  color: #374151;
  border: 1px solid #e5e7eb;
}
.inv-detail-btn--ghost:hover {
  background: #f9fafb;
  border-color: #d1d5db;
}
.inv-detail-btn--icon { padding: 0.5rem; }
.inv-detail-divider {
  width: 1px;
  height: 1.25rem;
  background: #e5e7eb;
  margin: 0 0.125rem;
}
.inv-detail-table thead tr {
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
}
.inv-detail-table th {
  padding: 0.625rem 1rem;
  font-size: 0.6875rem;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.inv-detail-table td {
  padding: 0.875rem 1rem;
  border-bottom: 1px solid #f1f5f9;
  vertical-align: top;
}
.inv-detail-table tbody tr:last-child td { border-bottom: none; }
</style>

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
