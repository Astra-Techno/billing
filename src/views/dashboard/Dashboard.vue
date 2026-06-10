<script setup>
import { ref, computed, onMounted } from 'vue'
import { list } from '../../api'
import { inr, inrCompact } from '../../utils/currency'
import { fmtDateShort } from '../../utils/date'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useTour } from '../../composables/useTour'

const router = useRouter()
const authStore = useAuthStore()

const { startTour, isTourSeen } = useTour('dashboard', [
  { target: '[data-tour="stat-cards"]', title: 'Business Snapshot', text: 'See your collections, pending invoices, expenses and overdue amounts at a glance. These update in real-time.' },
  { target: '[data-tour="balance-strip"]', title: 'Balance', text: 'Your overall financial health — Total Billed minus Total Expenses = Balance. Green is good!' },
  { target: '[data-tour="revenue-chart"]', title: 'Revenue Trend', text: 'Last 6 months of collections visualized. Helps you spot patterns and growth.' },
  { target: '[data-tour="quick-actions"]', title: 'Quick Actions', text: 'Shortcuts to common tasks — create invoice, add customer, record expense. One click away.' },
  { target: '[data-tour="recent-invoices"]', title: 'Recent Invoices', text: 'Your latest bills with status. Click any to view full details, record payment, or share.' },
])

const stats          = ref({ total_due: 0, total_paid_month: 0, total_expenses_month: 0, overdue_count: 0, draft_count: 0, overdue_amount: 0 })
const summary        = ref({ total_billed: 0, total_collected: 0, total_outstanding: 0 })
const totalExpenses  = ref(0)
const recent         = ref([])
const overdue        = ref([])
const monthlyRevenue = ref([])
const loading        = ref(true)

const balance = computed(() => {
  const collected = parseFloat(summary.value.total_collected || 0)
  const expenses  = parseFloat(totalExpenses.value || 0)
  return collected - expenses
})

onMounted(async () => {
  try {
    const [sR, sumR, expR, rR, oR, mR] = await Promise.all([
      list('Dashboard:stats'),
      list('Dashboard:summary'),
      list('Dashboard:expenseSummary'),
      list('Invoice', { sort_by: 'i.created_at', sort_order: 'desc', limit: 8 }),
      list('Invoice:overdue', { limit: 10 }),
      list('Dashboard:monthlyRevenue'),
    ])
    stats.value          = sR.data?.data?.[0] || {}
    const s              = sumR.data?.data?.[0] || {}
    summary.value        = s
    totalExpenses.value  = (expR.data?.data || []).reduce((sum, c) => sum + parseFloat(c.total || 0), 0)
    recent.value         = rR.data?.data  || []
    overdue.value        = oR.data?.data  || []
    monthlyRevenue.value = mR.data?.data  || []

    stats.value.overdue_amount = overdue.value.reduce((sum, inv) => sum + parseFloat(inv.amount_due || 0), 0)

  } catch {}
  loading.value = false

  // Auto-start tour for first-time users (after data loads)
  setTimeout(() => {
    if (!isTourSeen() && !loading.value) startTour()
  }, 800)
})

// Build last-6-months chart from real revenue data
const chartMonths = computed(() => {
  const months = []
  const now = new Date()
  for (let i = 5; i >= 0; i--) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1)
    const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
    const label = d.toLocaleString('default', { month: 'short' })
    months.push({ key, label })
  }
  const revenueMap = Object.fromEntries(monthlyRevenue.value.map(r => [r.month, parseFloat(r.collected || 0)]))
  const withData = months.map(m => ({ ...m, value: revenueMap[m.key] || 0 }))
  const max = Math.max(...withData.map(m => m.value), 1)
  return withData.map(m => ({ ...m, pct: Math.max(4, Math.round((m.value / max) * 100)) }))
})

const avatarColors = [
  'bg-blue-50 text-blue-600 border-blue-100', 'bg-emerald-50 text-emerald-600 border-emerald-100',
  'bg-purple-50 text-purple-600 border-purple-100', 'bg-amber-50 text-amber-600 border-amber-100',
  'bg-pink-50 text-pink-600 border-pink-100', 'bg-teal-50 text-teal-600 border-teal-100',
]
const avatarColor = name => avatarColors[(name?.charCodeAt(0) || 0) % avatarColors.length]

