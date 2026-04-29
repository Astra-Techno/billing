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
      list('Invoice', { sort_by: 'i.created_at', sort_order: 'desc', limit: 8 }),
      list('Invoice:overdue', { limit: 5 }),
    ])
    stats.value   = sR.data?.data?.[0] || {}
    recent.value  = rR.data?.data  || []
    overdue.value = oR.data?.data  || []
  } catch {}
  loading.value = false
})

const quickActions = [
  { label: 'New Bill',     to: '/invoices/new', color: 'text-blue-500',   icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
  { label: 'New Quote',    to: '/quotes/new',   color: 'text-amber-500',  icon: 'M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2' },
  { label: 'Add Client',   to: '/clients/new',  color: 'text-emerald-500',icon: 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z' },
  { label: 'Expenses',     to: '/expenses',     color: 'text-orange-500', icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z' },
  { label: 'Products',     to: '/products',     color: 'text-purple-500', icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' },
  { label: 'GST Filing',   to: '/gst-returns',  color: 'text-teal-500',   icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
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
    if (!map.has(inv.client_name)) map.set(inv.client_name, inv)
  }
  return Array.from(map.values()).slice(0, 10)
})
</script>

<template>
  <div class="flex flex-col gap-6 lg:max-w-4xl lg:mx-auto">

    <!-- ===== HERO (GPay Style Balance) ===== -->
    <div class="flex flex-col items-center justify-center pt-8 pb-4 shrink-0 relative animate-fade-in-up">
      <p class="text-gray-500 font-medium tracking-wide mb-1 text-sm">Total Outstanding</p>
      <h1 class="text-5xl lg:text-6xl font-extrabold tracking-tight text-gray-900 mb-8">{{ inrCompact(stats.total_due || 0) }}</h1>
      
      <div class="flex justify-center gap-4 w-full max-w-sm px-2">
        <RouterLink to="/invoices/new" class="flex-1 btn bg-primary-100 text-primary-700 hover:bg-primary-200 shadow-none border border-transparent">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
          New Bill
        </RouterLink>
        <button class="flex-1 btn bg-white text-gray-700 hover:bg-gray-50 shadow-soft border border-gray-100" @click="router.push('/invoices')">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
          Bills
        </button>
      </div>
    </div>

    <!-- ===== PEOPLE (Horizontal Scroll) ===== -->
    <div class="shrink-0 -mx-4 px-4 lg:mx-0 lg:px-0 mt-4 animate-fade-in-up anim-delay-75">
      <p class="text-sm font-bold text-gray-800 mb-4 px-1">People</p>
      <div class="flex gap-4 overflow-x-auto pb-4 hide-scrollbar">
        <div v-for="person in recentPeople" :key="person.id" 
             class="shrink-0 flex flex-col items-center gap-2 cursor-pointer group w-20"
             @click="router.push('/invoices/' + person.id)">
          <div class="w-16 h-16 rounded-full flex items-center justify-center text-2xl font-bold shadow-sm group-active:scale-95 transition-transform"
               :class="avatarColor(person.client_name)">
            {{ person.client_name?.charAt(0)?.toUpperCase() }}
          </div>
          <p class="text-xs font-semibold text-gray-700 truncate w-full text-center">{{ person.client_name?.split(' ')[0] }}</p>
        </div>
        <!-- Add new customer button -->
        <div class="shrink-0 flex flex-col items-center gap-2 cursor-pointer group w-20" @click="router.push('/clients/new')">
          <div class="w-16 h-16 rounded-full flex items-center justify-center text-2xl bg-white border border-gray-200 text-gray-400 group-active:scale-95 transition-transform shadow-soft">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
          </div>
          <p class="text-xs font-semibold text-gray-600 w-full text-center">Add</p>
        </div>
      </div>
    </div>

    <!-- ===== EXPLORE (Quick Actions) ===== -->
    <div class="shrink-0 mt-2 animate-fade-in-up anim-delay-150">
      <p class="text-sm font-bold text-gray-800 mb-4 px-1">Explore</p>
      <div class="grid grid-cols-4 sm:grid-cols-6 gap-y-6 gap-x-2">
        <RouterLink v-for="a in quickActions" :key="a.label" :to="a.to"
          class="flex flex-col items-center gap-2 group active:scale-95 transition-transform duration-300 ease-[cubic-bezier(0.34,1.56,0.64,1)]">
          <div class="w-14 h-14 rounded-full flex items-center justify-center bg-white shadow-soft group-hover:bg-gray-50 transition-colors border border-gray-50">
            <svg class="w-6 h-6" :class="a.color" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" :d="a.icon" />
            </svg>
          </div>
          <span class="text-[10px] font-semibold text-gray-600 text-center leading-tight w-16">{{ a.label }}</span>
        </RouterLink>
      </div>
    </div>

    <!-- ===== RECENT ACTIVITY ===== -->
    <div class="mt-4 pb-6 animate-fade-in-up anim-delay-200">
      <div class="flex items-center justify-between mb-4 px-1">
        <h2 class="font-bold text-gray-800 text-sm">Recent Activity</h2>
        <RouterLink to="/invoices" class="text-xs text-primary-600 font-bold bg-primary-50 hover:bg-primary-100 px-4 py-1.5 rounded-full transition-colors active:scale-95">Show all</RouterLink>
      </div>
      
      <div class="bg-white rounded-[2rem] shadow-soft border border-gray-50 overflow-hidden divide-y divide-gray-50">
          <div v-for="inv in recent" :key="inv.id"
            class="flex items-center justify-between px-5 py-4 hover:bg-gray-50 cursor-pointer transition-colors active:bg-gray-100"
            @click="router.push(`/invoices/${inv.id}`)">
            <div class="flex items-center gap-4 min-w-0">
              <div class="w-12 h-12 rounded-full flex items-center justify-center shrink-0 text-lg font-bold shadow-sm" :class="avatarColor(inv.client_name)">
                {{ inv.client_name?.charAt(0)?.toUpperCase() }}
              </div>
              <div class="min-w-0">
                <p class="font-bold text-gray-900 truncate text-sm">{{ inv.client_name }}</p>
                <p class="text-xs text-gray-500 mt-0.5">{{ fmtDateShort(inv.issue_date) }}</p>
              </div>
            </div>
            <div class="text-right ml-4 shrink-0 flex flex-col items-end">
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
</template>
