<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { item, list, task } from '../../api'
import { fmtDateShort } from '../../utils/date'

const route  = useRoute()
const router = useRouter()
const emit   = defineEmits(['refresh'])

const dc      = ref(null)
const items   = ref([])
const loading = ref(true)
const acting  = ref(false)
const actError = ref('')

async function load() {
  loading.value = true
  try {
    const id = route.params.id
    const [dcRes, itmRes] = await Promise.all([
      item('DeliveryChallan', { id }),
      list('DeliveryChallan:items', { dc_id: id }),
    ])
    dc.value    = dcRes.data?.data
    items.value = itmRes.data?.data || []
  } catch {}
  loading.value = false
}

async function doAction(action) {
  acting.value   = true
  actError.value = ''
  try {
    await task('DeliveryChallan', action, { id: dc.value.id })
    emit('refresh')
    await load()
  } catch (e) {
    actError.value = e.response?.data?.message || 'Action failed.'
  } finally { acting.value = false }
}

onMounted(load)

const badgeClass  = s => ({ draft: 'badge-gray', issued: 'badge-blue', delivered: 'badge-green', cancelled: 'badge-red' }[s] || 'badge-gray')
const statusLabel = s => ({ draft: 'Draft', issued: 'Issued', delivered: 'Delivered', cancelled: 'Cancelled' }[s] || s)
</script>