const topClients = computed(() => {
  const map = new Map()
  for (const inv of recent.value) {
    if (!map.has(inv.client_id)) {
      map.set(inv.client_id, {
        id: inv.client_id,
        name: inv.client_name,
        count: 1,
        total: parseFloat(inv.total || 0)
      })
    } else {
      const client = map.get(inv.client_id)
      client.count++
      client.total += parseFloat(inv.total || 0)
    }
  }
  return Array.from(map.values()).sort((a,b) => b.total - a.total).slice(0, 5)
})

const draftInvoices = computed(() => {
    return recent.value.filter(inv => inv.status === 'draft')
})

const firstName = computed(() => {
    return authStore.user?.name?.split(' ')[0] || 'User'
})

</script>

<template>
  <div class="gpay-screen overflow-y-auto custom-scrollbar">
      <div class="lg:px-8 lg:pt-8 lg:pb-10 max-w-[1280px] lg:mx-auto w-full animate-doc">

          <!-- Mobile: premium home -->
          <div class="px-4 pt-6 pb-2 lg:hidden flex justify-between items-center">
              <div>
                  <p class="text-[10px] font-bold text-primary-600 uppercase tracking-widest">Welcome back</p>
                  <h1 class="text-2xl font-black text-slate-800 mt-0.5 tracking-tight leading-none">{{ firstName }}</h1>
              </div>
              <span class="text-[10px] font-bold text-slate-400 bg-white border border-gray-100 px-2.5 py-1 rounded-full shadow-sm select-none">
                {{ new Date().toLocaleDateString('en-IN', { day: 'numeric', month: 'short' }) }}
              </span>
          </div>

          <div class="px-4 lg:hidden mb-5">
              <div class="gpay-hero-balance" @click="router.push('/invoices?status=sent')">
                  <div class="relative z-10">
                      <div class="flex items-start justify-between">
                          <p class="text-white/85 text-sm font-medium">Pending to collect</p>
                          <span v-if="stats.overdue_count > 0" class="text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur px-2 py-1 rounded-full">
                              {{ stats.overdue_count }} overdue
                          </span>
                      </div>
                      <p class="display-amount text-white mt-2">{{ inr(stats.total_due || 0) }}</p>
                      <div class="flex gap-6 mt-5 pt-4 border-t border-white/15">
                          <div>
                              <p class="text-white/60 text-[11px] font-medium uppercase tracking-wide">Collected</p>
                              <p class="text-lg font-bold text-white tabular-nums mt-0.5">{{ inrCompact(stats.total_paid_month || 0) }}</p>
                          </div>
                          <div>
                              <p class="text-white/60 text-[11px] font-medium uppercase tracking-wide">Expenses</p>
                              <p class="text-lg font-bold text-white tabular-nums mt-0.5">{{ inrCompact(stats.total_expenses_month || 0) }}</p>
                          </div>
                      </div>
                  </div>
              </div>
          </div>

          <div class="px-4 pb-6 lg:hidden">
              <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-3">Quick actions</p>
              <div class="flex gap-3 pb-1">
                  <button type="button" class="flex-1 bg-white border border-gray-100 shadow-sm rounded-2xl py-3 flex flex-col items-center justify-center gap-1.5 transition-all duration-200 active:scale-95" @click="router.push('/invoices/new')">
                      <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 border border-blue-100/30 flex items-center justify-center shrink-0">
                          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                      </div>
                      <span class="text-[11px] font-bold text-slate-700">New bill</span>
                  </button>
                  <button type="button" class="flex-1 bg-white border border-gray-100 shadow-sm rounded-2xl py-3 flex flex-col items-center justify-center gap-1.5 transition-all duration-200 active:scale-95" @click="router.push('/clients/new')">
                      <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-100/30 flex items-center justify-center shrink-0">
                          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                      </div>
                      <span class="text-[11px] font-bold text-slate-700">Customer</span>
                  </button>
                  <button type="button" class="flex-1 bg-white border border-gray-100 shadow-sm rounded-2xl py-3 flex flex-col items-center justify-center gap-1.5 transition-all duration-200 active:scale-95" @click="router.push('/expenses/new')">
                      <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 border border-amber-100/30 flex items-center justify-center shrink-0">
                          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                      </div>
                      <span class="text-[11px] font-bold text-slate-700">Expense</span>
                  </button>
                  <button type="button" class="flex-1 bg-white border border-gray-100 shadow-sm rounded-2xl py-3 flex flex-col items-center justify-center gap-1.5 transition-all duration-200 active:scale-95" @click="router.push('/reports')">
                      <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 border border-purple-100/30 flex items-center justify-center shrink-0">
                          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                      </div>
                      <span class="text-[11px] font-bold text-slate-700">Reports</span>
                  </button>
              </div>
          </div>

          <!-- Desktop header -->
          <div class="hidden lg:flex flex-row justify-between items-center mb-8 gap-4 px-0">
              <div>
                  <h1 class="text-[22px] font-normal text-google-text mb-0.5">Good morning, {{ firstName }}</h1>
                  <p class="text-google-muted text-[13px]">Here's your business snapshot for today.</p>
              </div>
              <div data-tour="quick-actions" class="flex gap-2 shrink-0">
                  <button @click="startTour()" class="btn-outline btn-sm" title="Take a guided tour">
                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Tour
                  </button>
                  <button @click="router.push('/expenses/new')" class="btn-outline btn-sm">Record expense</button>
                  <button @click="router.push('/invoices/new')" class="btn-primary btn-sm">New invoice</button>
              </div>
          </div>

          <!-- Stat Cards (desktop) -->
          <div data-tour="stat-cards" class="hidden lg:grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4 px-4 lg:px-0">
              <!-- Collected -->
              <div class="gpay-hero-balance relative overflow-hidden group cursor-pointer col-span-2 lg:col-span-1 hidden lg:block"
                @click="router.push('/invoices?status=paid')">
                  <div class="absolute -right-6 -top-6 w-20 h-20 bg-primary-500/20 rounded-full blur-xl group-hover:bg-primary-400/30 transition-all duration-500"></div>
                  <div class="absolute -right-2 bottom-0 w-12 h-12 bg-primary-500/10 rounded-full blur-lg"></div>
                  <div class="relative z-10">
                      <div class="flex items-center justify-between mb-3">
                          <span class="text-gray-400 text-[10px] font-semibold uppercase tracking-widest">Collected</span>
                          <div class="w-6 h-6 rounded-md bg-emerald-500/20 flex items-center justify-center">
                              <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                          </div>
                      </div>
                      <div class="text-[22px] font-extrabold tabular-nums tracking-tight leading-none">{{ inr(stats.total_paid_month || 0) }}</div>
                      <div class="mt-2 text-gray-500 text-[11px]">This month</div>
                  </div>
              </div>
              <!-- Outstanding -->
              <div class="card card-body flex flex-col justify-between group hover:shadow-gpay transition-shadow cursor-pointer"
                @click="router.push('/invoices?status=sent')">
                  <div class="flex items-center justify-between mb-3">
                      <span class="text-gray-400 text-[10px] font-semibold uppercase tracking-widest">Pending</span>
                      <div class="w-6 h-6 rounded-md bg-blue-50 flex items-center justify-center">
                          <svg class="w-3.5 h-3.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                      </div>
                  </div>
                  <div class="text-[22px] font-extrabold tabular-nums tracking-tight text-gray-900 leading-none">{{ inr(stats.total_due || 0) }}</div>
                  <div class="mt-2 text-gray-400 text-[11px]">Outstanding invoices</div>
              </div>
              <!-- Expenses -->
              <div class="card card-body flex flex-col justify-between group hover:shadow-gpay transition-shadow cursor-pointer"
                @click="router.push('/expenses')">
                  <div class="flex items-center justify-between mb-3">
                      <span class="text-gray-400 text-[10px] font-semibold uppercase tracking-widest">Expenses</span>
                      <div class="w-6 h-6 rounded-md bg-amber-50 flex items-center justify-center">
                          <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                      </div>
                  </div>
                  <div class="text-[22px] font-extrabold tabular-nums tracking-tight text-gray-900 leading-none">{{ inr(stats.total_expenses_month || 0) }}</div>
                  <div class="mt-2 text-gray-400 text-[11px]">Recorded this month</div>
              </div>
              <!-- Overdue -->
              <div class="card card-body flex flex-col justify-between group transition-shadow cursor-pointer"
                   :class="stats.overdue_amount > 0 ? 'bg-danger-50 border-danger-100' : ''"
                   @click="router.push('/invoices?status=overdue')">
                  <div class="flex items-center justify-between mb-3">
                      <span class="text-[10px] font-semibold uppercase tracking-widest"
                            :class="stats.overdue_amount > 0 ? 'text-red-400' : 'text-gray-400'">Overdue</span>
                      <div class="w-6 h-6 rounded-md flex items-center justify-center"
                           :class="stats.overdue_amount > 0 ? 'bg-red-100' : 'bg-gray-100'">
                          <svg class="w-3.5 h-3.5" :class="stats.overdue_amount > 0 ? 'text-red-500' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                      </div>
                  </div>
                  <div class="text-[22px] font-extrabold tabular-nums tracking-tight leading-none"
                       :class="stats.overdue_amount > 0 ? 'text-red-600' : 'text-gray-900'">
                      {{ inr(stats.overdue_amount || 0) }}
                  </div>
                  <div class="mt-2 text-[11px]"
                       :class="stats.overdue_amount > 0 ? 'text-red-500 font-semibold' : 'text-gray-400'">
                      {{ stats.overdue_amount > 0 ? 'Action needed' : 'All invoices on time' }}
                  </div>
              </div>
          </div>

          <!-- All-time Balance strip (desktop) -->
          <div data-tour="balance-strip" class="hidden lg:flex items-center justify-between gap-6 mt-4 px-5 py-4 rounded-2xl bg-white/80 border border-gray-100 shadow-soft">
              <div class="flex items-center gap-8">
                  <div>
                      <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Total Billed</p>
                      <p class="text-base font-bold text-gray-900 tabular-nums mt-0.5">{{ inr(summary.total_billed || 0) }}</p>
                  </div>
                  <div class="h-8 w-px bg-gray-200"></div>
                  <div>
                      <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Total Collected</p>
                      <p class="text-base font-bold text-emerald-600 tabular-nums mt-0.5">{{ inr(summary.total_collected || 0) }}</p>
                  </div>
                  <div class="h-8 w-px bg-gray-200"></div>
                  <div>
                      <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Total Expenses</p>
                      <p class="text-base font-bold text-amber-600 tabular-nums mt-0.5">{{ inr(totalExpenses) }}</p>
                  </div>
              </div>
              <div class="text-right">
                  <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest">Balance</p>
                  <p class="text-xl font-extrabold tabular-nums mt-0.5" :class="balance >= 0 ? 'text-emerald-600' : 'text-red-600'">{{ inr(balance) }}</p>
              </div>
          </div>

          <!-- All-time Balance strip (mobile) -->
          <div class="lg:hidden mx-4 mb-4 px-4 py-3 rounded-2xl bg-white border border-gray-100 shadow-soft">
              <div class="flex items-center justify-between">
                  <div class="flex items-center gap-4 text-xs">
                      <div><span class="text-gray-400">Billed</span><p class="font-bold text-gray-900 tabular-nums">{{ inrCompact(summary.total_billed || 0) }}</p></div>
                      <div><span class="text-gray-400">Collected</span><p class="font-bold text-emerald-600 tabular-nums">{{ inrCompact(summary.total_collected || 0) }}</p></div>
                      <div><span class="text-gray-400">Expenses</span><p class="font-bold text-amber-600 tabular-nums">{{ inrCompact(totalExpenses) }}</p></div>
                  </div>
                  <div class="text-right">
                      <span class="text-[10px] text-gray-400 font-semibold uppercase">Balance</span>
                      <p class="text-base font-extrabold tabular-nums" :class="balance >= 0 ? 'text-emerald-600' : 'text-red-600'">{{ inrCompact(balance) }}</p>
                  </div>
              </div>
          </div>

          <!-- Mobile: Recent activity -->
          <div class="lg:hidden px-4 pb-8">
              <div class="bg-white rounded-[2rem] border border-gray-100 shadow-sm overflow-hidden animate-fade-in-up mt-2">
                  <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                      <h2 class="text-[11px] font-bold text-gray-400 uppercase tracking-widest">Recent activity</h2>
                      <RouterLink to="/invoices" class="text-xs text-primary-600 font-bold hover:text-primary-700 transition-colors">See all</RouterLink>
                  </div>
                  <div v-if="loading" class="py-10 flex justify-center">
                      <div class="w-7 h-7 border-2 border-primary-100 border-t-primary-600 rounded-full animate-spin"></div>
                  </div>
                  <div v-else-if="!recent.length" class="px-5 py-10 text-center">
                      <div class="w-14 h-14 rounded-2xl bg-primary-50 text-primary-600 flex items-center justify-center mx-auto mb-3">
                          <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                      </div>
                      <p class="font-semibold text-ink">No bills yet</p>
                      <p class="text-sm text-google-muted mt-1">Create your first invoice</p>
                      <button type="button" class="btn-primary btn-sm mt-4" @click="router.push('/invoices/new')">New bill</button>
                  </div>
                  <div v-else>
                      <div
                        v-for="(inv, idx) in recent.slice(0, 8)"
                        :key="inv.id"
                        class="flex items-center gap-4 py-3.5 px-5 border-b border-gray-50 last:border-b-0 cursor-pointer active:bg-gray-50/50 active:scale-[0.99] transition-all duration-200"
                        :class="{ 'list-item-1': idx < 4 }"
                        :style="idx >= 4 ? {} : { animationDelay: (idx * 0.05) + 's' }"
                        @click="router.push(`/invoices/${inv.id}`)"
                      >
                          <div class="w-10 h-10 rounded-xl flex items-center justify-center font-bold text-sm border border-gray-100/50 shrink-0 select-none shadow-sm" :class="avatarColor(inv.client_name)">
                              {{ inv.client_name?.charAt(0)?.toUpperCase() }}
                          </div>
                          <div class="flex-1 min-w-0">
                              <p class="text-[14px] font-bold text-slate-800 truncate leading-snug">{{ inv.client_name }}</p>
                              <p class="text-[11px] text-gray-400 mt-0.5 font-medium">{{ inv.number }} · {{ fmtDateShort(inv.issue_date) }}</p>
                          </div>
                          <div class="text-right shrink-0">
                              <p class="text-[14px] font-black text-slate-800 tabular-nums leading-none">{{ inr(inv.total) }}</p>
                              <span class="inline-block mt-1.5 text-[9px] font-extrabold px-2 py-0.5 rounded-md uppercase tracking-wider whitespace-nowrap" :class="{
                                'text-emerald-700 bg-emerald-50': inv.status === 'paid',
                                'text-amber-700 bg-amber-50': inv.status === 'draft',
                                'text-blue-700 bg-blue-50': inv.status === 'sent',
                                'text-red-700 bg-red-50': inv.status === 'overdue',
                                'text-purple-700 bg-purple-50': inv.status === 'partial',
                              }">{{ inv.status }}</span>
                          </div>
                      </div>
                  </div>
              </div>
          </div>

          <!-- Chart & Actions Row -->
          <div class="hidden lg:grid grid-cols-1 lg:grid-cols-3 gap-4 mt-4 px-4 lg:px-0">
              <!-- Revenue Chart -->
              <div data-tour="revenue-chart" class="col-span-1 lg:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-soft p-6 h-[300px] flex-col hidden sm:flex relative">
                  <div class="flex justify-between items-center mb-5">
                      <div>
                          <h2 class="font-bold text-gray-900 text-[14px]">Revenue</h2>
                          <p class="text-[11px] text-gray-400 mt-0.5">Collections — last 6 months</p>
                      </div>
                      <RouterLink to="/reports" class="text-[11px] font-semibold text-primary-600 hover:text-primary-700">Reports →</RouterLink>
                  </div>
                  <div class="flex-1 flex items-end gap-2 px-1 pb-0 relative">
                      <div class="absolute inset-0 flex flex-col justify-between pointer-events-none pb-7 px-1">
                          <div class="w-full border-t border-gray-100 border-dashed"></div>
                          <div class="w-full border-t border-gray-100 border-dashed"></div>
                          <div class="w-full border-t border-gray-100 border-dashed"></div>
                      </div>
                      <div v-for="(m, i) in chartMonths" :key="m.key"
                        class="flex-1 flex flex-col items-center gap-1.5 relative z-10 group"
                        :title="`${m.label}: ${inr(m.value)}`">
                        <span class="text-[10px] font-semibold text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap">{{ inr(m.value) }}</span>
                        <div class="w-full rounded-t-md transition-all duration-700 ease-out"
                          :style="{ height: m.pct + '%' }"
                          :class="i === 5 ? 'bg-primary-500 shadow-[0_0_16px_rgba(37,99,235,0.2)]' : 'bg-primary-100/80 hover:bg-primary-200'">
                        </div>
                        <span class="text-[10px] font-semibold uppercase tracking-wide"
                          :class="i === 5 ? 'text-primary-600' : 'text-gray-400'">{{ m.label }}</span>
                      </div>
                  </div>
                  <div v-if="!loading && chartMonths.every(m => m.value === 0)"
                    class="absolute inset-0 flex flex-col items-center justify-center text-center pointer-events-none">
                      <p class="text-[13px] text-gray-400 font-medium">No payment data yet</p>
                  </div>
              </div>

              <!-- Action Required -->
              <div class="col-span-1 bg-white rounded-2xl border border-gray-100 shadow-soft p-5 flex flex-col h-[300px]">
                  <div class="flex justify-between items-center mb-4">
                      <h2 class="font-bold text-gray-900 text-[14px]">Action Required</h2>
                      <span v-if="overdue.length + draftInvoices.length > 0"
                        class="bg-red-100 text-red-600 text-[10px] font-bold px-2 py-0.5 rounded-md">
                        {{ overdue.length + draftInvoices.length }}
                      </span>
                  </div>
                  <div class="space-y-2 flex-1 overflow-y-auto pr-0.5 custom-scrollbar">
                      <div v-if="!loading && overdue.length === 0 && draftInvoices.length === 0"
                        class="h-full flex flex-col items-center justify-center text-center px-4">
                          <div class="w-10 h-10 bg-emerald-50 rounded-xl flex items-center justify-center mb-2">
                              <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                          </div>
                          <p class="text-[13px] font-bold text-gray-900">All caught up!</p>
                          <p class="text-[11px] text-gray-400 mt-0.5">No pending actions.</p>
                      </div>
                      <div v-for="inv in overdue" :key="inv.id"
                        class="p-3 rounded-xl border border-red-100 bg-red-50/40 flex flex-col gap-1 cursor-pointer hover:bg-red-50 transition-colors"
                        @click="router.push(`/invoices/${inv.id}`)">
                          <div class="flex justify-between items-center">
                              <span class="font-semibold text-gray-900 text-[12px] truncate pr-2">{{ inv.client_name }}</span>
                              <span class="font-bold text-red-600 text-[12px] tabular-nums shrink-0">{{ inr(inv.amount_due) }}</span>
                          </div>
                          <span class="text-[11px] text-gray-400">{{ inv.number }} · Overdue</span>
                      </div>
                      <div v-for="inv in draftInvoices" :key="inv.id"
                        class="p-3 rounded-xl border border-gray-100 bg-gray-50/60 flex flex-col gap-1 cursor-pointer hover:bg-white hover:border-gray-200 transition-colors"
                        @click="router.push(`/invoices/${inv.id}`)">
                          <div class="flex justify-between items-center">
                              <span class="font-semibold text-gray-900 text-[12px]">Draft Invoice</span>
                              <span class="font-bold text-gray-900 text-[12px] tabular-nums">{{ inr(inv.total) }}</span>
                          </div>
                          <span class="text-[11px] text-gray-400 truncate">{{ inv.client_name }}</span>
                      </div>
                  </div>
              </div>
          </div>

          <!-- Bottom Row: Recent Invoices + Top Clients -->
          <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-4">
              <!-- Recent Invoices -->
              <div data-tour="recent-invoices" class="col-span-1 lg:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-soft p-6 flex flex-col">
                  <div class="flex justify-between items-center mb-4">
                      <h2 class="font-bold text-gray-900 text-[14px]">Recent Invoices</h2>
                      <RouterLink to="/invoices" class="text-[11px] font-semibold text-primary-600 hover:text-primary-700">View All →</RouterLink>
                  </div>
                  <div v-if="loading" class="flex justify-center p-8">
                      <div class="w-5 h-5 border-2 border-primary-100 border-t-primary-500 rounded-full animate-spin"></div>
                  </div>
                  <div v-else-if="!recent.length" class="text-center p-8 text-gray-400 text-[13px]">No invoices yet.</div>
                  <div v-else class="flex-1 overflow-x-auto -mx-1">
                      <table class="w-full text-left">
                          <thead>
                              <tr class="border-b border-gray-100">
                                  <th class="pb-2.5 px-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wider whitespace-nowrap">Customer</th>
                                  <th class="pb-2.5 px-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wider whitespace-nowrap">Amount</th>
                                  <th class="pb-2.5 px-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wider whitespace-nowrap">Status</th>
                                  <th class="pb-2.5 px-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wider text-right whitespace-nowrap">Date</th>
                              </tr>
                          </thead>
                          <tbody class="divide-y divide-gray-50">
                              <tr v-for="inv in recent.slice(0, 6)" :key="inv.id"
                                class="hover:bg-gray-50/60 transition-colors cursor-pointer"
                                @click="router.push(`/invoices/${inv.id}`)">
                                  <td class="py-3 px-1">
                                      <div class="text-[13px] font-semibold text-gray-900 truncate max-w-[160px]">{{ inv.client_name }}</div>
                                      <div class="text-[11px] text-gray-400 mt-0.5">{{ inv.number }}</div>
                                  </td>
                                  <td class="py-3 px-1 text-[13px] font-semibold text-gray-700 tabular-nums whitespace-nowrap">{{ inr(inv.total) }}</td>
                                  <td class="py-3 px-1">
                                      <span class="text-[10px] font-semibold px-2 py-0.5 rounded-md uppercase tracking-wide whitespace-nowrap"
                                          :class="{
                                              'text-emerald-700 bg-emerald-50': inv.status === 'paid',
                                              'text-amber-700 bg-amber-50': inv.status === 'draft',
                                              'text-blue-700 bg-blue-50': inv.status === 'sent',
                                              'text-red-700 bg-red-50': inv.status === 'overdue',
                                              'text-purple-700 bg-purple-50': inv.status === 'partial',
                                          }">
                                          {{ inv.status }}
                                      </span>
                                  </td>
                                  <td class="py-3 px-1 text-right text-[11px] text-gray-400 whitespace-nowrap">{{ fmtDateShort(inv.issue_date) }}</td>
                              </tr>
                          </tbody>
                      </table>
                  </div>
              </div>

              <!-- Top Clients -->
              <div class="col-span-1 bg-white rounded-2xl border border-gray-100 shadow-soft p-6 flex flex-col">
                  <div class="flex justify-between items-center mb-4">
                      <h2 class="font-bold text-gray-900 text-[14px]">Top Clients</h2>
                      <RouterLink to="/clients" class="text-[11px] font-semibold text-primary-600 hover:text-primary-700">View All →</RouterLink>
                  </div>
                  <div v-if="loading" class="flex justify-center p-8">
                      <div class="w-5 h-5 border-2 border-primary-100 border-t-primary-500 rounded-full animate-spin"></div>
                  </div>
                  <div v-else-if="!topClients.length" class="text-center p-8 text-gray-400 text-[13px]">No data yet.</div>
                  <div v-else class="space-y-3 flex-1">
                      <div v-for="client in topClients" :key="client.id"
                        class="flex items-center gap-3 group cursor-pointer p-2 -mx-2 rounded-xl hover:bg-gray-50 transition-colors"
                        @click="router.push(`/clients/${client.id}`)">
                          <div class="w-9 h-9 rounded-lg flex items-center justify-center font-bold text-sm border shrink-0" :class="avatarColor(client.name)">
                              {{ client.name?.charAt(0)?.toUpperCase() }}
                          </div>
                          <div class="flex-1 min-w-0">
                              <div class="text-[13px] font-semibold text-gray-900 truncate">{{ client.name }}</div>
                              <div class="text-[11px] text-gray-400 mt-0.5">{{ client.count }} invoice{{ client.count !== 1 ? 's' : '' }}</div>
                          </div>
                          <div class="text-[13px] font-bold text-gray-900 tabular-nums shrink-0">{{ inrCompact(client.total) }}</div>
                      </div>
                  </div>
              </div>
          </div>
      </div>
  </div>
</template>
