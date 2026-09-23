<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api, { item, list } from '../../api'
import { useAuthStore } from '../../stores/auth'
import { inr } from '../../utils/currency'
import { fmtDateShort } from '../../utils/date'
import QRCode from 'qrcode'

const route    = useRoute()
const router   = useRouter()
const invoice  = ref(null)
const business = ref(null)
const items    = ref([])
const loading  = ref(true)
const error    = ref('')
const qrDataUrl = ref('')
const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent)
const isAndroidBluetooth = /Android/i.test(navigator.userAgent) && route.query.bluetooth === '1'

const printing = ref(false)
async function triggerPrint() {
  // For thermal receipts, use browser print (no headers/footers issue on receipt printers)
  if (isReceipt.value) { window.print(); return }
  // For A4/A3: generate server PDF and print via iframe — no browser headers/footers
  printing.value = true
  try {
    const base = import.meta.env.VITE_API_URL || '/api'
    const modeParam = route.query.mode ? `?mode=${route.query.mode}` : ''
    const url = `${base}/invoice/${invoice.value.id}/pdf${modeParam}`
    const res = await fetch(url, {
      headers: { 'Authorization': 'Bearer ' + useAuthStore().token }
    })
    if (!res.ok) throw new Error('PDF generation failed')
    const blob = await res.blob()
    const blobUrl = URL.createObjectURL(blob)
    const iframe = document.createElement('iframe')
    iframe.style.cssText = 'position:fixed;top:-9999px;left:-9999px;width:1px;height:1px'
    iframe.src = blobUrl
    document.body.appendChild(iframe)
    iframe.onload = () => {
      setTimeout(() => {
        iframe.contentWindow.print()
        setTimeout(() => { document.body.removeChild(iframe); URL.revokeObjectURL(blobUrl) }, 60000)
      }, 500)
    }
  } catch {
    // Fallback to browser print if PDF fails
    window.print()
  } finally {
    printing.value = false
  }
}
function goBack() { router.back() }

async function downloadPdf() {
  const base = import.meta.env.VITE_API_URL || '/api'
  const url = `${base}/invoice/${invoice.value.id}/pdf`
  try {
    const res = await fetch(url, {
      headers: { 'Authorization': 'Bearer ' + useAuthStore().token }
    })
    if (!res.ok) throw new Error('PDF failed')
    const blob = await res.blob()
    const pdfFile = new File([blob], `invoice-${invoice.value.number || invoice.value.id}.pdf`, { type: 'application/pdf' })
    if (isMobile && navigator.canShare && navigator.canShare({ files: [pdfFile] })) {
      await navigator.share({ files: [pdfFile], title: `Invoice ${invoice.value.number || ''}` })
      return
    }
    const blobUrl = URL.createObjectURL(blob)
    if (isMobile) {
      window.open(blobUrl, '_blank')
    } else {
      const a = document.createElement('a')
      a.href = blobUrl
      a.download = pdfFile.name
      a.click()
    }
    setTimeout(() => URL.revokeObjectURL(blobUrl), 10000)
  } catch { /* silent */ }
}

// Template: classic (default), modern, minimal
const tpl = computed(() => route.query.tpl || localStorage.getItem('invoiceTemplate') || 'classic')
const paper = computed(() => route.query.paper || localStorage.getItem('invoicePaper') || 'a4')
const isReceipt = computed(() => paper.value === 'thermal58' || paper.value === 'thermal80')
const effectiveTpl = computed(() => tpl.value)

// Print modes: normal, dc (delivery challan — no prices), proforma
const mode = computed(() => route.query.mode || 'normal')
const isDC = computed(() => mode.value === 'dc')
const isProforma = computed(() => mode.value === 'proforma')

const invoiceTitle = computed(() => {
  if (isDC.value) return 'Delivery Challan'
  if (isProforma.value) return 'Proforma Invoice'
  const map = { tax_invoice: 'Tax Invoice', bill_of_supply: 'Bill of Supply', retail: 'Retail Invoice', export: 'Export Invoice', proforma: 'Proforma Invoice' }
  return map[invoice.value?.invoice_type] || 'Tax Invoice'
})

const isGst = computed(() => !isDC.value && invoice.value?.invoice_type !== 'bill_of_supply')
const hasItemDiscount = computed(() => items.value.some(it => parseFloat(it.discount_pct || 0) > 0))

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
  document.body.className = `paper-${paper.value}`
  // Roll height comes from the Windows printer driver; CSS sets only the content width.
  const sizes = { a4: 'A4', a3: 'A3', thermal80: 'auto', thermal58: 'auto' }
  const style = document.createElement('style')
  style.textContent = `@page { size: ${sizes[paper.value] || 'A4'}; margin: ${isReceipt.value ? '0' : '10mm'}; }`
  document.head.appendChild(style)
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

    if (business.value?.upi_id && invoice.value) {
      const due = parseFloat(invoice.value.amount_due || 0)
      const upiStr = `upi://pay?pa=${encodeURIComponent(business.value.upi_id)}&pn=${encodeURIComponent(business.value.name || '')}&am=${due > 0 ? due.toFixed(2) : ''}&cu=INR&tn=${encodeURIComponent(invoice.value.number)}`
      try {
        qrDataUrl.value = await QRCode.toDataURL(upiStr, { width: 96, margin: 1, color: { dark: '#111827', light: '#ffffff' } })
      } catch {}
    }
  } catch (e) {
    error.value = 'Could not load invoice. Please ensure you are logged in.'
    loading.value = false
    return
  }
  loading.value = false
  // Auto-print on desktop; on Android Bluetooth auto-trigger print dialog too
  if (!/Android|iPhone|iPad|iPod/i.test(navigator.userAgent) || isAndroidBluetooth) {
    setTimeout(() => triggerPrint(), 300)
  }
})
</script>

