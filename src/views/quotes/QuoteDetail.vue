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

async function act(method, label) {
  acting.value = label
  error.value  = ''
  try {
    await task('Quote', method, { id: route.params.id })
    await load()
  } catch (e) {
    error.value = e.response?.data?.message || 'Action failed.'
  } finally { acting.value = '' }
}

async function convertToInvoice() {
  acting.value = 'convert'
  error.value  = ''
  try {
    const { data } = await task('Quote', 'convertToInvoice', { id: route.params.id })
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
    await task('Quote', 'delete', { id: route.params.id })
    router.push('/quotes')
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
    <div class="flex items-center gap-3">
      <button @click="router.back()" class="p-2 rounded-lg hover:bg-gray-100">
        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      </button>
      <h1 class="page-title">Quotation Details</h1>
    </div>

    <div v-if="loading" class="card p-10 text-center text-gray-400 text-sm">Loading…</div>

    <template v-if="!loading && quote">

      <!-- Status + actions -->
      <div class="card card-body">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <div class="flex items-center gap-2">
              <p class="font-bold text-gray-900 text-lg">{{ quote.number }}</p>
              <span :class="badgeClass(quote.status)">{{ quote.status }}</span>
            </div>
            <p class="text-sm text-gray-500 mt-0.5">{{ quote.type === 'proforma' ? 'Proforma Invoice' : 'Quotation' }} · {{ fmtDateShort(quote.issue_date) }}</p>
          </div>
          <div class="text-right">
            <p class="text-2xl font-bold text-gray-900">{{ inr(quote.total) }}</p>
          </div>
        </div>

        <!-- Action buttons -->
        <div class="flex flex-wrap gap-2 mt-4">
          <button v-if="quote.status === 'draft'" @click="act('markSent', 'sent')"
            :disabled="!!acting" class="btn-primary btn-sm">
            {{ acting === 'sent' ? 'Sending…' : 'Mark as Sent to Customer' }}
          </button>
          <RouterLink v-if="quote.status === 'draft'" :to="`/quotes/${quote.id}/edit`" class="btn-outline btn-sm">
            Edit
          </RouterLink>
          <button v-if="quote.status === 'draft'" @click="showDeleteConfirm = true"
            class="btn-outline btn-sm text-danger-600 border-danger-300 hover:bg-danger-50">
            Delete
          </button>
          <button v-if="quote.status === 'sent'" @click="act('accept', 'accept')"
            :disabled="!!acting" class="btn-sm bg-green-600 text-white rounded-lg px-3 py-1.5 text-sm font-medium">
            {{ acting === 'accept' ? 'Saving…' : 'Customer Accepted' }}
          </button>
          <button v-if="quote.status === 'sent'" @click="act('decline', 'decline')"
            :disabled="!!acting" class="btn-outline btn-sm">
            {{ acting === 'decline' ? 'Saving…' : 'Customer Declined' }}
          </button>
          <button v-if="['accepted','sent'].includes(quote.status)" @click="showConvertConfirm = true"
            :disabled="!!acting" class="btn-sm bg-primary-600 text-white rounded-lg px-3 py-1.5 text-sm font-medium">
            Convert to Bill
          </button>
          <button @click="shareWhatsApp" class="btn-outline btn-sm">
            📲 WhatsApp
          </button>
        </div>

        <div v-if="error" class="mt-3 text-sm text-danger-600 bg-danger-50 rounded-lg px-3 py-2">{{ error }}</div>
      </div>

      <!-- Quote body -->
      <div class="card">
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
    <div v-if="showDeleteConfirm" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
      <div class="bg-white rounded-2xl w-full max-w-sm shadow-xl p-6 space-y-4">
        <h3 class="font-semibold text-gray-900">Delete Quote?</h3>
        <p class="text-sm text-gray-600">This will permanently delete quote <strong>{{ quote?.number }}</strong>.</p>
        <div class="flex gap-3">
          <button @click="showDeleteConfirm = false" class="btn-outline flex-1" :disabled="deleting">Cancel</button>
          <button @click="deleteQuote" :disabled="deleting" class="btn-primary flex-1 bg-danger-600 hover:bg-danger-700 border-danger-600">
            {{ deleting ? 'Deleting…' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Convert confirm -->
    <div v-if="showConvertConfirm" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
      <div class="bg-white rounded-2xl w-full max-w-sm shadow-xl p-6 space-y-4">
        <h3 class="font-semibold text-gray-900">Convert to Bill?</h3>
        <p class="text-sm text-gray-600">This will create a new bill from this quotation. The quotation will be marked as Converted.</p>
        <div class="flex gap-3">
          <button @click="showConvertConfirm = false" class="btn-outline flex-1">Cancel</button>
          <button @click="convertToInvoice" :disabled="acting === 'convert'" class="btn-primary flex-1">
            {{ acting === 'convert' ? 'Converting…' : 'Convert' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
