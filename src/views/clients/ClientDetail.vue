<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { item, list, task } from '../../api'
import { inr } from '../../utils/currency'
import { fmtDateShort } from '../../utils/date'
import { statusBadge, statusLabel } from '../../utils/invoice'
import { useRole } from '../../composables/useRole'

const route  = useRoute()
const router = useRouter()
const emit   = defineEmits(['refresh'])
const { can } = useRole()
const client      = ref(null)
const invoices    = ref([])
const topItems    = ref([])
const loading     = ref(true)
const showDelete  = ref(false)
const deleting    = ref(false)

async function load() {
  try {
    const id = route.params.id
    const [cRes, iRes, tRes] = await Promise.all([
      item('Client', { id }),
      list('Invoice', { 'filter.client_id': id, sort_by: 'i.created_at', sort_order: 'desc' }),
      list('Client:topItems', { client_id: id }),
    ])
    client.value   = cRes.data?.data
    invoices.value = iRes.data?.data || []
    topItems.value = tRes.data?.data || []
  } catch {}
  loading.value = false
}

onMounted(load)

// outstanding_balance now comes directly from the API (Client.entity query)
// This is the same value shown in ClientList, ensuring consistency
const outstanding = () => parseFloat(client.value?.outstanding_balance || 0)

async function deleteClient() {
  deleting.value = true
  try {
    await task('Client', 'delete', { id: route.params.id })
    emit('refresh')
    router.push('/clients')
  } catch {
    deleting.value = false
    showDelete.value = false
  }
}

const avatarColors = [
  'bg-blue-100 text-blue-700', 'bg-emerald-100 text-emerald-700',
  'bg-purple-100 text-purple-700', 'bg-amber-100 text-amber-700',
  'bg-pink-100 text-pink-700', 'bg-teal-100 text-teal-700',
]
const avatarColor = name => avatarColors[(name?.charCodeAt(0) || 0) % avatarColors.length]
</script>