<template>
  <div class="max-w-3xl mx-auto space-y-5 pb-10">

    <!-- Back -->
    <div class="flex items-center gap-3 pt-2">
      <button @click="router.push('/delivery-challans')" class="p-2 -ml-2 rounded-full hover:bg-gray-100 transition-colors">
        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
      </button>
    </div>

    <div v-if="loading" class="flex justify-center p-12">
      <div class="w-8 h-8 border-4 border-primary-100 border-t-primary-600 rounded-full animate-spin"></div>
    </div>

    <template v-else-if="dc">

      <!-- Hero -->
      <div class="flex flex-col items-center text-center animate-fade-in-up mt-4 mb-2">
        <div class="w-16 h-16 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center mb-3">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
        </div>
        <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-1">{{ dc.client_name }}</p>
        <h1 class="text-3xl font-extrabold tracking-tight text-gray-900">{{ dc.number }}</h1>
        <div class="flex items-center gap-2 mt-3">
          <p class="text-sm font-semibold text-gray-600">{{ fmtDateShort(dc.challan_date) }}</p>
          <span :class="badgeClass(dc.status)" class="text-[10px] px-2 py-0.5">{{ statusLabel(dc.status) }}</span>
        </div>
      </div>

      <!-- Action Pills -->
      <div class="flex flex-wrap justify-center gap-2 w-full max-w-lg mx-auto animate-fade-in-up mb-4">
        <button v-if="dc.status === 'draft'" @click="doAction('issue')" :disabled="acting"
          class="flex-1 min-w-[100px] btn bg-primary-600 text-white hover:bg-primary-700 shadow-soft-blue flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
          <span class="text-xs">Issue</span>
        </button>

        <button v-if="dc.status === 'issued'" @click="doAction('deliver')" :disabled="acting"
          class="flex-1 min-w-[100px] btn bg-emerald-600 text-white hover:bg-emerald-700 shadow-soft flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          <span class="text-xs">Mark Delivered</span>
        </button>

        <RouterLink v-if="dc.status === 'draft'" :to="`/delivery-challans/${dc.id}/edit`"
          class="flex-1 min-w-[100px] btn bg-gray-50 text-gray-800 border border-gray-100 hover:bg-gray-100 shadow-soft flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
          <span class="text-xs">Edit</span>
        </RouterLink>

        <button @click="window.print()"
          class="flex-1 min-w-[100px] btn bg-gray-50 text-gray-800 border border-gray-100 hover:bg-gray-100 shadow-soft flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
          <span class="text-xs">Print</span>
        </button>

        <button v-if="dc.status !== 'delivered' && dc.status !== 'cancelled'" @click="doAction('cancel')" :disabled="acting"
          class="flex-1 min-w-[100px] btn bg-red-50 text-red-600 border border-red-100 hover:bg-red-100 shadow-soft flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
          <span class="text-xs">Cancel</span>
        </button>
      </div>

      <div v-if="actError" class="text-sm text-danger-600 bg-danger-50 rounded-xl px-4 py-3">{{ actError }}</div>

      <!-- DC Document -->
      <div class="bg-white rounded-[2rem] shadow-soft border-0 overflow-hidden animate-fade-in-up anim-delay-75">

        <!-- Header -->
        <div class="px-5 pt-5 pb-4 border-b border-gray-200 space-y-3">
          <div class="flex items-center justify-between">
            <p class="text-xl font-black text-indigo-700 uppercase tracking-widest">Delivery Challan</p>
            <p class="text-sm font-bold text-gray-700">{{ dc.number }}</p>
          </div>
        </div>

        <!-- Customer + DC Details -->
        <div class="grid grid-cols-2 border-b border-gray-200">
          <div class="px-5 py-4 border-r border-gray-200">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Customer</p>
            <p class="font-bold text-gray-900 text-sm">{{ dc.client_name }}</p>
            <p v-if="dc.client_gstin" class="text-[11px] text-gray-500 font-mono mt-1">GSTIN: {{ dc.client_gstin }}</p>
            <p v-if="dc.client_mobile" class="text-[11px] text-gray-500 mt-0.5">{{ dc.client_mobile }}</p>
          </div>
          <div class="px-5 py-4">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Challan Details</p>
            <table class="text-xs w-full">
              <tr><td class="text-gray-400 pb-1 pr-3">DC Number</td><td class="font-semibold text-gray-800 pb-1">{{ dc.number }}</td></tr>
              <tr><td class="text-gray-400 pb-1 pr-3">Date</td><td class="font-medium text-gray-700 pb-1">{{ fmtDateShort(dc.challan_date) }}</td></tr>
              <tr v-if="dc.vehicle_no"><td class="text-gray-400 pb-1 pr-3">Vehicle No.</td><td class="font-medium text-gray-700 pb-1">{{ dc.vehicle_no }}</td></tr>
              <tr v-if="dc.driver_name"><td class="text-gray-400 pr-3">Driver</td><td class="font-medium text-gray-700">{{ dc.driver_name }}</td></tr>
              <tr v-if="dc.destination"><td class="text-gray-400 pr-3 pt-1">Destination</td><td class="font-medium text-gray-700 pt-1">{{ dc.destination }}</td></tr>
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
                <th class="px-3 py-2.5 text-right text-xs font-semibold">Unit</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="(it, idx) in items" :key="it.id">
                <td class="px-3 py-3 text-gray-400 text-xs">{{ idx + 1 }}</td>
                <td class="px-3 py-3">
                  <p class="font-medium text-gray-800">{{ it.description }}</p>
                </td>
                <td class="px-3 py-3 text-center font-mono text-xs text-gray-500">{{ it.hsn_sac || '—' }}</td>
                <td class="px-3 py-3 text-right font-semibold text-gray-900">{{ it.quantity }}</td>
                <td class="px-3 py-3 text-right text-xs text-gray-600">{{ it.unit }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Signature block -->
        <div class="px-5 py-6 border-t border-gray-200 grid grid-cols-2 gap-4">
          <div class="text-center">
            <div class="border-t border-gray-300 pt-2 mt-10">
              <p class="text-xs font-semibold text-gray-500">Receiver's Signature</p>
            </div>
          </div>
          <div class="text-center">
            <div class="border-t border-gray-300 pt-2 mt-10">
              <p class="text-xs font-semibold text-gray-500">Authorised Signatory</p>
            </div>
          </div>
        </div>

        <!-- Notes -->
        <div v-if="dc.notes" class="px-5 pb-5 border-t border-gray-100 pt-4">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Notes</p>
          <p class="text-sm text-gray-600">{{ dc.notes }}</p>
        </div>
      </div>

    </template>
  </div>
</template>