<template>
  <div class="print-page">
    <div v-if="loading" class="flex items-center justify-center min-h-screen">
      <div class="w-8 h-8 border-4 border-blue-100 border-t-blue-600 rounded-full animate-spin"></div>
    </div>
    <div v-else-if="error" class="flex items-center justify-center min-h-screen text-red-600 text-sm p-8 text-center">{{ error }}</div>

    <!-- ════════════════════════════════════════════════════════════════ -->
    <!-- TEMPLATE: CLASSIC                                               -->
    <!-- ════════════════════════════════════════════════════════════════ -->
    <div v-else-if="invoice && isReceipt" class="receipt-doc">
      <header class="receipt-header">
        <img v-if="business?.logo" :src="business.logo" alt="" class="receipt-logo" />
        <h1>{{ business?.name || invoice.business_name }}</h1>
        <p v-if="business?.address_line1">{{ [business.address_line1, business.address_line2, business.city, business.state_name, business.pincode].filter(Boolean).join(', ') }}</p>
        <p v-if="business?.mobile">Tel: {{ business.mobile }}</p>
        <p v-if="business?.gstin">GSTIN: {{ business.gstin }}</p>
      </header>
      <div class="receipt-rule"></div>
      <p class="receipt-title">{{ invoiceTitle }}</p>
      <div class="receipt-pair"><span>No.</span><strong>{{ invoice.number }}</strong></div>
      <div class="receipt-pair"><span>Date</span><span>{{ fmtDateShort(invoice.issue_date) }}</span></div>
      <div v-if="!isDC" class="receipt-pair"><span>Due</span><span>{{ fmtDateShort(invoice.due_date) }}</span></div>
      <div class="receipt-rule"></div>
      <p>Bill to: <strong>{{ invoice.client_name || 'Walk-in Customer' }}</strong></p>
      <p v-if="invoice.client_gstin">Customer GSTIN: {{ invoice.client_gstin }}</p>
      <p v-if="invoice.client_mobile">Mobile: {{ invoice.client_mobile }}</p>
      <div class="receipt-rule"></div>
      <div v-for="(it, idx) in items" :key="it.id || idx" class="receipt-item">
        <strong>{{ idx + 1 }}. {{ it.description }}</strong>
        <p v-if="it.hsn_sac || (isGst && Number(it.gst_rate))"><span v-if="it.hsn_sac">HSN/SAC: {{ it.hsn_sac }}</span><span v-if="it.hsn_sac && isGst && Number(it.gst_rate)"> · </span><span v-if="isGst && Number(it.gst_rate)">GST {{ it.gst_rate }}%</span></p>
        <div class="receipt-pair"><span>{{ it.quantity }} {{ it.unit || 'Nos' }}<template v-if="!isDC"> × {{ inr(it.unit_price) }}</template></span><strong v-if="!isDC">{{ inr(it.total) }}</strong></div>
      </div>
      <div class="receipt-rule"></div>
      <template v-if="isDC"><div class="receipt-pair"><strong>Total Qty</strong><strong>{{ items.reduce((sum, it) => sum + Number(it.quantity || 0), 0) }}</strong></div></template>
      <template v-else>
        <div class="receipt-pair"><span>Subtotal</span><span>{{ inr(Number(invoice.subtotal || 0) + Number(invoice.discount || 0)) }}</span></div>
        <div v-if="Number(invoice.discount)" class="receipt-pair"><span>Discount</span><span>-{{ inr(invoice.discount) }}</span></div>
        <div v-if="Number(invoice.cgst_total)" class="receipt-pair"><span>CGST</span><span>{{ inr(invoice.cgst_total) }}</span></div>
        <div v-if="Number(invoice.sgst_total)" class="receipt-pair"><span>SGST</span><span>{{ inr(invoice.sgst_total) }}</span></div>
        <div v-if="Number(invoice.igst_total)" class="receipt-pair"><span>IGST</span><span>{{ inr(invoice.igst_total) }}</span></div>
        <div class="receipt-rule"></div>
        <div class="receipt-pair receipt-total"><strong>Total</strong><strong>{{ inr(invoice.total) }}</strong></div>
        <div v-if="Number(invoice.amount_paid)" class="receipt-pair"><span>Paid</span><span>{{ inr(invoice.amount_paid) }}</span></div>
        <div v-if="Number(invoice.amount_due)" class="receipt-pair"><strong>Balance Due</strong><strong>{{ inr(invoice.amount_due) }}</strong></div>
      </template>
      <div class="receipt-rule"></div>
      <p v-if="business?.upi_id">UPI: {{ business.upi_id }}</p>
      <img v-if="qrDataUrl && !isDC && Number(invoice.amount_due) > 0" :src="qrDataUrl" alt="UPI payment QR" class="receipt-qr" />
      <p v-if="invoice.notes || business?.invoice_notes">{{ invoice.notes || business.invoice_notes }}</p>
      <p v-if="isProforma">Proforma only — not a tax invoice</p>
      <p class="receipt-thanks">Thank you!</p>
    </div>
    <div v-else-if="invoice && effectiveTpl === 'classic'" class="invoice-doc">
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
          <p class="font-bold text-gray-900 text-sm">{{ invoice.client_name || 'Walk-in Customer' }}</p>
          <p v-if="invoice.client_company" class="text-xs text-gray-600">{{ invoice.client_company }}</p>
          <p v-if="invoice.client_gstin" class="text-xs text-gray-500 font-mono">GSTIN: {{ invoice.client_gstin }}</p>
          <p v-if="invoice.client_mobile" class="text-xs text-gray-500">Mob: {{ invoice.client_mobile }}</p>
        </div>
        <div>
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">{{ isDC ? 'Challan Details' : 'Invoice Details' }}</p>
          <table class="text-xs w-full">
            <tr><td class="text-gray-400 pb-1 pr-3">{{ isDC ? 'Ref Invoice' : 'Invoice No' }}</td><td class="font-semibold text-gray-800">{{ invoice.number }}</td></tr>
            <tr><td class="text-gray-400 pb-1 pr-3">{{ isDC ? 'Challan Date' : 'Invoice Date' }}</td><td class="font-medium text-gray-700">{{ fmtDateShort(invoice.issue_date) }}</td></tr>
            <tr v-if="!isDC"><td class="text-gray-400 pb-1 pr-3">Due Date</td><td class="font-medium text-gray-700">{{ fmtDateShort(invoice.due_date) }}</td></tr>
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
            <th class="px-2 py-2 text-center text-xs font-semibold">Unit</th>
            <th v-if="!isDC" class="px-2 py-2 text-right text-xs font-semibold">Rate</th>
            <th v-if="hasItemDiscount && !isDC" class="px-2 py-2 text-right text-xs font-semibold">Disc%</th>
            <th v-if="!isDC" class="px-2 py-2 text-right text-xs font-semibold">Taxable</th>
            <th v-if="isGst" class="px-2 py-2 text-right text-xs font-semibold">Tax</th>
            <th v-if="!isDC" class="px-2 py-2 text-right text-xs font-semibold">Amount</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(it, idx) in items" :key="it.id" class="border-b border-gray-100">
            <td class="px-2 py-2 text-gray-400 text-xs">{{ idx + 1 }}</td>
            <td class="px-2 py-2 font-medium text-gray-800">{{ it.description }}</td>
            <td class="px-2 py-2 text-center font-mono text-xs text-gray-500">{{ it.hsn_sac || '—' }}</td>
            <td class="px-2 py-2 text-right text-gray-700">{{ it.quantity }}</td>
            <td class="px-2 py-2 text-center text-gray-700 text-xs">{{ it.unit || 'Nos' }}</td>
            <td v-if="!isDC" class="px-2 py-2 text-right text-gray-700">{{ inr(it.unit_price) }}</td>
            <td v-if="hasItemDiscount && !isDC" class="px-2 py-2 text-right text-green-700 text-xs">{{ parseFloat(it.discount_pct || 0) > 0 ? it.discount_pct + '%' : '—' }}</td>
            <td v-if="!isDC" class="px-2 py-2 text-right text-gray-700">{{ inr(it.taxable_amt) }}</td>
            <td v-if="isGst" class="px-2 py-2 text-right text-xs">
              <div v-if="it.cgst_amt > 0"><span class="text-gray-600">CGST {{ it.gst_rate/2 }}%: {{ inr(it.cgst_amt) }}</span><br/><span class="text-gray-600">SGST {{ it.gst_rate/2 }}%: {{ inr(it.sgst_amt) }}</span></div>
              <div v-else-if="it.igst_amt > 0" class="text-gray-600">IGST {{ it.gst_rate }}%: {{ inr(it.igst_amt) }}</div>
              <div v-else class="text-gray-400">Nil</div>
            </td>
            <td v-if="!isDC" class="px-2 py-2 text-right font-semibold text-gray-900">{{ inr(it.total) }}</td>
          </tr>
        </tbody>
      </table>

      <!-- Proforma / DC disclaimers -->
      <div v-if="isProforma" class="mb-4 px-3 py-2 border-2 border-dashed border-amber-400 bg-amber-50 text-center">
        <p class="text-xs font-bold text-amber-700 uppercase tracking-wider">This is not a tax invoice — for reference only</p>
      </div>
      <div v-if="isDC" class="mb-4 pb-4 border-b border-gray-200 text-right">
        <p class="text-sm text-gray-600">Total Items: <span class="font-bold text-gray-900">{{ items.length }}</span></p>
        <p class="text-sm text-gray-600">Total Quantity: <span class="font-bold text-gray-900">{{ items.reduce((s, it) => s + parseFloat(it.quantity || 0), 0) }}</span></p>
      </div>

      <!-- Totals -->
      <div v-if="!isDC" class="flex gap-6 mb-4 pb-4 border-b border-gray-200">
        <div class="flex-1">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Amount in Words</p>
          <p class="text-sm font-medium text-gray-700 italic">{{ amountInWords(invoice.total) }}</p>
        </div>
        <div class="w-52 space-y-1 text-xs">
          <div class="flex justify-between text-gray-600"><span>Subtotal</span><span>{{ inr(parseFloat(invoice.subtotal||0) + parseFloat(invoice.discount||0)) }}</span></div>
          <div v-if="invoice.discount > 0" class="flex justify-between text-green-700"><span>Discount</span><span>-{{ inr(invoice.discount) }}</span></div>
          <div v-if="invoice.cgst_total > 0" class="flex justify-between text-gray-600"><span>CGST</span><span>{{ inr(invoice.cgst_total) }}</span></div>
          <div v-if="invoice.sgst_total > 0" class="flex justify-between text-gray-600"><span>SGST</span><span>{{ inr(invoice.sgst_total) }}</span></div>
          <div v-if="invoice.igst_total > 0" class="flex justify-between text-gray-600"><span>IGST</span><span>{{ inr(invoice.igst_total) }}</span></div>
          <div class="flex justify-between font-bold text-sm text-gray-900 border-t border-gray-300 pt-1"><span>Total</span><span>{{ inr(invoice.total) }}</span></div>
          <div v-if="invoice.amount_paid > 0" class="flex justify-between text-green-700"><span>Paid</span><span>{{ inr(invoice.amount_paid) }}</span></div>
          <div v-if="invoice.amount_due > 0" class="flex justify-between font-bold text-red-600 border-t border-gray-300 pt-1"><span>Balance Due</span><span>{{ inr(invoice.amount_due) }}</span></div>
        </div>
      </div>

      <!-- Bank + Notes -->
      <div class="grid grid-cols-2 gap-6 mb-6">
        <div v-if="!isDC && (business?.bank_name || business?.upi_id)">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Payment Details</p>
          <div class="flex gap-4 items-start">
            <div class="space-y-0.5 text-xs flex-1">
              <div v-if="business.bank_name" class="flex gap-2"><span class="text-gray-400 w-16 shrink-0">Bank</span><span>{{ business.bank_name }}</span></div>
              <div v-if="business.bank_account_no" class="flex gap-2"><span class="text-gray-400 w-16 shrink-0">A/C No</span><span class="font-mono">{{ business.bank_account_no }}</span></div>
              <div v-if="business.bank_ifsc" class="flex gap-2"><span class="text-gray-400 w-16 shrink-0">IFSC</span><span class="font-mono">{{ business.bank_ifsc }}</span></div>
              <div v-if="business.upi_id" class="flex gap-2"><span class="text-gray-400 w-16 shrink-0">UPI ID</span><span class="font-mono">{{ business.upi_id }}</span></div>
            </div>
            <div v-if="qrDataUrl && invoice?.amount_due > 0" class="shrink-0 text-center">
              <img :src="qrDataUrl" class="w-20 h-20 border border-gray-200 rounded" alt="UPI QR" />
              <p class="text-[9px] text-gray-400 mt-0.5">Scan to Pay</p>
            </div>
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

    <!-- ════════════════════════════════════════════════════════════════ -->
    <!-- TEMPLATE: MODERN                                                -->
    <!-- ════════════════════════════════════════════════════════════════ -->
    <div v-else-if="invoice && effectiveTpl === 'modern'" class="invoice-doc">
      <!-- Accent header bar -->
      <div style="background: linear-gradient(135deg, #1a5fd4, #3b7ded); padding: 20px 24px; border-radius: 8px 8px 0 0; margin: -20px -20px 0 -20px; color: white; display: flex; justify-content: space-between; align-items: center;">
        <div>
          <p style="font-size: 22px; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase;">{{ invoiceTitle }}</p>
          <p style="font-size: 12px; opacity: 0.8; margin-top: 2px;">{{ invoice.number }}</p>
        </div>
        <div style="text-align: right;">
          <img v-if="business?.logo" :src="business.logo" style="width: 48px; height: 48px; object-fit: contain; border-radius: 8px; border: 2px solid rgba(255,255,255,0.3);" alt="logo" />
        </div>
      </div>

      <!-- Business + Client row -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin: 20px 0; padding-bottom: 16px; border-bottom: 2px solid #eef2f7;">
        <div>
          <p style="font-size: 15px; font-weight: 700; color: #111827;">{{ business?.name || invoice.business_name }}</p>
          <p v-if="business?.address_line1" style="font-size: 11px; color: #6b7280; margin-top: 2px;">{{ business.address_line1 }}<span v-if="business.address_line2">, {{ business.address_line2 }}</span></p>
          <p v-if="business?.city" style="font-size: 11px; color: #6b7280;">{{ [business?.city, business?.state_name, business?.pincode].filter(Boolean).join(', ') }}</p>
          <p v-if="business?.mobile || business?.email" style="font-size: 11px; color: #6b7280;">{{ [business?.mobile, business?.email].filter(Boolean).join(' · ') }}</p>
          <p v-if="business?.gstin" style="font-size: 11px; color: #6b7280; font-family: monospace;">GSTIN: {{ business.gstin }}</p>
        </div>
        <div style="background: #f8faff; border-radius: 10px; padding: 12px 16px;">
          <p style="font-size: 10px; font-weight: 700; color: #1a5fd4; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 6px;">Bill To</p>
          <p style="font-size: 14px; font-weight: 700; color: #111827;">{{ invoice.client_name || 'Walk-in Customer' }}</p>
          <p v-if="invoice.client_company" style="font-size: 11px; color: #6b7280;">{{ invoice.client_company }}</p>
          <p v-if="invoice.client_gstin" style="font-size: 11px; color: #6b7280; font-family: monospace;">GSTIN: {{ invoice.client_gstin }}</p>
          <p v-if="invoice.client_mobile" style="font-size: 11px; color: #6b7280;">{{ invoice.client_mobile }}</p>
        </div>
      </div>

      <!-- Invoice meta pills -->
      <div style="display: flex; gap: 12px; margin-bottom: 16px; flex-wrap: wrap;">
        <div style="background: #eef4ff; border-radius: 8px; padding: 8px 14px; flex: 1; min-width: 120px;">
          <p style="font-size: 9px; font-weight: 700; color: #1a5fd4; text-transform: uppercase; letter-spacing: 0.1em;">Invoice Date</p>
          <p style="font-size: 13px; font-weight: 600; color: #111827; margin-top: 2px;">{{ fmtDateShort(invoice.issue_date) }}</p>
        </div>
        <div v-if="!isDC" style="background: #eef4ff; border-radius: 8px; padding: 8px 14px; flex: 1; min-width: 120px;">
          <p style="font-size: 9px; font-weight: 700; color: #1a5fd4; text-transform: uppercase; letter-spacing: 0.1em;">Due Date</p>
          <p style="font-size: 13px; font-weight: 600; color: #111827; margin-top: 2px;">{{ fmtDateShort(invoice.due_date) }}</p>
        </div>
        <div style="background: #eef4ff; border-radius: 8px; padding: 8px 14px; flex: 1; min-width: 120px;">
          <p style="font-size: 9px; font-weight: 700; color: #1a5fd4; text-transform: uppercase; letter-spacing: 0.1em;">Place of Supply</p>
          <p style="font-size: 13px; font-weight: 600; color: #111827; margin-top: 2px;">{{ invoice.place_of_supply_name || invoice.supply_type }}</p>
        </div>
      </div>

      <!-- Items -->
      <table class="w-full text-sm mb-4">
        <thead>
          <tr style="background: linear-gradient(135deg, #1a5fd4, #3b7ded); color: white;">
            <th style="padding: 10px 8px; text-align: left; font-size: 11px; font-weight: 600; border-radius: 6px 0 0 0;">#</th>
            <th style="padding: 10px 8px; text-align: left; font-size: 11px; font-weight: 600;">Description</th>
            <th style="padding: 10px 8px; text-align: center; font-size: 11px; font-weight: 600;">HSN</th>
            <th style="padding: 10px 8px; text-align: right; font-size: 11px; font-weight: 600;">Qty</th>
            <th style="padding: 10px 8px; text-align: center; font-size: 11px; font-weight: 600;">Unit</th>
            <th v-if="!isDC" style="padding: 10px 8px; text-align: right; font-size: 11px; font-weight: 600;">Rate</th>
            <th v-if="hasItemDiscount && !isDC" style="padding: 10px 8px; text-align: right; font-size: 11px; font-weight: 600;">Disc%</th>
            <th v-if="isGst" style="padding: 10px 8px; text-align: right; font-size: 11px; font-weight: 600;">Tax</th>
            <th v-if="!isDC" style="padding: 10px 8px; text-align: right; font-size: 11px; font-weight: 600; border-radius: 0 6px 0 0;">Amount</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(it, idx) in items" :key="it.id" :style="{ background: idx % 2 === 0 ? '#f8faff' : 'white' }">
            <td style="padding: 8px; font-size: 11px; color: #9ca3af;">{{ idx + 1 }}</td>
            <td style="padding: 8px; font-weight: 600; color: #1f2937;">{{ it.description }}</td>
            <td style="padding: 8px; text-align: center; font-family: monospace; font-size: 11px; color: #6b7280;">{{ it.hsn_sac || '—' }}</td>
            <td style="padding: 8px; text-align: right; color: #374151;">{{ it.quantity }}</td>
            <td style="padding: 8px; text-align: center; font-size: 11px; color: #374151;">{{ it.unit || 'Nos' }}</td>
            <td v-if="!isDC" style="padding: 8px; text-align: right; color: #374151;">{{ inr(it.unit_price) }}</td>
            <td v-if="hasItemDiscount && !isDC" style="padding: 8px; text-align: right; font-size: 11px; color: #15803d;">{{ parseFloat(it.discount_pct || 0) > 0 ? it.discount_pct + '%' : '—' }}</td>
            <td v-if="isGst" style="padding: 8px; text-align: right; font-size: 10px; color: #6b7280;">
              <span v-if="it.cgst_amt > 0">{{ it.gst_rate }}%</span>
              <span v-else-if="it.igst_amt > 0">{{ it.gst_rate }}%</span>
              <span v-else>—</span>
            </td>
            <td v-if="!isDC" style="padding: 8px; text-align: right; font-weight: 700; color: #111827;">{{ inr(it.total) }}</td>
          </tr>
        </tbody>
      </table>

      <!-- Proforma / DC -->
      <div v-if="isProforma" style="margin-bottom: 16px; padding: 8px 12px; border: 2px dashed #f59e0b; background: #fffbeb; text-align: center; border-radius: 8px;">
        <p style="font-size: 11px; font-weight: 700; color: #b45309; text-transform: uppercase; letter-spacing: 0.08em;">This is not a tax invoice — for reference only</p>
      </div>
      <div v-if="isDC" style="margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid #e5e7eb; text-align: right;">
        <p style="font-size: 13px; color: #4b5563;">Total Items: <span style="font-weight: 700; color: #111827;">{{ items.length }}</span></p>
        <p style="font-size: 13px; color: #4b5563;">Total Qty: <span style="font-weight: 700; color: #111827;">{{ items.reduce((s, it) => s + parseFloat(it.quantity || 0), 0) }}</span></p>
      </div>

      <!-- Totals -->
      <div v-if="!isDC" style="display: flex; gap: 24px; margin-bottom: 16px; padding-bottom: 16px; border-bottom: 2px solid #eef2f7;">
        <div style="flex: 1;">
          <p style="font-size: 9px; font-weight: 700; color: #1a5fd4; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 4px;">Amount in Words</p>
          <p style="font-size: 12px; font-weight: 500; color: #374151; font-style: italic;">{{ amountInWords(invoice.total) }}</p>
        </div>
        <div style="width: 220px;">
          <div style="display: flex; justify-content: space-between; padding: 3px 0; font-size: 12px; color: #4b5563;"><span>Subtotal</span><span>{{ inr(parseFloat(invoice.subtotal||0) + parseFloat(invoice.discount||0)) }}</span></div>
          <div v-if="invoice.discount > 0" style="display: flex; justify-content: space-between; padding: 3px 0; font-size: 12px; color: #15803d;"><span>Discount</span><span>-{{ inr(invoice.discount) }}</span></div>
          <div v-if="invoice.cgst_total > 0" style="display: flex; justify-content: space-between; padding: 3px 0; font-size: 12px; color: #4b5563;"><span>CGST</span><span>{{ inr(invoice.cgst_total) }}</span></div>
          <div v-if="invoice.sgst_total > 0" style="display: flex; justify-content: space-between; padding: 3px 0; font-size: 12px; color: #4b5563;"><span>SGST</span><span>{{ inr(invoice.sgst_total) }}</span></div>
          <div v-if="invoice.igst_total > 0" style="display: flex; justify-content: space-between; padding: 3px 0; font-size: 12px; color: #4b5563;"><span>IGST</span><span>{{ inr(invoice.igst_total) }}</span></div>
          <div style="display: flex; justify-content: space-between; padding: 8px 12px; margin-top: 4px; font-size: 14px; font-weight: 700; color: white; background: linear-gradient(135deg, #1a5fd4, #3b7ded); border-radius: 6px;"><span>Total</span><span>{{ inr(invoice.total) }}</span></div>
          <div v-if="invoice.amount_paid > 0" style="display: flex; justify-content: space-between; padding: 3px 0; font-size: 12px; color: #15803d; margin-top: 4px;"><span>Paid</span><span>{{ inr(invoice.amount_paid) }}</span></div>
          <div v-if="invoice.amount_due > 0" style="display: flex; justify-content: space-between; padding: 4px 0; font-size: 13px; font-weight: 700; color: #dc2626; border-top: 1px solid #e5e7eb; margin-top: 4px;"><span>Balance Due</span><span>{{ inr(invoice.amount_due) }}</span></div>
        </div>
      </div>

      <!-- Bank + Notes -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
        <div v-if="!isDC && (business?.bank_name || business?.upi_id)">
          <p style="font-size: 9px; font-weight: 700; color: #1a5fd4; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 4px;">Payment Details</p>
          <div style="display: flex; gap: 16px; align-items: flex-start;">
            <div style="flex: 1;">
              <div v-if="business.bank_name" style="font-size: 11px; color: #4b5563; padding: 2px 0;"><span style="color: #9ca3af; display: inline-block; width: 48px;">Bank</span> {{ business.bank_name }}</div>
              <div v-if="business.bank_account_no" style="font-size: 11px; color: #4b5563; padding: 2px 0; font-family: monospace;"><span style="color: #9ca3af; display: inline-block; width: 48px; font-family: sans-serif;">A/C</span> {{ business.bank_account_no }}</div>
              <div v-if="business.bank_ifsc" style="font-size: 11px; color: #4b5563; padding: 2px 0; font-family: monospace;"><span style="color: #9ca3af; display: inline-block; width: 48px; font-family: sans-serif;">IFSC</span> {{ business.bank_ifsc }}</div>
              <div v-if="business.upi_id" style="font-size: 11px; color: #4b5563; padding: 2px 0; font-family: monospace;"><span style="color: #9ca3af; display: inline-block; width: 48px; font-family: sans-serif;">UPI</span> {{ business.upi_id }}</div>
            </div>
            <div v-if="qrDataUrl && invoice?.amount_due > 0" style="text-align: center;">
              <img :src="qrDataUrl" style="width: 72px; height: 72px; border: 1px solid #e5e7eb; border-radius: 6px;" alt="UPI QR" />
              <p style="font-size: 8px; color: #9ca3af; margin-top: 2px;">Scan to Pay</p>
            </div>
          </div>
        </div>
        <div>
          <div v-if="invoice.notes || business?.invoice_notes" style="margin-bottom: 8px;">
            <p style="font-size: 9px; font-weight: 700; color: #1a5fd4; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 2px;">Notes</p>
            <p style="font-size: 11px; color: #4b5563;">{{ invoice.notes || business?.invoice_notes }}</p>
          </div>
          <div v-if="invoice.terms || business?.invoice_terms">
            <p style="font-size: 9px; font-weight: 700; color: #1a5fd4; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 2px;">Terms</p>
            <p style="font-size: 11px; color: #4b5563;">{{ invoice.terms || business?.invoice_terms }}</p>
          </div>
        </div>
      </div>

      <!-- Signature -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 32px; margin-top: 32px;">
        <div style="text-align: center;"><div style="border-top: 1px solid #9ca3af; padding-top: 8px; margin-top: 48px;"><p style="font-size: 11px; font-weight: 600; color: #6b7280;">Customer Signature</p></div></div>
        <div style="text-align: center;"><div style="border-top: 1px solid #9ca3af; padding-top: 8px; margin-top: 48px;"><p style="font-size: 11px; font-weight: 600; color: #6b7280;">Authorised Signatory</p></div></div>
      </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════════ -->
    <!-- TEMPLATE: MINIMAL                                               -->
    <!-- ════════════════════════════════════════════════════════════════ -->
    <div v-else-if="invoice && effectiveTpl === 'minimal'" class="invoice-doc" style="font-family: 'Georgia', 'Times New Roman', serif;">
      <!-- Clean header -->
      <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 32px;">
        <div>
          <div style="display: flex; align-items: center; gap: 12px;">
            <img v-if="business?.logo" :src="business.logo" style="width: 40px; height: 40px; object-fit: contain;" alt="logo" />
            <p style="font-size: 20px; font-weight: 700; color: #111827; letter-spacing: -0.02em;">{{ business?.name || invoice.business_name }}</p>
          </div>
          <p v-if="business?.address_line1" style="font-size: 11px; color: #9ca3af; margin-top: 4px;">{{ [business.address_line1, business.address_line2, business?.city, business?.state_name, business?.pincode].filter(Boolean).join(', ') }}</p>
          <p v-if="business?.mobile || business?.email" style="font-size: 11px; color: #9ca3af;">{{ [business?.mobile, business?.email].filter(Boolean).join(' · ') }}</p>
          <p v-if="business?.gstin" style="font-size: 11px; color: #9ca3af; font-family: monospace;">GSTIN: {{ business.gstin }}</p>
        </div>
        <div style="text-align: right;">
          <p style="font-size: 28px; font-weight: 300; color: #111827; letter-spacing: -0.02em; text-transform: uppercase;">{{ invoiceTitle }}</p>
          <p style="font-size: 12px; color: #9ca3af; margin-top: 4px; font-family: monospace;">{{ invoice.number }}</p>
        </div>
      </div>

      <!-- Thin line -->
      <div style="height: 1px; background: #e5e7eb; margin-bottom: 24px;"></div>

      <!-- Client + Details -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 24px;">
        <div>
          <p style="font-size: 9px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.15em; margin-bottom: 8px;">Billed To</p>
          <p style="font-size: 15px; font-weight: 600; color: #111827;">{{ invoice.client_name || 'Walk-in Customer' }}</p>
          <p v-if="invoice.client_company" style="font-size: 12px; color: #6b7280; margin-top: 2px;">{{ invoice.client_company }}</p>
          <p v-if="invoice.client_gstin" style="font-size: 11px; color: #9ca3af; font-family: monospace; margin-top: 4px;">GSTIN: {{ invoice.client_gstin }}</p>
          <p v-if="invoice.client_mobile" style="font-size: 11px; color: #9ca3af; margin-top: 2px;">{{ invoice.client_mobile }}</p>
        </div>
        <div style="text-align: right;">
          <div style="margin-bottom: 8px;"><span style="font-size: 9px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.15em;">Date</span><br/><span style="font-size: 13px; color: #111827;">{{ fmtDateShort(invoice.issue_date) }}</span></div>
          <div v-if="!isDC" style="margin-bottom: 8px;"><span style="font-size: 9px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.15em;">Due</span><br/><span style="font-size: 13px; color: #111827;">{{ fmtDateShort(invoice.due_date) }}</span></div>
          <div><span style="font-size: 9px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.15em;">Supply</span><br/><span style="font-size: 13px; color: #111827;">{{ invoice.place_of_supply_name || invoice.supply_type }}</span></div>
        </div>
      </div>

      <!-- Items — clean table -->
      <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px; font-family: -apple-system, sans-serif;">
        <thead>
          <tr style="border-bottom: 2px solid #111827;">
            <th style="padding: 8px 0; text-align: left; font-size: 9px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.15em;">Description</th>
            <th style="padding: 8px 0; text-align: center; font-size: 9px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.15em;">HSN</th>
            <th style="padding: 8px 0; text-align: right; font-size: 9px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.15em;">Qty</th>
            <th v-if="!isDC" style="padding: 8px 0; text-align: right; font-size: 9px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.15em;">Rate</th>
            <th v-if="hasItemDiscount && !isDC" style="padding: 8px 0; text-align: right; font-size: 9px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.15em;">Disc%</th>
            <th v-if="isGst" style="padding: 8px 0; text-align: right; font-size: 9px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.15em;">Tax</th>
            <th v-if="!isDC" style="padding: 8px 0; text-align: right; font-size: 9px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.15em;">Amount</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(it, idx) in items" :key="it.id" style="border-bottom: 1px solid #f3f4f6;">
            <td style="padding: 10px 0;">
              <span style="font-size: 13px; font-weight: 500; color: #111827;">{{ it.description }}</span>
              <span v-if="it.unit && it.unit !== 'Nos'" style="font-size: 10px; color: #9ca3af; margin-left: 6px;">{{ it.unit }}</span>
            </td>
            <td style="padding: 10px 0; text-align: center; font-family: monospace; font-size: 11px; color: #9ca3af;">{{ it.hsn_sac || '—' }}</td>
            <td style="padding: 10px 0; text-align: right; font-size: 13px; color: #374151;">{{ it.quantity }}</td>
            <td v-if="!isDC" style="padding: 10px 0; text-align: right; font-size: 13px; color: #374151;">{{ inr(it.unit_price) }}</td>
            <td v-if="hasItemDiscount && !isDC" style="padding: 10px 0; text-align: right; font-size: 11px; color: #15803d;">{{ parseFloat(it.discount_pct || 0) > 0 ? it.discount_pct + '%' : '—' }}</td>
            <td v-if="isGst" style="padding: 10px 0; text-align: right; font-size: 11px; color: #9ca3af;">{{ it.gst_rate }}%</td>
            <td v-if="!isDC" style="padding: 10px 0; text-align: right; font-size: 13px; font-weight: 600; color: #111827;">{{ inr(it.total) }}</td>
          </tr>
        </tbody>
      </table>

      <!-- Proforma / DC -->
      <div v-if="isProforma" style="margin-bottom: 16px; padding: 10px; border: 1px solid #f59e0b; text-align: center;">
        <p style="font-size: 11px; font-weight: 600; color: #b45309; text-transform: uppercase; letter-spacing: 0.1em;">This is not a tax invoice — for reference only</p>
      </div>
      <div v-if="isDC" style="margin-bottom: 20px; text-align: right;">
        <p style="font-size: 12px; color: #6b7280;">Total Items: <strong>{{ items.length }}</strong> · Total Qty: <strong>{{ items.reduce((s, it) => s + parseFloat(it.quantity || 0), 0) }}</strong></p>
      </div>

      <!-- Totals — right aligned, minimal -->
      <div v-if="!isDC" style="display: flex; gap: 24px; margin-bottom: 24px;">
        <div style="flex: 1;">
          <p style="font-size: 9px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.15em; margin-bottom: 4px;">Amount in Words</p>
          <p style="font-size: 12px; color: #6b7280; font-style: italic;">{{ amountInWords(invoice.total) }}</p>
        </div>
        <div style="width: 200px; border-top: 2px solid #111827; padding-top: 8px;">
          <div style="display: flex; justify-content: space-between; padding: 2px 0; font-size: 12px; color: #6b7280;"><span>Subtotal</span><span>{{ inr(parseFloat(invoice.subtotal||0) + parseFloat(invoice.discount||0)) }}</span></div>
          <div v-if="invoice.discount > 0" style="display: flex; justify-content: space-between; padding: 2px 0; font-size: 12px; color: #15803d;"><span>Discount</span><span>-{{ inr(invoice.discount) }}</span></div>
          <div v-if="invoice.cgst_total > 0" style="display: flex; justify-content: space-between; padding: 2px 0; font-size: 12px; color: #6b7280;"><span>CGST</span><span>{{ inr(invoice.cgst_total) }}</span></div>
          <div v-if="invoice.sgst_total > 0" style="display: flex; justify-content: space-between; padding: 2px 0; font-size: 12px; color: #6b7280;"><span>SGST</span><span>{{ inr(invoice.sgst_total) }}</span></div>
          <div v-if="invoice.igst_total > 0" style="display: flex; justify-content: space-between; padding: 2px 0; font-size: 12px; color: #6b7280;"><span>IGST</span><span>{{ inr(invoice.igst_total) }}</span></div>
          <div style="display: flex; justify-content: space-between; padding: 8px 0 4px; font-size: 18px; font-weight: 700; color: #111827; border-top: 2px solid #111827; margin-top: 4px;"><span>Total</span><span>{{ inr(invoice.total) }}</span></div>
          <div v-if="invoice.amount_paid > 0" style="display: flex; justify-content: space-between; padding: 2px 0; font-size: 12px; color: #15803d;"><span>Paid</span><span>{{ inr(invoice.amount_paid) }}</span></div>
          <div v-if="invoice.amount_due > 0" style="display: flex; justify-content: space-between; padding: 4px 0; font-size: 14px; font-weight: 700; color: #dc2626;"><span>Balance Due</span><span>{{ inr(invoice.amount_due) }}</span></div>
        </div>
      </div>

      <!-- Bank + Notes -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 32px;">
        <div v-if="!isDC && (business?.bank_name || business?.upi_id)">
          <p style="font-size: 9px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.15em; margin-bottom: 6px;">Payment Details</p>
          <div style="display: flex; gap: 16px; align-items: flex-start;">
            <div style="flex: 1; font-size: 11px; color: #6b7280; line-height: 1.8;">
              <div v-if="business.bank_name">{{ business.bank_name }}</div>
              <div v-if="business.bank_account_no" style="font-family: monospace;">A/C: {{ business.bank_account_no }}</div>
              <div v-if="business.bank_ifsc" style="font-family: monospace;">IFSC: {{ business.bank_ifsc }}</div>
              <div v-if="business.upi_id" style="font-family: monospace;">UPI: {{ business.upi_id }}</div>
            </div>
            <div v-if="qrDataUrl && invoice?.amount_due > 0" style="text-align: center;">
              <img :src="qrDataUrl" style="width: 64px; height: 64px; border: 1px solid #e5e7eb;" alt="QR" />
              <p style="font-size: 8px; color: #9ca3af; margin-top: 2px;">Scan to Pay</p>
            </div>
          </div>
        </div>
        <div>
          <div v-if="invoice.notes || business?.invoice_notes" style="margin-bottom: 8px;">
            <p style="font-size: 9px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.15em; margin-bottom: 2px;">Notes</p>
            <p style="font-size: 11px; color: #6b7280;">{{ invoice.notes || business?.invoice_notes }}</p>
          </div>
          <div v-if="invoice.terms || business?.invoice_terms">
            <p style="font-size: 9px; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.15em; margin-bottom: 2px;">Terms</p>
            <p style="font-size: 11px; color: #6b7280;">{{ invoice.terms || business?.invoice_terms }}</p>
          </div>
        </div>
      </div>

      <!-- Signature — minimal -->
      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 40px;">
        <div style="text-align: center;"><div style="border-top: 1px solid #d1d5db; padding-top: 8px; margin-top: 48px;"><p style="font-size: 10px; color: #9ca3af;">Customer Signature</p></div></div>
        <div style="text-align: center;"><div style="border-top: 1px solid #d1d5db; padding-top: 8px; margin-top: 48px;"><p style="font-size: 10px; color: #9ca3af;">Authorised Signatory</p></div></div>
      </div>
    </div>

    <!-- ════════════════════════════════════════════════════════════════ -->
    <!-- TEMPLATE: TRADITIONAL (bordered table format)                   -->
    <!-- ════════════════════════════════════════════════════════════════ -->
    <div v-else-if="invoice && effectiveTpl === 'traditional'" class="invoice-doc trad">
      <!-- Company Header -->
      <table class="trad-full" style="border-bottom: 2px solid #000;">
        <tr>
          <td style="text-align: center; padding: 12px 16px;">
            <div style="display: flex; align-items: center; justify-content: center; gap: 16px;">
              <img v-if="business?.logo" :src="business.logo" style="width: 60px; height: 60px; object-fit: contain;" alt="logo" />
              <div>
                <p style="font-size: 24px; font-weight: 900; font-style: italic; color: #000; letter-spacing: 0.02em;">{{ business?.name || invoice.business_name }}</p>
                <p v-if="business?.address_line1" style="font-size: 11px; color: #333;">{{ business.address_line1 }}<span v-if="business.address_line2">, {{ business.address_line2 }}</span></p>
                <p v-if="business?.city" style="font-size: 11px; color: #333;">{{ [business?.city, business?.state_name, business?.pincode].filter(Boolean).join(' - ') }}<span v-if="business?.state_name"> {{ business.state_name }} Dt.</span></p>
                <p v-if="business?.gstin || invoice.business_gstin" style="font-size: 12px; font-weight: 700; color: #000;">GST IN : {{ business?.gstin || invoice.business_gstin }}</p>
                <p v-if="business?.mobile || business?.email" style="font-size: 11px; color: #333;">
                  <span v-if="business?.mobile">Phone : {{ business.mobile }}</span>
                  <span v-if="business?.mobile && business?.email"> &nbsp; </span>
                  <span v-if="business?.email">Email : {{ business.email }}</span>
                </p>
              </div>
            </div>
          </td>
        </tr>
      </table>

      <!-- Invoice Title -->
      <div style="text-align: center; padding: 8px 0; border-bottom: 1px solid #000;">
        <p style="font-size: 18px; font-weight: 800; letter-spacing: 0.1em;">{{ invoiceTitle.toUpperCase() }}</p>
      </div>

      <!-- To + Invoice Details -->
      <table class="trad-full">
        <tr>
          <td style="padding: 10px 12px; vertical-align: top; width: 55%; border-right: 1px solid #000; border-bottom: 1px solid #000;">
            <p style="font-size: 12px; font-weight: 700;">To</p>
            <p style="font-size: 14px; font-weight: 700; margin-top: 4px;">{{ invoice.client_name || 'Walk-in Customer' }}</p>
            <p v-if="invoice.client_company" style="font-size: 12px;">{{ invoice.client_company }}</p>
            <p v-if="invoice.client_gstin" style="font-size: 11px; margin-top: 2px;">GSTIN: {{ invoice.client_gstin }}</p>
            <p v-if="invoice.client_mobile" style="font-size: 11px;">Mob: {{ invoice.client_mobile }}</p>
          </td>
          <td style="padding: 10px 12px; vertical-align: top; border-bottom: 1px solid #000;">
            <table style="width: 100%; font-size: 12px;">
              <tr><td style="padding: 3px 0; font-weight: 600;">Invoice No</td><td style="padding: 3px 0;">: {{ invoice.number }}</td></tr>
              <tr><td style="padding: 3px 0; font-weight: 600;">Date</td><td style="padding: 3px 0;">: {{ fmtDateShort(invoice.issue_date) }}</td></tr>
              <tr v-if="!isDC"><td style="padding: 3px 0; font-weight: 600;">Due Dt</td><td style="padding: 3px 0;">: {{ fmtDateShort(invoice.due_date) }}</td></tr>
              <tr><td style="padding: 3px 0; font-weight: 600;">Supply</td><td style="padding: 3px 0;">: {{ invoice.place_of_supply_name || invoice.supply_type }}</td></tr>
            </table>
          </td>
        </tr>
      </table>

      <!-- Items Table -->
      <table class="trad-full trad-items">
        <thead>
          <tr>
            <th style="width: 40px;">S.No</th>
            <th style="text-align: left;">Product Name</th>
            <th>HSN Code</th>
            <th>Per UOM</th>
            <th v-if="!isDC" style="text-align: right;">Rate</th>
            <th v-if="hasItemDiscount && !isDC" style="text-align: right;">Disc%</th>
            <th style="text-align: right;">Qty</th>
            <th v-if="!isDC" style="text-align: right;">Amount</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(it, idx) in items" :key="it.id">
            <td style="text-align: center;">{{ idx + 1 }}</td>
            <td style="text-align: left; font-weight: 500;">{{ it.description }}</td>
            <td style="text-align: center; font-family: monospace; font-size: 11px;">{{ it.hsn_sac || '' }}</td>
            <td style="text-align: center;">{{ it.unit || 'Nos' }}</td>
            <td v-if="!isDC" style="text-align: right;">{{ inr(it.unit_price) }}</td>
            <td v-if="hasItemDiscount && !isDC" style="text-align: right; color: #15803d;">{{ parseFloat(it.discount_pct || 0) > 0 ? it.discount_pct + '%' : '—' }}</td>
            <td style="text-align: right;">{{ it.quantity }}</td>
            <td v-if="!isDC" style="text-align: right; font-weight: 600;">{{ inr(it.total) }}</td>
          </tr>
          <!-- Empty rows to fill space -->
          <tr v-for="n in Math.max(0, 8 - items.length)" :key="'empty-'+n">
            <td>&nbsp;</td><td></td><td></td><td></td>
            <td v-if="hasItemDiscount && !isDC"></td>
            <td v-if="!isDC"></td><td></td><td v-if="!isDC"></td>
          </tr>
        </tbody>
      </table>

      <!-- Transport + Totals row -->
      <table v-if="!isDC" class="trad-full" style="border-top: 2px solid #000;">
        <tr>
          <td style="width: 55%; padding: 8px 12px; vertical-align: top; border-right: 1px solid #000; font-size: 12px;">
            <div style="display: flex; gap: 8px;"><span style="font-weight: 600; min-width: 80px;">No.of.Bdl</span><span>:</span></div>
            <div style="display: flex; gap: 8px;"><span style="font-weight: 600; min-width: 80px;">Transport</span><span>:</span></div>
            <div style="display: flex; gap: 8px;"><span style="font-weight: 600; min-width: 80px;">Vehicle No</span><span>:</span></div>
            <div style="display: flex; gap: 8px;"><span style="font-weight: 600; min-width: 80px;">L.R. No</span><span>:</span></div>
          </td>
          <td style="padding: 0; vertical-align: top;">
            <table style="width: 100%; font-size: 12px; border-collapse: collapse;">
              <tr style="border-bottom: 1px solid #ddd;"><td style="padding: 4px 12px;">Total</td><td style="padding: 4px 12px; text-align: right; font-weight: 600;">{{ inr(parseFloat(invoice.subtotal||0) + parseFloat(invoice.discount||0)) }}</td></tr>
              <tr v-if="invoice.discount > 0" style="border-bottom: 1px solid #ddd;"><td style="padding: 4px 12px;">Discount</td><td style="padding: 4px 12px; text-align: right;">-{{ inr(invoice.discount) }}</td></tr>
              <tr v-if="invoice.sgst_total > 0" style="border-bottom: 1px solid #ddd;"><td style="padding: 4px 12px;">SGST</td><td style="padding: 4px 12px; text-align: right;">{{ inr(invoice.sgst_total) }}</td></tr>
              <tr v-if="invoice.cgst_total > 0" style="border-bottom: 1px solid #ddd;"><td style="padding: 4px 12px;">CGST</td><td style="padding: 4px 12px; text-align: right;">{{ inr(invoice.cgst_total) }}</td></tr>
              <tr v-if="invoice.igst_total > 0" style="border-bottom: 1px solid #ddd;"><td style="padding: 4px 12px;">IGST</td><td style="padding: 4px 12px; text-align: right;">{{ inr(invoice.igst_total) }}</td></tr>
              <tr style="border-bottom: 1px solid #ddd;"><td style="padding: 4px 12px;">Round-off</td><td style="padding: 4px 12px; text-align: right;">{{ inr(Math.round(parseFloat(invoice.total||0)) - parseFloat(invoice.total||0)) }}</td></tr>
            </table>
          </td>
        </tr>
      </table>

      <!-- Amount in words + NET AMOUNT -->
      <table v-if="!isDC" class="trad-full" style="border-top: 2px solid #000;">
        <tr>
          <td style="width: 55%; padding: 8px 12px; font-size: 12px; border-right: 1px solid #000;">
            <span style="font-weight: 700;">RS.</span> {{ amountInWords(invoice.total) }}
          </td>
          <td style="padding: 8px 12px; text-align: right;">
            <span style="font-size: 11px; font-weight: 700; background: #000; color: #fff; padding: 3px 10px;">NET-AMOUNT</span>
            <p style="font-size: 18px; font-weight: 900; margin-top: 4px;">{{ inr(Math.round(parseFloat(invoice.total||0))) }}</p>
            <div v-if="invoice.amount_paid > 0" style="font-size: 12px; color: #15803d; margin-top: 2px;">Paid: {{ inr(invoice.amount_paid) }}</div>
            <div v-if="invoice.amount_due > 0" style="font-size: 14px; font-weight: 800; color: #dc2626; margin-top: 2px;">Balance Due: {{ inr(invoice.amount_due) }}</div>
          </td>
        </tr>
      </table>

      <!-- Bank Details -->
      <div v-if="!isDC && (business?.bank_name || business?.upi_id)" style="border-top: 1px solid #000; padding: 10px 12px; text-align: center;">
        <p style="font-size: 13px; font-weight: 800; margin-bottom: 6px;">Company's Bank Details</p>
        <div style="display: flex; justify-content: center; gap: 32px; font-size: 12px;">
          <div style="text-align: left;">
            <div v-if="business?.bank_account_holder || business?.name"><span style="font-weight: 600;">A/C Holder's Name</span> : {{ business?.bank_account_holder || business?.name }}</div>
            <div v-if="business?.bank_account_no"><span style="font-weight: 600;">A/C No</span> : {{ business.bank_account_no }}</div>
          </div>
          <div style="text-align: left;">
            <div v-if="business?.bank_name"><span style="font-weight: 600;">Bank Name</span> : {{ business.bank_name }}</div>
            <div v-if="business?.bank_ifsc"><span style="font-weight: 600;">IFSC/Branch</span> : {{ business.bank_ifsc }}</div>
          </div>
        </div>
        <div v-if="qrDataUrl && invoice?.amount_due > 0" style="margin-top: 8px;">
          <img :src="qrDataUrl" style="width: 72px; height: 72px; margin: 0 auto; border: 1px solid #ddd;" alt="UPI QR" />
          <p style="font-size: 9px; color: #666;">Scan to Pay via UPI</p>
        </div>
      </div>

      <!-- Signatures -->
      <table class="trad-full" style="border-top: 2px solid #000;">
        <tr>
          <td style="width: 33%; padding: 12px; vertical-align: bottom; height: 80px; border-right: 1px solid #000;">
            <div style="border-top: 1px solid #000; padding-top: 4px; text-align: center; font-size: 11px; font-weight: 600;">Receiver's Signature</div>
          </td>
          <td style="width: 34%; padding: 12px; vertical-align: bottom; border-right: 1px solid #000;">
            <div style="border-top: 1px solid #000; padding-top: 4px; text-align: center; font-size: 11px; font-weight: 600;">Checked by</div>
          </td>
          <td style="width: 33%; padding: 12px; vertical-align: top; text-align: right;">
            <p style="font-size: 12px; font-weight: 700; font-style: italic;">For {{ business?.name || invoice.business_name }}</p>
            <div style="height: 50px;"></div>
            <p style="font-size: 11px; font-weight: 600; text-align: right;">Authorised Signatory</p>
          </td>
        </tr>
      </table>

      <!-- Terms -->
      <div style="border-top: 1px solid #000; padding: 8px 12px;">
        <p style="font-size: 11px; font-weight: 700;">Terms & Conditions</p>
        <p v-if="invoice.terms || business?.invoice_terms" style="font-size: 10px; color: #333; margin-top: 2px;">{{ invoice.terms || business?.invoice_terms }}</p>
      </div>

      <!-- Footer -->
      <div style="border-top: 1px solid #000; padding: 6px; text-align: center;">
        <p style="font-size: 10px; font-style: italic; color: #666;">This is a Computer Generated Invoice</p>
      </div>
    </div>

    <!-- Mobile action bar (hidden when printing) -->
    <div v-if="!loading && !error && invoice && isAndroidBluetooth" class="no-print fixed bottom-16 left-4 right-4 z-[99] mx-auto max-w-lg rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-950 shadow-lg">
      To print on the paired PSF588, install a 58mm ESC/POS Bluetooth print service, enable it in Android Settings &gt; Printing, and add the printer there. Then tap Print below and select PSF588. Bluetooth pairing alone does not add it to Android's print list.
    </div>
    <div v-if="!loading && !error && invoice" class="print-actions no-print">
      <button @click="goBack" class="print-action-btn">Back</button>
      <button @click="triggerPrint" :disabled="printing" class="print-action-btn print-action-primary">{{ printing ? 'Preparing…' : 'Print' }}</button>
      <button @click="downloadPdf" class="print-action-btn">PDF</button>
    </div>
  </div>
