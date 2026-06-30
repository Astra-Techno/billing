<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { list } from '../../api'
import { inr } from '../../utils/currency'
import { fmtDateShort } from '../../utils/date'
import HelpIcon from '../../components/HelpIcon.vue'
import { useListRefresh } from '../../composables/useListRefresh'

const route    = useRoute()
const router   = useRouter()
const challans  = ref([])
const loading   = ref(true)
const showFilters = ref(false)
const filter    = ref({ status: '', search: '', from_date: '', to_date: '', client_id: '', client_name: '' })
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

function clearClientFilter() {
  filter.value.client_id = ''; filter.value.client_name = ''; load()
}

async function load() {
  loading.value = true
  try {
    const p = { sort_by: 'dc.created_at', sort_order: 'desc' }
    if (filter.value.status)    p['filter.status']    = filter.value.status
    if (filter.value.search)    p['filter.search']    = `%${filter.value.search}%`
    if (filter.value.from_date) p['filter.from_date'] = filter.value.from_date
    if (filter.value.to_date)   p['filter.to_date']   = filter.value.to_date
    if (filter.value.client_id) p['filter.client_id'] = filter.value.client_id
    const { data } = await list('DeliveryChallan', p)
    challans.value = data.data || []
  } catch {}
  loading.value = false
}

function onSearch() { clearTimeout(timer); timer = setTimeout(load, 350) }

onMounted(() => {
  if (route.query.client_id)   filter.value.client_id   = route.query.client_id
  if (route.query.client_name) filter.value.client_name = route.query.client_name
})

useListRefresh(load, { listRouteName: 'DeliveryChallans' })
</script>

