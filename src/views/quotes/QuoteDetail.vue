<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { item, list, task } from '../../api'
import { inr } from '../../utils/currency'
import { fmtDateShort } from '../../utils/date'

const props = defineProps({ panelId: { type: [String, Number], default: null } })
const emit  = defineEmits(['back', 'refresh'])

const router = useRouter()
const route  = useRoute()

const quote    = ref(null)
const items    = ref([])
const loading  = ref(true)
const acting   = ref('')
const error    = ref('')
const showConvertConfirm = ref(false)
const showDeleteConfirm  = ref(false)
const deleting = ref(false)

const badgeClass = (s) => ({
  draft: 'badge-gray', sent: 'badge-blue', accepted: 'badge-green',
  declined: 'badge-red', expired: 'badge-yellow', converted: 'badge-green',
}[s] || 'badge-gray')

async function load() {
  loading.value = true
  try {
    const id = props.panelId || route.params.id
    const [qRes, iRes] = await Promise.all([
      item('Quote', { id }),
      list('Quote:items', { quote_id: id }),
    ])
    quote.value = qRes.data?.data || null
    items.value = iRes.data?.data || []
  } catch {}
  loading.value = false
}

const quoteId = () => props.panelId || route.params.id

async function act(method, label) {
  acting.value = label
  error.value  = ''
  try {
    await task('Quote', method, { id: quoteId() })
    emit('refresh')
    await load()
  } catch (e) {
    error.value = e.response?.data?.message || 'Action failed.'
  } finally { acting.value = '' }
}

async function convertToInvoice() {
  acting.value = 'convert'
  error.value  = ''
  try {
    const { data } = await task('Quote', 'convertToInvoice', { id: quoteId() })
    emit('refresh')
    router.push('/invoices/' + data.data.invoice_id)
  } catch (e) {
    error.value = e.response?.data?.message || 'Conversion failed.'
    acting.value = ''
    showConvertConfirm.value = false
  }
}

function shareWhatsApp() {
  if (!quote.value) return
  const q = quote.value
  const text = `*${q.type === 'proforma' ? 'Proforma Invoice' : 'Quotation'} ${q.number}*\n` +
    `Dear ${q.client_name},\n\n` +
    `Please find attached your quotation for *${inr(q.total)}*.\n` +
    `Valid Until: ${fmtDateShort(q.valid_until)}\n\n` +
    `Thank you for your interest!`
  window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank')
}

async function deleteQuote() {
  deleting.value = true
  try {
    await task('Quote', 'delete', { id: quoteId() })
    emit('refresh')
    if (props.panelId) { emit('back'); }
    else router.push('/quotes')
  } catch (e) {
    error.value = e.response?.data?.message || 'Delete failed.'
    deleting.value = false
    showDeleteConfirm.value = false
  }
}

watch(() => props.panelId, v => { if (v) load() })
onMounted(load)
</script>

