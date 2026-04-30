<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { item, list, task } from '../../api'
import { inr } from '../../utils/currency'
import { fmtDateShort } from '../../utils/date'

const route  = useRoute()
const router = useRouter()
const emit   = defineEmits(['refresh'])

const po      = ref(null)
const items   = ref([])
const loading = ref(true)
const acting  = ref(false)
const actError = ref('')

async function load() {
  loading.value = true
  try {
    const id = route.params.id
    const [poRes, itmRes] = await Promise.all([
      item('PurchaseOrder', { id }),
      list('PurchaseOrder:items', { po_id: id }),
    ])
    po.value    = poRes.data?.data
    items.value = itmRes.data?.data || []
  } catch {}
  loading.value = false
}

async function doAction(action) {
  acting.value  = true
  actError.value = ''
  try {
    await task('PurchaseOrder', action, { id: po.value.id })
    emit('refresh')
    await load()
  } catch (e) {
    actError.value = e.response?.data?.message || 'Action failed.'
  } finally { acting.value = false }
}

onMounted(load)

const badgeClass = s => ({ draft: 'badge-gray', sent: 'badge-blue', received: 'badge-green', cancelled: 'badge-red' }[s] || 'badge-gray')
const statusLabel = s => ({ draft: 'Draft', sent: 'Sent', received: 'Received', cancelled: 'Cancelled' }[s] || s)
</script>

<template>
  <div class="max-w-3xl mx-auto space-y-5 pb-10">

    <!-- Back -->
    <div class="flex items-center gap-3 pt-2">
      <button @click="router.push('/purchase-orders')" class="p-2 -ml-2 rounded-full hover:bg-gray-100 transition-colors">
        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
      </button>
    </div>

    <div v-if="loading" class="flex justify-center p-12">
      <div class="w-8 h-8 border-4 border-primary-100 border-t-primary-600 rounded-full animate-spin"></div>
    </div>

    <template v-else-if="po">

      <!-- Hero -->
      <div class="flex flex-col items-center text-center animate-fade-in-up mt-4 mb-2">
        <div class="w-16 h-16 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center mb-3">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        </div>
        <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">{{ po.supplier_name }}</p>
        <h1 class="text-5xl font-extrabold tracking-tight text-gray-900">{{ inr(po.total) }}</h1>
        <div class="flex items-center gap-2 mt-3">
          <p class="text-sm font-semibold text-gray-600">{{ po.number }}</p>
          <span :class="badgeClass(po.status)" class="text-[10px] px-2 py-0.5">{{ statusLabel(po.status) }}</span>
        </div>
      </div>

      <!-- Action Pills -->
      <div class="flex flex-wrap justify-center gap-2 w-full max-w-lg mx-auto animate-fade-in-up mb-4">
        <button v-if="po.status === 'draft'" @click="doAction('send')" :disabled="acting"
          class="flex-1 min-w-[100px] btn bg-primary-600 text-white hover:bg-primary-700 shadow-soft-blue flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
          <span class="text-xs">Mark Sent</span>
        </button>

        <button v-if="po.status === 'sent'" @click="doAction('receive')" :disabled="acting"
          class="flex-1 min-w-[100px] btn bg-emerald-600 text-white hover:bg-emerald-700 shadow-soft flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          <span class="text-xs">Mark Received</span>
        </button>

        <RouterLink v-if="po.status === 'draft'" :to="`/purchase-orders/${po.id}/edit`"
          class="flex-1 min-w-[100px] btn bg-gray-50 text-gray-800 border border-gray-100 hover:bg-gray-100 shadow-soft flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
          <span class="text-xs">Edit</span>
        </RouterLink>

        <button @click="window.print()"
          class="flex-1 min-w-[100px] btn bg-gray-50 text-gray-800 border border-gray-100 hover:bg-gray-100 shadow-soft flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
          <span class="text-xs">Print</span>
        </button>

        <button v-if="po.status !== 'received' && po.status !== 'cancelled'" @click="doAction('cancel')" :disabled="acting"
          class="flex-1 min-w-[100px] btn bg-red-50 text-red-600 border border-red-100 hover:bg-red-100 shadow-soft flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
          <span class="text-xs">Cancel</span>
        </button>
      </div>

      <div v-if="actError" class="text-sm text-danger-600 bg-danger-50 rounded-xl px-4 py-3">{{ actError }}</div>

      <!-- PO Document -->
      <div class="bg-white rounded-[2rem] shadow-soft border-0 overflow-hidden animate-fade-in-up anim-delay-75">

        <!-- Header -->
        <div class="px-5 pt-5 pb-4 border-b border-gray-200 space-y-3">
          <div class="flex items-center justify-between">
            <p class="text-xl font-black text-indigo-700 uppercase tracking-widest">Purchase Order</p>
            <p class="text-sm font-bold text-gray-700">{{ po.number }}</p>
          </div>
        </div>

        <!-- Supplier + PO Details -->
        <div class="grid grid-cols-2 border-b border-gray-200">
          <div class="px-5 py-4 border-r border-gray-200">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Supplier</p>
            <p class="font-bold text-gray-900 text-sm">{{ po.supplier_name }}</p>
            <p v-if="po.supplier_gstin" class="text-[11px] text-gray-500 font-mono mt-1">GSTIN: {{ po.supplier_gstin }}</p>
            <p v-if="po.supplier_mobile" class="text-[11px] text-gray-500 mt-0.5">{{ po.supplier_mobile }}</p>
          </div>
          <div class="px-5 py-4">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Order Details</p>
            <table class="text-xs w-full">
              <tr><td class="text-gray-400 pb-1 pr-3">PO Number</td><td class="font-semibold text-gray-800 pb-1">{{ po.number }}</td></tr>
              <tr><td class="text-gray-400 pb-1 pr-3">Order Date</td><td class="font-medium text-gray-700 pb-1">{{ fmtDateShort(po.order_date) }}</td></tr>
              <tr><td class="text-gray-400 pr-3">Expected By</td><td class="font-medium text-gray-700">{{ fmtDateShort(po.expected_date) }}</td></tr>
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
                <td class="px-3 py-3">
                  <p class="font-medium text-gray-800">{{ it.description }}</p>
                  <p v-if="it.unit" class="text-xs text-gray-400">{{ it.unit }}</p>
                </td>
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
            <div class="flex justify-between text-gray-600"><span>Subtotal</span><span>{{ inr(po.subtotal) }}</span></div>
            <div v-if="po.tax_total > 0" class="flex justify-between text-gray-600"><span>GST</span><span>{{ inr(po.tax_total) }}</span></div>
            <div class="flex justify-between font-bold text-base text-gray-900 border-t border-gray-200 pt-2">
              <span>Total</span><span>{{ inr(po.total) }}</span>
            </div>
          </div>
        </div>

        <!-- Notes -->
        <div v-if="po.notes" class="px-5 pb-5 border-t border-gray-100 pt-4">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Notes</p>
          <p class="text-sm text-gray-600">{{ po.notes }}</p>
        </div>
      </div>

    </template>
  </div>
</template>