<template>
  <div class="flex flex-col lg:flex-row h-full min-h-0 w-full overflow-hidden">
    
    <!-- Left Pane: List -->
    <div id="c3-left-panel" :class="{ 'hidden lg:flex': $route.name !== 'DeliveryChallans', 'split-pane-left transition-all duration-300 relative z-30 h-full': true }">
      
      <!-- Top Sticky Header Area -->
      <div class="px-5 py-4 border-b border-gray-200/60 bg-white/60 backdrop-blur-md sticky top-0 z-10">
        <!-- Header & Actions -->
        <div class="flex justify-between items-center mb-4">
            <h2 class="font-bold text-gray-900 text-sm tracking-tight flex items-center gap-2">Delivery Challans <HelpIcon section="delivery-challans" class="w-3.5 h-3.5" /></h2>
            <div class="flex gap-2">
                <!-- Search Toggle -->
                <button @click="showFilters = !showFilters" class="w-7 h-7 bg-white border border-gray-200/80 shadow-sm hover:shadow hover:border-gray-300 rounded-lg flex items-center justify-center transition-all" :class="showFilters ? 'text-primary-600 border-primary-200 bg-primary-50' : 'text-gray-600'">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </button>
                <!-- New Challan -->
                <button @click="router.push('/delivery-challans/new')" class="w-7 h-7 bg-white border border-gray-200/80 shadow-sm hover:shadow hover:border-gray-300 rounded-lg flex items-center justify-center text-gray-600 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                </button>
            </div>
        </div>

        <!-- Search / Filter Expansion -->
        <div v-show="showFilters" class="mb-4 space-y-2 animate-fade-in-up">
            <input v-model="filter.search" @input="onSearch" type="text"
              class="w-full bg-white border border-gray-200 shadow-sm text-gray-900 text-xs font-semibold rounded-lg focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 block px-3 py-2 transition-all"
              placeholder="Search by DC no. or customer..." />
            <div class="flex gap-2 items-center">
              <input v-model="filter.from_date" type="date" class="w-full bg-white border border-gray-200 shadow-sm text-gray-900 text-[11px] font-semibold rounded-lg px-2 py-1.5 focus:border-primary-500 transition-all" @change="load()" />
              <span class="text-gray-400 text-[10px] font-bold uppercase">to</span>
              <input v-model="filter.to_date" type="date" class="w-full bg-white border border-gray-200 shadow-sm text-gray-900 text-[11px] font-semibold rounded-lg px-2 py-1.5 focus:border-primary-500 transition-all" @change="load()" />
            </div>
        </div>

        <!-- Active client filter chip -->
        <div v-if="filter.client_id" class="flex items-center gap-1.5 bg-primary-50 border border-primary-100 rounded-lg px-3 py-1.5 mb-2">
          <svg class="w-3 h-3 text-primary-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          <span class="text-[11px] font-bold text-primary-700 flex-1 truncate">{{ filter.client_name || 'Customer filter active' }}</span>
          <button @click="clearClientFilter" class="text-primary-400 hover:text-primary-700 ml-1">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <!-- Status Tabs -->
        <div class="flex gap-1 bg-gray-100/80 p-1 rounded-[10px] ring-1 ring-inset ring-gray-200/50 overflow-x-auto hide-scrollbar">
            <button v-for="t in tabs" :key="t.value"
              @click="filter.status = t.value; load()"
              class="flex-1 text-[11px] font-semibold rounded-md py-1.5 transition-all whitespace-nowrap px-2"
              :class="filter.status === t.value ? 'bg-white shadow-sm text-gray-900 font-bold' : 'text-gray-500 hover:text-gray-700'">
              {{ t.label }}
            </button>
        </div>
      </div>

      <!-- Scrollable List -->
      <div class="flex-1 overflow-y-auto px-3 py-3 space-y-1.5 custom-scrollbar min-h-0">
          
          <div v-if="loading" class="space-y-1.5">
            <div v-for="i in 6" :key="i" class="p-4 rounded-xl border border-gray-100 bg-white/40 animate-pulse flex justify-between items-center gap-3">
              <div class="w-10 h-10 rounded-full bg-gray-200 shrink-0"></div>
              <div class="space-y-2 flex-1"><div class="h-3.5 bg-gray-200 rounded w-24"></div><div class="h-2.5 bg-gray-100 rounded w-16"></div></div>
            </div>
          </div>

          <div v-else-if="!challans.length" class="p-8 text-center">
            <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
              <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
            </div>
            <p class="font-bold text-gray-900 text-[13px]">No delivery challans</p>
            <p class="text-[11px] text-gray-500 mt-1">Issue challans for goods dispatched</p>
          </div>

          <div v-else v-for="(dc, idx) in challans" :key="dc.id"
            class="p-4 rounded-xl border cursor-pointer transition-all group relative overflow-hidden list-item-1"
            :style="{ animationDelay: (idx * 0.05) + 's' }"
            :class="[
              $route.params.id == dc.id ? 'bg-white border-gray-200 shadow-[0_2px_8px_rgba(0,0,0,0.03)]' : 'border-transparent hover:border-gray-200/60 hover:bg-white hover:shadow-[0_2px_8px_rgba(0,0,0,0.02)]'
            ]"
            @click="router.push('/delivery-challans/' + dc.id)">
            
            <!-- Active Indicator Line -->
            <div v-if="$route.params.id == dc.id" class="absolute left-0 top-0 bottom-0 w-[3px] bg-gray-900 rounded-l-xl"></div>
            
            <div class="flex gap-3 items-center">
                <div class="w-10 h-10 rounded-[10px] flex items-center justify-center font-bold text-sm border group-hover:scale-105 transition-transform shrink-0" :class="avatarColor(dc.client_name)">
                  {{ dc.client_name?.charAt(0)?.toUpperCase() || 'C' }}
                </div>
                
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-start mb-0.5">
                        <span class="text-[14px] font-bold truncate pr-2 tracking-tight transition-colors"
                              :class="$route.params.id == dc.id ? 'text-primary-600' : 'text-gray-900 group-hover:text-primary-600'">
                              {{ dc.client_name || 'Unknown Customer' }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center mt-1">
                        <span class="text-[11px] font-medium text-gray-500 truncate pr-2 flex items-center gap-1.5">
                            <span class="text-gray-400 font-semibold">{{ dc.number }}</span>
                            <span class="text-gray-300">•</span>
                            <span>{{ fmtDateShort(dc.challan_date) }}</span>
                        </span>
                        
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded tracking-wider flex items-center shrink-0"
                              :class="{
                                  'bg-green-50 text-green-600 border border-green-100': dc.status === 'delivered',
                                  'bg-blue-50 text-blue-600 border border-blue-100': dc.status === 'issued',
                                  'bg-gray-100 text-gray-500 border border-gray-200/60': dc.status === 'draft',
                                  'bg-red-50 text-red-600 border border-red-100': dc.status === 'cancelled'
                              }">
                            {{ statusLabel(dc.status).toUpperCase() }}
                        </span>
                    </div>
                    <div v-if="dc.vehicle_no" class="mt-1.5 text-[10px] uppercase font-bold tracking-wider text-gray-400 bg-gray-50/80 px-2 py-0.5 rounded-md inline-block border border-gray-100">
                        {{ dc.vehicle_no }}
                    </div>
                </div>
            </div>
          </div>
      </div>
    </div>

    <!-- Right Pane: Detail/Form wrapper -->
    <div v-if="$route.name !== 'DeliveryChallans'" id="c3-right-view" class="split-pane-right relative z-20">
      <!-- Subtle noise/texture overlay -->
      <div class="absolute inset-0 opacity-[0.03] pointer-events-none mix-blend-multiply" style="background-image: url('data:image/svg+xml,%3Csvg viewBox=%220 0 200 200%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cfilter id=%22noiseFilter%22%3E%3CfeTurbulence type=%22fractalNoise%22 baseFrequency=%220.65%22 numOctaves=%223%22 stitchTiles=%22stitch%22/%3E%3C/filter%3E%3Crect width=%22100%25%22 height=%22100%25%22 filter=%22url(%23noiseFilter)%22/%3E%3C/svg%3E');"></div>
      
      <router-view v-slot="{ Component }">
        <component :is="Component" :key="$route.fullPath" @refresh="load" />
      </router-view>
    </div>
  </div>
</template>