<template>
  <div class="max-w-3xl mx-auto space-y-5">

    <!-- Header -->
    <div class="flex items-center gap-3 pt-2">
      <button @click="router.back()" class="p-2 -ml-2 rounded-full hover:bg-gray-100 transition-colors">
        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
      </button>
    </div>

    <div v-if="loading" class="flex justify-center p-12">
      <div class="w-8 h-8 border-4 border-primary-100 border-t-primary-600 rounded-full animate-spin"></div>
    </div>

    <template v-if="!loading && quote">

      <!-- HERO: Massive Total and Status -->
      <div class="flex flex-col items-center justify-center text-center animate-fade-in-up mt-4 mb-2">
        <div class="w-16 h-16 rounded-full bg-blue-50 text-primary-600 flex items-center justify-center mb-3">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </div>
        <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">{{ quote.client_name }}</p>
        <h1 class="text-5xl font-extrabold tracking-tight text-gray-900">{{ inr(quote.total) }}</h1>
        <div class="flex items-center gap-2 mt-3">
          <p class="text-sm font-semibold text-gray-600">{{ quote.type === 'proforma' ? 'Proforma Invoice' : 'Quotation' }} {{ quote.number }}</p>
          <span :class="badgeClass(quote.status)" class="text-[10px] px-2 py-0.5 rounded-full uppercase tracking-wider font-extrabold">{{ quote.status }}</span>
        </div>
      </div>

      <!-- Action Pills -->
      <div class="flex flex-wrap justify-center gap-2 w-full max-w-lg mx-auto animate-fade-in-up delay-75 mb-6">
        
        <button v-if="quote.status === 'draft'" @click="act('markSent', 'sent')" :disabled="!!acting" class="flex-1 min-w-[120px] btn bg-primary-600 text-white hover:bg-primary-700 shadow-soft-blue flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
          <span class="text-xs">{{ acting === 'sent' ? 'Sending…' : 'Mark Sent' }}</span>
        </button>

        <button v-if="['accepted','sent'].includes(quote.status)" @click="showConvertConfirm = true" :disabled="!!acting" class="flex-1 min-w-[120px] btn bg-emerald-600 text-white hover:bg-emerald-700 shadow-soft flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          <span class="text-xs">Convert to Bill</span>
        </button>

        <button v-if="quote.status === 'sent'" @click="act('accept', 'accept')" :disabled="!!acting" class="flex-1 min-w-[80px] btn bg-emerald-50 text-emerald-700 border border-emerald-100 hover:bg-emerald-100 shadow-soft flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          <span class="text-xs">Accept</span>
        </button>

        <button v-if="quote.status === 'sent'" @click="act('decline', 'decline')" :disabled="!!acting" class="flex-1 min-w-[80px] btn bg-red-50 text-red-700 border border-red-100 hover:bg-red-100 shadow-soft flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          <span class="text-xs">Decline</span>
        </button>

        <button @click="shareWhatsApp" class="flex-1 min-w-[80px] btn bg-green-50 text-green-700 border border-green-100 hover:bg-green-100 shadow-soft flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6 text-green-600" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.137.565 4.147 1.554 5.887L0 24l6.305-1.524A11.94 11.94 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.818 9.818 0 01-5.006-1.375l-.359-.214-3.735.902.948-3.632-.234-.373A9.818 9.818 0 1112 21.818z"/></svg>
          <span class="text-xs">Share</span>
        </button>

        <RouterLink v-if="quote.status === 'draft'" :to="`/quotes/${quote.id}/edit`" class="flex-1 min-w-[80px] btn bg-gray-50 text-gray-800 border border-gray-100 hover:bg-gray-100 shadow-soft flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
          <span class="text-xs">Edit</span>
        </RouterLink>

        <button v-if="quote.status === 'draft'" @click="showDeleteConfirm = true" class="flex-1 min-w-[80px] btn bg-red-50 text-red-600 border border-red-100 hover:bg-red-100 shadow-soft flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
          <span class="text-xs">Delete</span>
        </button>

      </div>
      
      <div v-if="error" class="mb-4 text-sm text-danger-600 bg-danger-50 rounded-lg px-3 py-2 text-center">{{ error }}</div>

      <!-- Quote body -->
      <div class="bg-white rounded-[2rem] shadow-soft border-0 overflow-hidden animate-fade-in-up delay-150">
        <!-- From / To -->
        <div class="grid sm:grid-cols-2 gap-4 p-5 border-b border-gray-100">
          <div>
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Client</p>
            <p class="font-semibold text-gray-900">{{ quote.client_name }}</p>
            <p v-if="quote.client_company" class="text-sm text-gray-600">{{ quote.client_company }}</p>
            <p v-if="quote.client_gstin"   class="text-xs text-gray-400 mt-0.5">GSTIN: {{ quote.client_gstin }}</p>
            <p v-if="quote.client_mobile"  class="text-xs text-gray-400">{{ quote.client_mobile }}</p>
          </div>
          <div class="sm:text-right space-y-1">
            <div class="flex sm:justify-end gap-6 text-sm">
              <div>
                <p class="text-xs text-gray-400">Issue Date</p>
                <p class="font-medium text-gray-800">{{ fmtDateShort(quote.issue_date) }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-400">Valid Until</p>
                <p class="font-medium text-gray-800">{{ fmtDateShort(quote.valid_until) }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Items table -->
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
              <tr>
                <th class="px-4 py-3 text-left">Description</th>
                <th class="px-4 py-3 text-right">Qty</th>
                <th class="px-4 py-3 text-right">Rate</th>
                <th class="px-4 py-3 text-right">GST%</th>
                <th class="px-4 py-3 text-right">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="it in items" :key="it.id">
                <td class="px-4 py-3">
                  <p class="font-medium text-gray-800">{{ it.description }}</p>
                  <p v-if="it.hsn_sac" class="text-xs text-gray-400">HSN/SAC: {{ it.hsn_sac }}</p>
                </td>
                <td class="px-4 py-3 text-right text-gray-600">{{ it.quantity }} {{ it.unit }}</td>
                <td class="px-4 py-3 text-right text-gray-600">{{ inr(it.unit_price) }}</td>
                <td class="px-4 py-3 text-right text-gray-600">{{ it.gst_rate }}%</td>
                <td class="px-4 py-3 text-right font-medium text-gray-900">{{ inr(it.total) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Totals -->
        <div class="p-5 border-t border-gray-100">
          <div class="max-w-xs ml-auto space-y-1.5 text-sm">
            <div class="flex justify-between text-gray-600"><span>Subtotal</span><span>{{ inr(quote.subtotal) }}</span></div>
            <div v-if="quote.cgst_total > 0" class="flex justify-between text-gray-600"><span>CGST</span><span>{{ inr(quote.cgst_total) }}</span></div>
            <div v-if="quote.sgst_total > 0" class="flex justify-between text-gray-600"><span>SGST</span><span>{{ inr(quote.sgst_total) }}</span></div>
            <div v-if="quote.igst_total > 0" class="flex justify-between text-gray-600"><span>IGST</span><span>{{ inr(quote.igst_total) }}</span></div>
            <div class="flex justify-between font-bold text-base text-gray-900 border-t border-gray-200 pt-2">
              <span>Total</span><span>{{ inr(quote.total) }}</span>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Delete confirm -->
    <div v-if="showDeleteConfirm" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
      <div class="bg-white rounded-[2rem] w-full max-w-sm shadow-xl p-6 text-center space-y-4">
        <div class="w-16 h-16 rounded-full bg-red-50 flex items-center justify-center mx-auto mb-2 text-3xl">🗑</div>
        <h3 class="text-xl font-extrabold text-gray-900">Delete Quote?</h3>
        <p class="text-sm text-gray-500">This will permanently delete quote <strong>{{ quote?.number }}</strong>.</p>
        <div class="flex gap-3 pt-2">
          <button @click="showDeleteConfirm = false" class="btn bg-gray-100 text-gray-700 hover:bg-gray-200 flex-1 border-0" :disabled="deleting">Cancel</button>
          <button @click="deleteQuote" :disabled="deleting" class="btn bg-danger-600 text-white hover:bg-danger-700 flex-1 border-0">
            {{ deleting ? 'Deleting…' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Convert confirm -->
    <div v-if="showConvertConfirm" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm">
      <div class="bg-white rounded-[2rem] w-full max-w-sm shadow-xl p-6 text-center space-y-4">
        <div class="w-16 h-16 rounded-full bg-primary-50 text-primary-600 flex items-center justify-center mx-auto mb-2">
           <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        </div>
        <h3 class="text-xl font-extrabold text-gray-900">Convert to Bill?</h3>
        <p class="text-sm text-gray-500">This will create a new bill from this quotation. The quotation will be marked as Converted.</p>
        <div class="flex gap-3 pt-2">
          <button @click="showConvertConfirm = false" class="btn bg-gray-100 text-gray-700 hover:bg-gray-200 flex-1 border-0">Cancel</button>
          <button @click="convertToInvoice" :disabled="acting === 'convert'" class="btn bg-primary-600 text-white hover:bg-primary-700 flex-1 border-0">
            {{ acting === 'convert' ? 'Converting…' : 'Convert' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