<template>
  <div class="max-w-2xl mx-auto space-y-6 pb-8">
    
    <!-- Top Back Navigation -->
    <div class="flex items-center gap-3 pt-2">
      <button @click="router.push('/clients')" class="p-2 -ml-2 rounded-full hover:bg-gray-100 transition-colors">
        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
      </button>
      <div class="ml-auto flex gap-2">
        <RouterLink v-if="client" :to="`/clients/${client.id}/edit`" class="btn bg-gray-50 text-gray-700 hover:bg-gray-100 shadow-none border border-gray-100 px-4 py-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
          <span class="hidden sm:inline">Edit</span>
        </RouterLink>
      </div>
    </div>

    <div v-if="loading" class="flex justify-center p-12">
      <div class="w-8 h-8 border-4 border-primary-100 border-t-primary-600 rounded-full animate-spin"></div>
    </div>

    <template v-else-if="client">
      
      <!-- HERO: Avatar, Name, Balance -->
      <div class="flex flex-col items-center justify-center text-center animate-fade-in-up">
        <div class="w-24 h-24 sm:w-32 sm:h-32 rounded-full flex items-center justify-center text-4xl sm:text-5xl font-extrabold shadow-soft mb-4" :class="avatarColor(client.name)">
          {{ client.name?.charAt(0)?.toUpperCase() }}
        </div>
        <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-gray-900">{{ client.name }}</h1>
        <p v-if="outstanding() > 0" class="text-danger-600 font-bold mt-2 bg-danger-50 px-4 py-1.5 rounded-full text-sm tracking-wide">
          Owes {{ inr(outstanding()) }}
        </p>
        <p v-else class="text-emerald-600 font-bold mt-2 bg-emerald-50 px-4 py-1.5 rounded-full text-sm tracking-wide">
          No dues!
        </p>
      </div>

      <!-- PRIMARY ACTIONS -->
      <div class="flex justify-center gap-3 w-full max-w-sm mx-auto animate-fade-in-up delay-75">
        <RouterLink :to="`/invoices/new?client_id=${client.id}`" class="flex-1 btn bg-primary-600 text-white hover:bg-primary-700 shadow-soft-blue flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
          <span class="text-xs">Bill</span>
        </RouterLink>
        <a v-if="client.mobile" :href="`tel:${client.mobile}`" class="flex-1 btn bg-gray-50 text-gray-800 border border-gray-100 hover:bg-gray-100 shadow-soft flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
          <span class="text-xs">Call</span>
        </a>
        <RouterLink :to="`/clients/${client.id}/statement`" class="flex-1 btn bg-indigo-50 text-indigo-700 border border-indigo-100 hover:bg-indigo-100 shadow-soft flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          <span class="text-xs">Statement</span>
        </RouterLink>
        <button v-if="can('delete')" @click="showDelete = true" class="flex-1 btn bg-red-50 text-red-600 border border-red-100 hover:bg-red-100 shadow-soft flex flex-col items-center justify-center h-20 gap-1 rounded-[1.5rem]">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
          <span class="text-xs">Delete</span>
        </button>
      </div>

      <!-- Details Card -->
      <div class="card p-2 animate-fade-in-up delay-150 border-0">
        <div class="p-4 space-y-4">
          <div v-if="client.mobile" class="flex items-center gap-4 text-sm text-gray-800 font-medium">
             <div class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center shrink-0">
               <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
             </div>
             <div>
               <p class="text-gray-900 text-base font-bold">{{ client.mobile }}</p>
               <p class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Mobile</p>
             </div>
          </div>
          <div v-if="client.email" class="flex items-center gap-4 text-sm text-gray-800 font-medium">
             <div class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center shrink-0">
               <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
             </div>
             <div>
               <p class="text-gray-900 text-base font-bold">{{ client.email }}</p>
               <p class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Email</p>
             </div>
          </div>
          <div v-if="client.gstin" class="flex items-center gap-4 text-sm text-gray-800 font-medium">
             <div class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center shrink-0">
               <span class="text-gray-400 font-extrabold text-xs">GST</span>
             </div>
             <div>
               <p class="text-gray-900 text-base font-mono font-bold tracking-tight">{{ client.gstin }}</p>
               <p class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">GST Number</p>
             </div>
          </div>
          <div v-if="client.city || client.pincode" class="flex items-center gap-4 text-sm text-gray-800 font-medium">
             <div class="w-10 h-10 rounded-full bg-gray-50 flex items-center justify-center shrink-0">
               <span class="text-xl">📍</span>
             </div>
             <div>
               <p class="text-gray-900 text-base font-bold">{{ [client.city, client.pincode].filter(Boolean).join(' - ') }}</p>
               <p class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Location</p>
             </div>
          </div>
        </div>
      </div>

      <!-- Top Items -->
      <div v-if="topItems.length" class="mt-6 animate-fade-in-up delay-150">
        <h2 class="font-bold text-gray-800 text-sm mb-4 px-1">Top Items Ordered</h2>
        <div class="bg-white rounded-[2rem] shadow-soft border-0 overflow-hidden divide-y divide-gray-50">
          <div v-for="(item, idx) in topItems.slice(0, 5)" :key="item.description"
            class="flex items-center gap-4 px-5 py-4">
            <div class="w-9 h-9 rounded-full bg-indigo-50 flex items-center justify-center shrink-0 text-indigo-600 font-extrabold text-sm">
              {{ idx + 1 }}
            </div>
            <div class="flex-1 min-w-0">
              <p class="font-bold text-gray-900 text-sm truncate">{{ item.description }}</p>
              <p class="text-xs text-gray-500 mt-0.5">{{ item.order_count }} {{ item.order_count == 1 ? 'order' : 'orders' }} · last {{ fmtDateShort(item.last_ordered) }}</p>
            </div>
            <div class="text-right shrink-0">
              <p class="font-extrabold text-gray-900 text-sm">{{ inr(item.total_value) }}</p>
              <p class="text-xs text-gray-500 mt-0.5">qty {{ item.total_qty }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Transaction History (Bills) -->
      <div class="mt-8 animate-fade-in-up delay-200">
        <h2 class="font-bold text-gray-800 text-sm mb-4 px-1">Transaction History</h2>
        
        <div class="bg-white rounded-[2rem] shadow-soft border-0 overflow-hidden divide-y divide-gray-50">
          <div v-for="inv in invoices" :key="inv.id"
            class="flex items-center justify-between px-5 py-4 hover:bg-gray-50 cursor-pointer transition-colors active:bg-gray-100"
            @click="router.push(`/invoices/${inv.id}`)">
            <div class="flex items-center gap-4 min-w-0">
              <div class="w-12 h-12 rounded-full flex items-center justify-center shrink-0" :class="inv.status === 'paid' ? 'bg-emerald-50 text-emerald-500' : 'bg-blue-50 text-blue-500'">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
              </div>
              <div class="min-w-0">
                <p class="font-bold text-gray-900 truncate text-sm">{{ inv.number }}</p>
                <p class="text-xs text-gray-500 mt-0.5">{{ fmtDateShort(inv.issue_date) }}</p>
              </div>
            </div>
            <div class="text-right ml-4 shrink-0 flex flex-col items-end">
              <p class="font-extrabold text-gray-900 text-sm">{{ inr(inv.total) }}</p>
              <p class="text-[9px] font-extrabold mt-1 tracking-wider uppercase px-2 py-0.5 rounded-full" 
                 :class="{'bg-emerald-50 text-emerald-600': inv.status==='paid', 'bg-amber-50 text-amber-600': inv.status==='draft', 'bg-blue-50 text-blue-600': inv.status==='sent', 'bg-red-50 text-red-600': inv.status==='overdue'}">
                {{ inv.status }}
              </p>
            </div>
          </div>
          <div v-if="!invoices.length" class="p-8 text-center text-gray-500 text-sm font-medium">No bills found for this customer.</div>
        </div>
      </div>
    </template>

    <!-- Delete confirmation modal -->
    <div v-if="showDelete" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm">
      <div class="bg-white rounded-[2rem] shadow-xl max-w-sm w-full p-6 space-y-4 text-center">
        <div class="w-16 h-16 rounded-full bg-red-50 flex items-center justify-center mx-auto mb-2 text-3xl">🗑</div>
        <h3 class="text-xl font-extrabold text-gray-900">Delete {{ client?.name }}?</h3>
        <p class="text-sm text-gray-500 leading-relaxed">This will permanently delete this customer and cannot be undone.</p>
        <div class="flex gap-3 pt-4">
          <button @click="showDelete = false" class="btn bg-gray-100 text-gray-700 hover:bg-gray-200 flex-1 border-0" :disabled="deleting">Cancel</button>
          <button @click="deleteClient" class="btn bg-danger-600 text-white hover:bg-danger-700 flex-1 border-0" :disabled="deleting">
            {{ deleting ? 'Deleting…' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