</template>

<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: white; color: #111; }
.print-page { max-width: 210mm; margin: 0 auto; padding: 20px; padding-bottom: 80px; }
.invoice-doc { background: white; }
body.paper-a3 .print-page { max-width: 297mm; }
body.paper-thermal80 .print-page { width: 80mm; padding: 2mm 3mm; }
body.paper-thermal58 .print-page { width: 58mm; padding: 2mm 3mm; }
.receipt-doc { width: 100%; color: #000; background: #fff; font: 11px/1.35 Arial, sans-serif; overflow-wrap: anywhere; }
.receipt-doc h1 { font-size: 15px; line-height: 1.15; font-weight: 800; margin: 0 0 1mm; }
.receipt-doc p { margin: 1mm 0; }
.receipt-header, .receipt-title, .receipt-thanks { text-align: center; }
.receipt-logo { display: block; max-width: 16mm; max-height: 16mm; object-fit: contain; margin: 0 auto 1mm; }
.receipt-title { font-size: 12px; font-weight: 800; text-transform: uppercase; margin: 1mm 0; }
.receipt-rule { border-top: 1px dashed #000; margin: 2mm 0; }
.receipt-pair { display: flex; justify-content: space-between; align-items: baseline; gap: 2mm; }
.receipt-pair > :last-child { text-align: right; }
.receipt-item { padding: 1.5mm 0; border-bottom: 1px dotted #666; break-inside: avoid; }
.receipt-item > strong { display: block; }
.receipt-item p { font-size: 10px; }
.receipt-total { font-size: 14px; }
.receipt-qr { display: block; width: 22mm; height: 22mm; margin: 2mm auto; image-rendering: pixelated; }
.receipt-thanks { font-weight: 700; margin: 3mm 0 5mm !important; }
/* Traditional template */
.trad { border: 2px solid #000; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #000; }
.trad-full { width: 100%; border-collapse: collapse; }
.trad-full td, .trad-full th { border: none; }
.trad-items th { padding: 8px 6px; font-size: 12px; font-weight: 700; border-bottom: 2px solid #000; border-right: 1px solid #ccc; text-align: center; background: #f9f9f9; }
.trad-items th:last-child { border-right: none; }
.trad-items td { padding: 6px; font-size: 12px; border-bottom: 1px solid #eee; border-right: 1px solid #eee; }
.trad-items td:last-child { border-right: none; }
.trad-items tbody tr:last-child td { border-bottom: none; }
/* Mobile action bar */
.print-actions { position: fixed; bottom: 0; left: 0; right: 0; display: flex; gap: 8px; padding: 12px 16px; background: #1f2937; z-index: 100; }
.print-action-btn { flex: 1; padding: 10px 0; border: 1px solid rgba(255,255,255,0.2); border-radius: 10px; background: rgba(255,255,255,0.1); color: white; font-size: 14px; font-weight: 600; cursor: pointer; }
.print-action-btn:active { opacity: 0.7; }
.print-action-primary { background: #2563eb; border-color: #2563eb; }
@media print {
  .no-print { display: none !important; }
  html, body { width: 100%; height: auto; margin: 0; padding: 0; }
  .print-page { max-width: 100%; width: 100%; padding: 0; padding-bottom: 0; margin: 0; }
  body.paper-thermal58 .print-page { width: 58mm; padding: 2mm 3mm; }
  body.paper-thermal80 .print-page { width: 80mm; padding: 2mm 3mm; }
  /* Multi-page print support */
  .invoice-doc { overflow: visible !important; height: auto !important; }
  .invoice-doc table { page-break-inside: auto; }
  .invoice-doc tr { page-break-inside: avoid; page-break-after: auto; }
  .invoice-doc thead { display: table-header-group; }
  .invoice-doc tfoot { display: table-footer-group; }
  .invoice-doc .grid { break-inside: avoid; }
  .invoice-doc .border-b { break-inside: avoid; }
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
.w-20 { width: 5rem; }
.w-52 { width: 13rem; }
.h-8 { height: 2rem; }
.h-12 { height: 3rem; }
.h-20 { height: 5rem; }
.mb-1 { margin-bottom: 0.25rem; }
.mb-2 { margin-bottom: 0.5rem; }
.mb-4 { margin-bottom: 1rem; }
.mb-6 { margin-bottom: 1.5rem; }
.mt-1 { margin-top: 0.25rem; }
.mt-4 { margin-top: 1rem; }
.mt-8 { margin-top: 2rem; }
.mt-12 { margin-top: 3rem; }
.p-8 { padding: 2rem; }
.px-2 { padding-left: 0.5rem; padding-right: 0.5rem; }
.px-3 { padding-left: 0.75rem; padding-right: 0.75rem; }
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
.bg-amber-50 { background-color: #fffbeb; }
.border-amber-400 { border-color: #fbbf24; }
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
.text-amber-700 { color: #b45309; }
.rounded-lg { border-radius: 0.5rem; }
.border { border: 1px solid #e5e7eb; }
.object-contain { object-fit: contain; }
table { width: 100%; border-collapse: collapse; }
.animate-spin { animation: spin 1s linear infinite; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.w-full { width: 100%; }
</style>
