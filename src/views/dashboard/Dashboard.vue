<script setup>
import { ref, computed, onMounted } from 'vue'
import { list } from '../../api'
import { inr, inrCompact } from '../../utils/currency'
import { fmtDateShort } from '../../utils/date'
import { useAuthStore } from '../../stores/auth'
import { useRouter } from 'vue-router'

const auth   = useAuthStore()
const router = useRouter()

const stats   = ref({ total_due: 0, total_paid_month: 0, overdue_count: 0, draft_count: 0 })
const recent  = ref([])
const overdue = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const [sR, rR, oR] = await Promise.all([
      list('Dashboard:stats'),
      list('Invoice', { sort_by: 'i.created_at', sort_order: 'desc', limit: 5 }),
      list('Invoice:overdue', { limit: 5 }),
    ])
    stats.value   = sR.data?.data?.[0] || {}
    recent.value  = rR.data?.data  || []
    overdue.value = oR.data?.data  || []
  } catch {}
  loading.value = false
})

const quickActions = [
  { label: 'New Quote',    to: '/quotes/new',   color: 'text-amber-600 bg-amber-50',  icon: 'M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2' },
  { label: 'Add Client',   to: '/clients/new',  color: 'text-emerald-600 bg-emerald-50',icon: 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z' },
  { label: 'Expenses',     to: '/expenses',     color: 'text-orange-600 bg-orange-50', icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z' },
  { label: 'Products',     to: '/products',     color: 'text-purple-600 bg-purple-50', icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' },
]

const avatarColors = [
  'bg-blue-100 text-blue-700', 'bg-emerald-100 text-emerald-700',
  'bg-purple-100 text-purple-700', 'bg-amber-100 text-amber-700',
  'bg-pink-100 text-pink-700', 'bg-teal-100 text-teal-700',
]
const avatarColor = name => avatarColors[(name?.charCodeAt(0) || 0) % avatarColors.length]

const recentPeople = computed(() => {
  const map = new Map()
  for (const inv of recent.value) {
    if (!map.has(inv.client_id)) map.set(inv.client_id, inv)
  }
  return Array.from(map.values()).slice(0, 10)
})
</script>

<template>
  <div class="flex flex-col lg:flex-row gap-4 lg:gap-6 w-full lg:h-full lg:min-h-0">
    
    <!-- LEFT PANE: Hero & Recent Activity -->
    <div class="flex-1 flex flex-col gap-4 w-full lg:w-1/2 shrink-0 lg:h-full lg:min-h-0">
      
      <!-- ===== HERO (Midnight Slate Glassmorphic Balance) ===== -->
      <div class="relative shrink-0 overflow-hidden rounded-[2rem] bg-gradient-to-br from-gray-900 via-gray-800 to-black p-6 sm:p-8 text-center shadow-2xl animate-fade-in-up">
        <!-- Glow effects -->
        <div class="absolute -top-24 -right-24 w-40 h-40 bg-primary-500/30 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -left-24 w-40 h-40 bg-emerald-500/20 rounded-full blur-3xl pointer-events-none"></div>
        
        <p class="text-gray-400 font-medium tracking-wide mb-1.5 text-xs relative z-10">Total Outstanding</p>
        <h1 class="text-4xl lg:text-5xl font-extrabold tracking-tight text-white mb-5 relative z-10">{{ inrCompact(stats.total_due || 0) }}</h1>
        
        <div class="flex justify-center gap-3 w-full max-w-sm px-2 mx-auto relative z-10">
          <RouterLink to="/invoices/new" class="flex-1 flex items-center justify-center gap-2 py-3 bg-primary-600 text-white hover:bg-primary-500 font-bold rounded-xl shadow-lg shadow-primary-600/30 border border-primary-500/50 transition-all active:scale-95 text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            New Bill
          </RouterLink>
          <button class="flex-1 flex items-center justify-center gap-2 py-3 bg-white/10 backdrop-blur-md text-white hover:bg-white/20 font-bold rounded-xl border border-white/10 transition-all active:scale-95 text-sm" @click="router.push('/invoices')">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Bills
          </button>
        </div>
      </div>

      <!-- ===== RECENT ACTIVITY ===== -->
      <div class="flex flex-col flex-1 min-h-0 pb-4 lg:pb-0 animate-fade-in-up anim-delay-200">
        <div class="flex items-center justify-between mb-3 px-1 shrink-0">
          <h2 class="font-bold text-gray-800 text-sm">Recent Activity</h2>
          <RouterLink to="/invoices" class="text-xs text-primary-600 font-bold bg-primary-50 hover:bg-primary-100 px-3 py-1.5 rounded-full transition-colors active:scale-95">Show all</RouterLink>
        </div>
        
        <div class="bg-white rounded-[2rem] shadow-soft border border-gray-50 overflow-y-auto hide-scrollbar min-h-0 flex-1">
          <div class="divide-y divide-gray-50">
            <div v-for="inv in recent" :key="inv.id"
              class="flex items-center justify-between px-5 py-3.5 hover:bg-gray-50 cursor-pointer transition-colors active:bg-gray-100"
              @click="router.push(`/invoices/${inv.id}`)">
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 text-base font-bold shadow-sm" :class="avatarColor(inv.client_name)">
                  {{ inv.client_name?.charAt(0)?.toUpperCase() }}
                </div>
                <div class="min-w-0">
                  <p class="font-bold text-gray-900 truncate text-sm">{{ inv.client_name }}</p>
                  <p class="text-xs text-gray-500 mt-0.5">{{ fmtDateShort(inv.issue_date) }}</p>
                </div>
              </div>
              <div class="text-right ml-3 shrink-0 flex flex-col items-end">
                <p class="font-extrabold text-gray-900 text-sm">{{ inr(inv.amount_due) }}</p>
                <p class="text-[9px] font-extrabold mt-1 tracking-wider uppercase px-2 py-0.5 rounded-full" 
                   :class="{'bg-emerald-50 text-emerald-600': inv.status==='paid', 'bg-amber-50 text-amber-600': inv.status==='draft', 'bg-blue-50 text-blue-600': inv.status==='sent', 'bg-red-50 text-red-600': inv.status==='overdue'}">
                  {{ inv.status }}
                </p>
              </div>
            </div>
            <div v-if="!recent.length && !loading" class="p-8 text-center text-gray-500 text-sm font-medium">No recent activity</div>
            <div v-if="loading" class="p-8 flex justify-center">
               <div class="w-6 h-6 border-2 border-primary-200 border-t-primary-600 rounded-full animate-spin"></div>
            </div>
          </div>
        </div>
      </div>
      
    </div>

    <!-- RIGHT PANE: Overdue, Actions, People -->
    <div class="flex flex-col gap-4 w-full lg:w-1/2 shrink-0 lg:h-full lg:min-h-0 lg:overflow-y-auto hide-scrollbar lg:pb-6">

      <!-- ===== OVERDUE ALERT BANNER ===== -->
      <div v-if="overdue.length > 0" class="shrink-0 flex items-center gap-3 bg-gradient-to-r from-red-50 to-white border border-red-100 rounded-[1.5rem] p-3 shadow-sm animate-fade-in-up">
        <div class="w-9 h-9 rounded-full bg-red-100 text-red-600 flex items-center justify-center shrink-0 shadow-inner">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-bold text-gray-900">{{ overdue.length }} overdue invoice{{ overdue.length !== 1 ? 's' : '' }}</p>
          <p class="text-xs font-semibold text-red-600 mt-0.5">{{ inr(overdue.reduce((s, i) => s + parseFloat(i.amount_due || 0), 0)) }} pending</p>
        </div>
        <RouterLink to="/invoices?status=overdue" class="text-xs font-bold text-red-700 bg-red-100 hover:bg-red-200 px-3 py-1.5 rounded-full transition-colors shrink-0">
          Collect
        </RouterLink>
      </div>

      <!-- ===== EXPLORE (Premium Cards) ===== -->
      <div class="shrink-0 animate-fade-in-up anim-delay-75">
        <p class="text-sm font-bold text-gray-800 mb-3 px-1">Quick Actions</p>
        <div class="grid grid-cols-2 gap-3">
          <RouterLink v-for="a in quickActions" :key="a.label" :to="a.to"
            class="flex flex-col items-center justify-center gap-2 p-3 bg-white rounded-[1.25rem] shadow-soft border border-gray-100 hover:border-gray-200 hover:shadow-md active:scale-95 transition-all duration-300">
            <div class="w-10 h-10 rounded-full flex items-center justify-center shadow-inner" :class="a.color">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="a.icon" />
              </svg>
            </div>
            <span class="text-sm font-bold text-gray-800 text-center">{{ a.label }}</span>
          </RouterLink>
        </div>
      </div>

      <!-- ===== PEOPLE (Grid/Wrap) ===== -->
      <div class="shrink-0 -mx-4 px-4 lg:mx-0 lg:px-0 animate-fade-in-up anim-delay-150">
        <p class="text-sm font-bold text-gray-800 mb-3 px-1">People</p>
        <div class="flex flex-wrap gap-3 pb-4">
          <div v-for="person in recentPeople" :key="person.client_id" 
               class="shrink-0 flex flex-col items-center gap-1.5 cursor-pointer group w-[4.5rem]"
               @click="router.push('/clients/' + person.client_id)">
            <div class="w-[3.5rem] h-[3.5rem] rounded-2xl flex items-center justify-center text-xl font-bold shadow-sm border border-gray-100 group-active:scale-95 transition-transform"
                 :class="avatarColor(person.client_name)">
              {{ person.client_name?.charAt(0)?.toUpperCase() }}
            </div>
            <p class="text-xs font-semibold text-gray-700 truncate w-full text-center">{{ person.client_name?.split(' ')[0] }}</p>
          </div>
          <!-- Add new customer button -->
          <div class="shrink-0 flex flex-col items-center gap-1.5 cursor-pointer group w-[4.5rem]" @click="router.push('/clients/new')">
            <div class="w-[3.5rem] h-[3.5rem] rounded-2xl flex items-center justify-center text-xl bg-gray-50 border border-dashed border-gray-300 text-gray-400 group-active:scale-95 transition-transform">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            </div>
            <p class="text-xs font-semibold text-gray-600 w-full text-center">Add</p>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>
