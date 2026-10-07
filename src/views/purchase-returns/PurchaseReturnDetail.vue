<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { item, list, task } from '../../api'
import { inr } from '../../utils/currency'
import { fmtDateShort } from '../../utils/date'

const route  = useRoute()
const router = useRouter()
const emit   = defineEmits(['refresh'])

const pr      = ref(null)
const items   = ref([])
const loading = ref(true)
const acting  = ref(false)
const actError = ref('')

async function load() {
  loading.value = true
  try {
    const id = route.params.id
    const [prRes, itmRes] = await Promise.all([
      item('PurchaseReturn', { id }),
      list('PurchaseReturn:items', { pr_id: id }),
    ])
    pr.value    = prRes.data?.data
    items.value = itmRes.data?.data || []
  } catch {}
  loading.value = false
}

async function doAction(action) {
  acting.value  = true
  actError.value = ''
  try {
    await task('PurchaseReturn', action, { id: pr.value.id })
    emit('refresh')
    await load()
  } catch (e) {
    actError.value = e.response?.data?.message || 'Action failed.'
  } finally { acting.value = false }
}

onMounted(load)

const badgeClass  = s => ({ draft: 'badge-gray', issued: 'badge-blue', adjusted: 'badge-green' }[s] || 'badge-gray')
const statusLabel = s => ({ draft: 'Draft', issued: 'Issued', adjusted: 'Adjusted' }[s] || s)
const reasonLabel = r => ({ defective: 'Defective', expired: 'Expired', excess: 'Excess Qty', wrong_item: 'Wrong Item', other: 'Other' }[r] || r)
</script>

<template>
  <div class="gpay-screen px-4 py-4 max-w-3xl lg:mx-auto space-y-5 pb-10">

    <div class="flex items-center gap-3 pt-2">
      <button @click="router.push('/purchase-returns')" class="p-2 -ml-2 rounded-full hover:bg-gray-100 transition-colors">
        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
      </button>
    </div>

    <div v-if="loading" class="flex justify-center p-12">
      <div class="w-8 h-8 border-4 border-primary-100 border-t-primary-600 rounded-full animate-spin"></div>
    </div>

    <template v-else-if="pr">

      <!-- Hero -->
      <div class="flex flex-col items-center text-center animate-fade-in-up mt-4 mb-2">
        <div class="w-16 h-16 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center mb-3">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
        </div>
        <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">{{ pr.supplier_name }}</p>
        <h1 class="text-5xl font-extrabold tracking-tight text-gray-900">{{ inr(pr.total) }}</h1>
        <div class="flex items-center gap-2 mt-3">
          <p class="text-sm font-semibold text-gray-600">{{ pr.number }}</p>
          <span :class="badgeClass(pr.status)" class="text-[10px] px-2 py-0.5">{{ statusLabel(pr.status) }}</span>
        </div>
        <p class="text-xs text-gray-500 mt-1">{{ reasonLabel(pr.reason) }} · Bill {{ pr.pi_number }}</p>
      </div>

      <!-- Action Pills -->
      <div class="flex flex-wrap justify-center gap-2 w-full max-w-lg mx-auto animate-fade-in-up mb-4">
        <button v-if="pr.status === 'draft'" @click="doAction('issue')" :disabled="acting"
          class="flex-1 min-w-[100px] btn bg-primary-600 text-white hover:bg-primary-700 shadow-gpay flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          <span class="text-xs">Issue & Reverse Stock</span>
        </button>

        <button v-if="pr.status === 'issued'" @click="doAction('adjust')" :disabled="acting"
          class="flex-1 min-w-[100px] btn bg-emerald-600 text-white hover:bg-emerald-700 shadow-soft flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1"/></svg>
          <span class="text-xs">Adjust Balance</span>
        </button>

        <button v-if="pr.status === 'draft'" @click="doAction('delete')" :disabled="acting"
          class="flex-1 min-w-[100px] btn bg-red-50 text-red-600 border border-red-100 hover:bg-red-100 shadow-soft flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
          <span class="text-xs">Delete</span>
        </button>
      </div>

      <div v-if="actError" class="text-sm text-danger-600 bg-danger-50 rounded-xl px-4 py-3">{{ actError }}</div>

      <!-- Document -->
      <div class="bg-white rounded-[2rem] shadow-soft border-0 overflow-hidden animate-fade-in-up anim-delay-75">

        <div class="px-5 pt-5 pb-4 border-b border-gray-200 space-y-3">
          <div class="flex items-center justify-between">
            <p class="text-xl font-black text-amber-700 uppercase tracking-widest">Purchase Return</p>
            <p class="text-sm font-bold text-gray-700">{{ pr.number }}</p>
          </div>
        </div>

        <div class="grid grid-cols-2 border-b border-gray-200">
          <div class="px-5 py-4 border-r border-gray-200">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Supplier</p>
            <p class="font-bold text-gray-900 text-sm">{{ pr.supplier_name }}</p>
            <p v-if="pr.supplier_gstin" class="text-[11px] text-gray-500 font-mono mt-1">GSTIN: {{ pr.supplier_gstin }}</p>
          </div>
          <div class="px-5 py-4">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Return Details</p>
            <table class="text-xs w-full">
              <tr><td class="text-gray-400 pb-1 pr-3">Return No.</td><td class="font-semibold text-gray-800 pb-1">{{ pr.number }}</td></tr>
              <tr><td class="text-gray-400 pb-1 pr-3">Return Date</td><td class="font-medium text-gray-700 pb-1">{{ fmtDateShort(pr.return_date) }}</td></tr>
              <tr><td class="text-gray-400 pb-1 pr-3">Reason</td><td class="font-medium text-gray-700 pb-1">{{ reasonLabel(pr.reason) }}</td></tr>
              <tr><td class="text-gray-400 pr-3">Against Bill</td><td class="font-medium text-gray-700">{{ pr.pi_number }}</td></tr>
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
                  <p v-if="it.batch_no || it.expiry_date" class="text-[10px] text-gray-400 mt-0.5">
                    <span v-if="it.batch_no">Batch: {{ it.batch_no }}</span>
                    <span v-if="it.batch_no && it.expiry_date"> · </span>
                    <span v-if="it.expiry_date">Exp: {{ fmtDateShort(it.expiry_date) }}</span>
                  </p>
                </td>
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
            <div class="flex justify-between text-gray-600"><span>Subtotal</span><span>{{ inr(pr.subtotal) }}</span></div>
            <div v-if="pr.cgst_total > 0" class="flex justify-between text-gray-600"><span>CGST</span><span>{{ inr(pr.cgst_total) }}</span></div>
            <div v-if="pr.sgst_total > 0" class="flex justify-between text-gray-600"><span>SGST</span><span>{{ inr(pr.sgst_total) }}</span></div>
            <div v-if="pr.igst_total > 0" class="flex justify-between text-gray-600"><span>IGST</span><span>{{ inr(pr.igst_total) }}</span></div>
            <div class="flex justify-between font-bold text-base text-gray-900 border-t border-gray-200 pt-2">
              <span>Return Total</span><span>{{ inr(pr.total) }}</span>
            </div>
          </div>
        </div>

        <div v-if="pr.notes" class="px-5 pb-5 border-t border-gray-100 pt-4">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Notes</p>
          <p class="text-sm text-gray-600">{{ pr.notes }}</p>
        </div>
      </div>

    </template>
  </div>
</template>
