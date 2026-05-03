<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { list } from '../../api'
import { inr } from '../../utils/currency'
import { fmtDateShort } from '../../utils/date'

const router  = useRouter()
const challans = ref([])
const loading  = ref(true)
const filter   = ref({ status: '', search: '' })
let timer = null

const tabs = [
  { label: 'All',       value: '' },
  { label: 'Draft',     value: 'draft' },
  { label: 'Issued',    value: 'issued' },
  { label: 'Delivered', value: 'delivered' },
]

const badgeClass  = s => ({ draft: 'badge-gray', issued: 'badge-blue', delivered: 'badge-green', cancelled: 'badge-red' }[s] || 'badge-gray')
const statusLabel = s => ({ draft: 'Draft', issued: 'Issued', delivered: 'Delivered', cancelled: 'Cancelled' }[s] || s)

const avatarColors = ['bg-blue-100 text-blue-700', 'bg-emerald-100 text-emerald-700', 'bg-purple-100 text-purple-700', 'bg-amber-100 text-amber-700', 'bg-pink-100 text-pink-700']
const avatarColor  = name => avatarColors[(name?.charCodeAt(0) || 0) % avatarColors.length]

async function load() {
  loading.value = true
  try {
    const p = { sort_by: 'dc.created_at', sort_order: 'desc' }
    if (filter.value.status) p['filter.status'] = filter.value.status
    if (filter.value.search) p['filter.search'] = `%${filter.value.search}%`
    const { data } = await list('DeliveryChallan', p)
    challans.value = data.data || []
  } catch {}
  loading.value = false
}

function onSearch() { clearTimeout(timer); timer = setTimeout(load, 350) }
onMounted(load)
</script>

<template>
  <div class="flex flex-col lg:flex-row gap-6 h-full min-h-0">
    <!-- Left Pane -->
    <div :class="{ 'hidden lg:flex': $route.name !== 'DeliveryChallans', 'w-full lg:w-[35%] flex flex-col min-h-0': true }">
      <div class="flex flex-col gap-2 pr-1 shrink-0 z-10 relative">
        <!-- Compact Header -->
        <div class="flex items-center justify-between gap-3">
          <h1 class="page-title flex items-center gap-2">Delivery Challans <HelpIcon section="delivery-challans" /></h1>
          <div class="flex items-center gap-2">
            <!-- Mobile only New DC -->
            <RouterLink to="/delivery-challans/new" class="lg:hidden p-2 text-white bg-primary-600 hover:bg-primary-700 rounded-full transition-colors shadow-soft-blue">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            </RouterLink>
          </div>
        </div>

        <!-- Search Row -->
        <div class="flex gap-2 animate-fade-in-up z-10 relative">
          <div class="relative flex-1 min-w-0">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input v-model="filter.search" @input="onSearch" type="text"
              class="w-full bg-white border-0 shadow-soft text-gray-900 text-sm rounded-xl focus:ring-2 focus:ring-primary-500 block pl-10 p-2.5 transition-shadow"
              placeholder="Search by DC no. or customer…" />
          </div>
        </div>

        <!-- Status Pill Tabs -->
        <div class="flex gap-1.5 overflow-x-auto no-scrollbar pb-0.5">
          <button v-for="t in tabs" :key="t.value"
            @click="filter.status = t.value; load()"
            class="shrink-0 px-3 py-1.5 rounded-full text-xs font-bold whitespace-nowrap transition-all"
            :class="filter.status === t.value
              ? 'bg-primary-600 text-white shadow-soft-blue'
              : 'bg-white text-gray-500 shadow-soft hover:bg-gray-50'">{{ t.label }}
          </button>
        </div>
      </div>

      <!-- Scrollable List Wrapper -->
      <div class="flex-1 overflow-y-auto pr-1 pb-10 mt-2 no-scrollbar min-h-0">
        <div class="bg-white rounded-[2rem] shadow-soft border-0 overflow-hidden animate-fade-in-up anim-delay-75">
          <div v-if="loading" class="divide-y divide-gray-50">
            <div v-for="i in 4" :key="i" class="flex items-center gap-4 px-5 py-4 animate-pulse">
              <div class="w-12 h-12 rounded-full bg-gray-100 shrink-0"></div>
              <div class="flex-1 space-y-2"><div class="h-3 bg-gray-100 rounded w-2/3"></div><div class="h-2.5 bg-gray-100 rounded w-1/3"></div></div>
              <div class="h-3 bg-gray-100 rounded w-16"></div>
            </div>
          </div>

          <div v-else-if="!challans.length" class="p-12 text-center">
            <div class="w-16 h-16 rounded-full bg-indigo-50 flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
            </div>
            <p class="font-extrabold text-gray-900 text-lg">No delivery challans yet</p>
            <p class="text-sm text-gray-500 mt-1">Issue challans for goods dispatched</p>
            <RouterLink to="/delivery-challans/new" class="btn bg-primary-600 text-white hover:bg-primary-700 shadow-soft-blue rounded-full px-6 py-2.5 mt-5 inline-flex items-center gap-2 font-bold">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
              Create First DC
            </RouterLink>
          </div>

          <div v-else class="divide-y divide-gray-50">
            <div v-for="dc in challans" :key="dc.id"
              class="flex items-center gap-4 px-5 py-4 hover:bg-gray-50 active:bg-gray-100 cursor-pointer transition-colors group"
              @click="router.push('/delivery-challans/' + dc.id)">
              <div class="w-12 h-12 rounded-full flex items-center justify-center shrink-0 font-extrabold text-lg" :class="avatarColor(dc.client_name)">
                {{ dc.client_name?.charAt(0)?.toUpperCase() || 'C' }}
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <p class="font-bold text-gray-900 text-base truncate group-hover:text-primary-700 transition-colors">{{ dc.client_name || 'Unknown Customer' }}</p>
                  <span :class="badgeClass(dc.status)" class="text-[10px]">{{ statusLabel(dc.status) }}</span>
                </div>
                <p class="text-xs text-gray-500 mt-0.5 truncate">{{ dc.number }} · {{ fmtDateShort(dc.challan_date) }}</p>
              </div>
              <div v-if="dc.vehicle_no" class="text-right shrink-0">
                <p class="text-[10px] uppercase font-bold tracking-wider text-gray-400 bg-gray-50 px-2 py-0.5 rounded-full">{{ dc.vehicle_no }}</p>
              </div>
            </div>
          </div>
          
          <!-- List Footer -->
          <div v-if="!loading && challans.length" class="bg-gray-50/80 border-t border-gray-100 px-6 py-4 flex items-center justify-between">
            <span class="text-xs text-gray-500 font-medium">Showing <span class="font-bold text-gray-800">{{ challans.length }}</span> challan{{ challans.length !== 1 ? 's' : '' }}</span>
            <span class="text-[10px] text-gray-400 font-bold uppercase tracking-widest flex items-center gap-1.5">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
              End of list
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Pane -->
    <div v-if="$route.name !== 'DeliveryChallans'" class="w-full lg:w-[65%] flex-1 overflow-y-auto no-scrollbar pb-10">
      <router-view v-slot="{ Component }">
        <component :is="Component" :key="$route.fullPath" @refresh="load" />
      </router-view>
    </div>
  </div>
</template>
