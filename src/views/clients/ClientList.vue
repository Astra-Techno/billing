<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { list } from '../../api'
import HelpIcon from '../../components/HelpIcon.vue'
import { inr } from '../../utils/currency'
import { useTour } from '../../composables/useTour'
import { useListRefresh } from '../../composables/useListRefresh'

const { startTour, isTourSeen } = useTour('client-list', [
  { target: '[data-tour="client-search"]', title: 'Search Customers', text: 'Type a name or mobile number to quickly find a customer.' },
  { target: '[data-tour="client-add"]', title: 'Add Customer', text: 'Create a new customer record to use when making invoices.' },
  { target: '[data-tour="client-list"]', title: 'Customer List', text: 'Tap any customer to view their details, invoices, and outstanding balance.' },
])

const router  = useRouter()
const clients = ref([])
const loading = ref(true)
const search  = ref('')
let timer = null

async function load() {
  loading.value = true
  try {
    const p = {}
    if (search.value) p['filter.search'] = `%${search.value}%`
    const { data } = await list('Client', p)
    clients.value = data.data || []
  } catch {}
  loading.value = false
}

function onSearch() { clearTimeout(timer); timer = setTimeout(load, 350) }

useListRefresh(() => {
  load().then(() => {
    setTimeout(() => { if (!isTourSeen()) startTour() }, 800)
  })
}, { listRouteName: 'Clients' })

const avatarColors = ['bg-blue-100 text-blue-700', 'bg-emerald-100 text-emerald-700', 'bg-purple-100 text-purple-700', 'bg-amber-100 text-amber-700', 'bg-pink-100 text-pink-700', 'bg-teal-100 text-teal-700']
const avatarColor  = (name) => avatarColors[(name?.charCodeAt(0) || 0) % avatarColors.length]
</script>

<template>
  <div class="flex flex-col lg:flex-row h-full min-h-0 w-full overflow-hidden">

    <!-- Left Pane: List -->
    <div id="c3-left-panel" :class="{ 'hidden lg:flex': $route.name !== 'Clients', 'split-pane-left transition-all duration-300 relative z-30 h-full': true }">
      
      <div class="px-4 py-3 border-b border-google-divider bg-white sticky top-0 z-10">
        <!-- Header & Actions -->
        <div class="flex justify-between items-center mb-4">
            <h2 class="font-medium text-google-text text-base flex items-center gap-2">Customers <HelpIcon section="customers" class="w-4 h-4" />
              <button @click="startTour()" class="text-[10px] font-bold text-primary-500 hover:text-primary-700 ml-1" title="Take a tour">Tour</button>
            </h2>
            <div class="flex gap-2">
                <!-- Search Toggle (Using inline expansion for clients) -->
                <!-- New Customer -->
                <button @click="router.push('/clients/new')" data-tour="client-add" class="w-7 h-7 bg-white border border-gray-200/80 shadow-sm hover:shadow hover:border-gray-300 rounded-lg flex items-center justify-center text-gray-600 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                </button>
            </div>
        </div>

        <!-- Search -->
        <div class="mb-2 space-y-2 animate-fade-in-up">
            <input v-model="search" @input="onSearch" type="text"
              class="gpay-list-search" data-tour="client-search"
              placeholder="Search customers..." />
        </div>
      </div>

      <!-- Scrollable List -->
      <div class="flex-1 overflow-y-auto px-3 py-3 space-y-1.5 custom-scrollbar min-h-0" data-tour="client-list">

          <div v-if="loading" class="space-y-1.5">
            <div v-for="i in 6" :key="i" class="p-4 rounded-xl border border-gray-100 bg-white/40 animate-pulse flex items-center gap-3">
              <div class="w-10 h-10 rounded-full bg-gray-200 shrink-0"></div>
              <div class="space-y-2 flex-1"><div class="h-3.5 bg-gray-200 rounded w-24"></div><div class="h-2.5 bg-gray-100 rounded w-16"></div></div>
            </div>
          </div>

          <div v-else-if="!clients.length" class="p-8 text-center">
            <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
              <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <p class="font-bold text-gray-900 text-[13px]">No customers found</p>
            <p class="text-[11px] text-gray-500 mt-1">Add a customer to get started</p>
          </div>

          <div v-else v-for="(c, idx) in clients" :key="c.id"
            class="p-4 rounded-xl border cursor-pointer transition-all group relative overflow-hidden list-item-1"
            :style="{ animationDelay: (idx * 0.05) + 's' }"
            :class="[
              $route.params.id == c.id ? 'bg-white border-gray-200 shadow-[0_2px_8px_rgba(0,0,0,0.03)]' : 'border-transparent hover:border-gray-200/60 hover:bg-white hover:shadow-[0_2px_8px_rgba(0,0,0,0.02)]'
            ]"
            @click="router.push(`/clients/${c.id}`)">
            
            <!-- Active Indicator Line -->
            <div v-if="$route.params.id == c.id" class="absolute left-0 top-0 bottom-0 w-[3px] bg-primary-600 rounded-l-xl"></div>
            
            <div class="flex gap-3 items-center">
                <div class="w-10 h-10 rounded-[10px] flex items-center justify-center font-bold text-sm border group-hover:scale-105 transition-transform shrink-0" :class="avatarColor(c.name)">
                  {{ c.name?.charAt(0)?.toUpperCase() }}
                </div>
                
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-start mb-0.5">
                        <span class="text-[14px] font-bold truncate pr-2 tracking-tight transition-colors"
                              :class="$route.params.id == c.id ? 'text-primary-600' : 'text-gray-900 group-hover:text-primary-600'">
                              {{ c.name }}
                        </span>
                        <span v-if="parseFloat(c.outstanding_balance) > 0" class="text-[11px] font-bold text-red-600 tabular-nums bg-red-50 px-1.5 py-0.5 rounded border border-red-100 shrink-0">
                              {{ inr(c.outstanding_balance) }}
                        </span>
                    </div>
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <span v-if="c.mobile" class="text-[11px] font-semibold text-gray-500">{{ c.mobile }}</span>
                        <span v-if="c.mobile && c.city" class="text-gray-300 text-[10px]">•</span>
                        <span v-if="c.city" class="text-[11px] text-gray-400">{{ c.city }}</span>
                    </div>
                </div>
            </div>
          </div>
      </div>
    </div>

    <!-- Right Pane: Detail/Form wrapper -->
    <div v-if="$route.name !== 'Clients'" id="c3-right-view" class="split-pane-right relative z-20">
      <router-view v-slot="{ Component }">
        <component :is="Component" :key="$route.fullPath" @refresh="load" />
      </router-view>
    </div>
  </div>
</template>
