<script setup>
import { ref, computed } from 'vue'
import { list } from '../../api'
import { inr, inrCompact } from '../../utils/currency'
import { fmtDateShort } from '../../utils/date'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useTour } from '../../composables/useTour'
import { useListRefresh } from '../../composables/useListRefresh'

const router    = useRouter()
const authStore = useAuthStore()

const { startTour, isTourSeen } = useTour('dashboard', [
  { target: '[data-tour="stat-cards"]',    title: 'Business Snapshot', text: 'Outstanding amounts, this month collections & expenses, and overdue alerts.' },
  { target: '[data-tour="revenue-chart"]', title: 'Revenue Trend',     text: 'Collections received over the last 6 months.' },
  { target: '[data-tour="quick-actions"]', title: 'Quick Actions',     text: 'Shortcuts to common tasks — one click away.' },
  { target: '[data-tour="recent-invoices"]',title: 'Recent Invoices',  text: 'Your latest bills with status.' },
])

const stats  = ref({
  total_outstanding: 0,
  pending_count: 0,
  total_paid_month: 0,
  total_expenses_month: 0,
  overdue_count: 0,
  draft_count: 0,
  overdue_amount: 0,
})
const summary        = ref({ total_billed: 0, total_collected: 0, total_outstanding: 0, pending_count: 0 })
const recent         = ref([])
const overdue        = ref([])
const monthlyRevenue = ref([])
const loading        = ref(true)

const monthLabel = computed(() =>
  new Date().toLocaleDateString('en-IN', { month: 'short', year: 'numeric' })
)

const netThisMonth = computed(() =>
  parseFloat(stats.value.total_paid_month || 0) - parseFloat(stats.value.total_expenses_month || 0)
)

const chartMonths = computed(() => {
  const months = []
  const now = new Date()
  for (let i = 5; i >= 0; i--) {
    const d   = new Date(now.getFullYear(), now.getMonth() - i, 1)
    const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
    months.push({ key, label: d.toLocaleString('default', { month: 'short' }) })
  }
  const revenueMap = Object.fromEntries(monthlyRevenue.value.map(r => [r.month, parseFloat(r.collected || 0)]))
  const withData   = months.map(m => ({ ...m, value: revenueMap[m.key] || 0 }))
  const max        = Math.max(...withData.map(m => m.value), 1)
  return withData.map(m => ({ ...m, pct: Math.max(4, Math.round((m.value / max) * 100)) }))
})

const chartSixMonthTotal = computed(() =>
  chartMonths.value.reduce((sum, m) => sum + m.value, 0)
)

async function load() {
  loading.value = true
  try {
    const [sR, sumR, rR, oR, mR] = await Promise.all([
      list('Dashboard:stats'),
      list('Dashboard:summary'),
      list('Invoice', { sort_by: 'i.created_at', sort_order: 'desc', limit: 8 }),
      list('Invoice:overdue', { limit: 10 }),
      list('Dashboard:monthlyRevenue'),
    ])
    stats.value   = sR.data?.data?.[0] || {}
    summary.value = sumR.data?.data?.[0] || {}
    recent.value  = rR.data?.data || []
    overdue.value = oR.data?.data || []
    monthlyRevenue.value = mR.data?.data || []
    stats.value.overdue_amount = overdue.value.reduce((sum, inv) => sum + parseFloat(inv.amount_due || 0), 0)
  } catch {}
  loading.value = false
  setTimeout(() => { if (!isTourSeen() && !loading.value) startTour() }, 800)
}

useListRefresh(load)

const statusConfig = {
  paid:      { label: 'Paid',     cls: 'badge-green'  },
  sent:      { label: 'Sent',     cls: 'badge-blue'   },
  draft:     { label: 'Draft',    cls: 'badge-gray'   },
  overdue:   { label: 'Overdue',  cls: 'badge-red'    },
  partial:   { label: 'Partial',  cls: 'badge-yellow' },
  cancelled: { label: 'Void',     cls: 'badge-gray'   },
}

const firstName = computed(() => authStore.user?.name?.split(' ')[0] || 'there')

const quickActions = [
  { label: 'New Invoice',  path: '/invoices/new', color: 'bg-indigo-500',  icon: 'M12 4v16m8-8H4' },
  { label: 'New Quote',    path: '/quotes/new',   color: 'bg-violet-500',  icon: 'M12 4v16m8-8H4' },
  { label: 'Add Client',   path: '/clients/new',  color: 'bg-cyan-500',    icon: 'M12 4v16m8-8H4' },
  { label: 'Add Expense',  path: '/expenses/new', color: 'bg-rose-500',    icon: 'M12 4v16m8-8H4' },
]
</script>

<template>
  <div class="gpay-screen overflow-y-auto custom-scrollbar">
    <div class="max-w-[1280px] mx-auto w-full px-4 py-5 lg:px-8 lg:py-7 animate-doc">

      <!-- ── Header ── -->
      <div class="mb-6 flex items-center justify-between">
        <div>
          <p class="text-xs font-bold text-primary-600 uppercase tracking-widest mb-0.5">Welcome back</p>
          <h1 class="text-2xl font-bold text-gray-900 tracking-tight">{{ firstName }}</h1>
        </div>
        <span class="hidden sm:block text-xs font-medium text-gray-400 bg-white border border-gray-200 px-3 py-1.5 rounded-lg shadow-soft">
          {{ new Date().toLocaleDateString('en-IN', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' }) }}
        </span>
      </div>

      <!-- ── Mobile hero ── -->
      <div class="lg:hidden mb-5" @click="router.push('/invoices?status=sent')">
        <div class="gpay-hero-balance">
          <div class="relative z-10">
            <div class="flex items-start justify-between">
              <p class="text-white/80 text-sm font-medium">Outstanding to collect</p>
              <span v-if="stats.overdue_count > 0"
                class="text-[10px] font-bold uppercase tracking-wide bg-white/20 px-2 py-1 rounded-full">
                {{ stats.overdue_count }} overdue
              </span>
            </div>
            <p class="text-3xl font-bold text-white mt-2 tabular-nums">{{ inr(stats.total_outstanding || 0) }}</p>
            <p class="text-white/60 text-xs mt-1">
              {{ stats.pending_count || 0 }} invoice{{ stats.pending_count === 1 ? '' : 's' }} awaiting payment
            </p>
            <div class="flex gap-6 mt-4 pt-4 border-t border-white/15">
              <div>
                <p class="text-white/60 text-[11px] font-medium uppercase tracking-wide">Collected · {{ monthLabel }}</p>
                <p class="text-lg font-bold text-white tabular-nums mt-0.5">{{ inrCompact(stats.total_paid_month || 0) }}</p>
              </div>
              <div>
                <p class="text-white/60 text-[11px] font-medium uppercase tracking-wide">Expenses · {{ monthLabel }}</p>
                <p class="text-lg font-bold text-white tabular-nums mt-0.5">{{ inrCompact(stats.total_expenses_month || 0) }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Stat cards ── -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4 mb-5" data-tour="stat-cards">

        <!-- Outstanding -->
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-soft cursor-pointer hover:border-indigo-200 hover:shadow-gpay transition-all"
          @click="router.push('/invoices?status=sent')">
          <div class="flex items-center justify-between mb-3">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Outstanding</p>
            <div class="w-8 h-8 rounded-lg bg-indigo-50 flex items-center justify-center">
              <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
          </div>
          <p v-if="loading" class="skeleton h-7 w-24 rounded"></p>
          <p v-else class="text-xl lg:text-2xl font-bold text-gray-900 tabular-nums tracking-tight">{{ inrCompact(stats.total_outstanding || 0) }}</p>
          <p class="text-xs text-gray-400 mt-1">
            {{ stats.pending_count || 0 }} unpaid invoice{{ stats.pending_count === 1 ? '' : 's' }}
          </p>
        </div>

        <!-- Collected this month -->
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-soft cursor-pointer hover:border-emerald-200 hover:shadow-gpay transition-all"
          @click="router.push('/invoices?status=paid')">
          <div class="flex items-center justify-between mb-3">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Collected</p>
            <div class="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center">
              <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
          </div>
          <p v-if="loading" class="skeleton h-7 w-24 rounded"></p>
          <p v-else class="text-xl lg:text-2xl font-bold text-gray-900 tabular-nums tracking-tight">{{ inrCompact(stats.total_paid_month || 0) }}</p>
          <p class="text-xs text-gray-400 mt-1">payments in {{ monthLabel }}</p>
        </div>

        <!-- Expenses this month -->
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-soft cursor-pointer hover:border-rose-200 hover:shadow-gpay transition-all"
          @click="router.push('/expenses')">
          <div class="flex items-center justify-between mb-3">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Expenses</p>
            <div class="w-8 h-8 rounded-lg bg-rose-50 flex items-center justify-center">
              <svg class="w-4 h-4 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
              </svg>
            </div>
          </div>
          <p v-if="loading" class="skeleton h-7 w-24 rounded"></p>
          <p v-else class="text-xl lg:text-2xl font-bold text-gray-900 tabular-nums tracking-tight">{{ inrCompact(stats.total_expenses_month || 0) }}</p>
          <p class="text-xs text-gray-400 mt-1">spent in {{ monthLabel }}</p>
        </div>

        <!-- Overdue -->
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-soft cursor-pointer hover:border-red-200 hover:shadow-gpay transition-all"
          @click="router.push('/invoices?status=overdue')">
          <div class="flex items-center justify-between mb-3">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Overdue</p>
            <div class="w-8 h-8 rounded-lg flex items-center justify-center"
              :class="stats.overdue_count > 0 ? 'bg-red-50' : 'bg-gray-50'">
              <svg class="w-4 h-4" :class="stats.overdue_count > 0 ? 'text-red-500' : 'text-gray-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
              </svg>
            </div>
          </div>
          <p v-if="loading" class="skeleton h-7 w-24 rounded"></p>
          <p v-else class="text-xl lg:text-2xl font-bold tabular-nums tracking-tight"
            :class="stats.overdue_count > 0 ? 'text-red-600' : 'text-gray-900'">
            {{ stats.overdue_count || 0 }}
          </p>
          <p class="text-xs text-gray-400 mt-1">
            {{ stats.overdue_count > 0 ? inrCompact(stats.overdue_amount || 0) + ' overdue' : 'all clear' }}
          </p>
        </div>
      </div>

      <!-- Draft hint -->
      <div v-if="!loading && stats.draft_count > 0"
        class="mb-4 flex items-center justify-between gap-3 px-4 py-3 rounded-xl bg-amber-50 border border-amber-100 cursor-pointer hover:bg-amber-100/80 transition-colors"
        @click="router.push('/invoices?status=draft')">
        <p class="text-sm text-amber-800">
          <span class="font-semibold">{{ stats.draft_count }} draft invoice{{ stats.draft_count === 1 ? '' : 's' }}</span>
          waiting to be sent
        </p>
        <span class="text-xs font-semibold text-amber-700 shrink-0">Review →</span>
      </div>

      <!-- ── Main grid: Chart + Quick actions ── -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">

        <!-- Revenue chart -->
        <div class="lg:col-span-2 bg-white border border-gray-200 rounded-xl p-5 shadow-soft" data-tour="revenue-chart">
          <div class="flex items-center justify-between mb-1">
            <div>
              <p class="text-sm font-semibold text-gray-900">Collections Trend</p>
              <p class="text-xs text-gray-400 mt-0.5">Payments received — last 6 months</p>
            </div>
            <span class="text-xs font-semibold text-primary-600 bg-primary-50 px-2.5 py-1 rounded-full tabular-nums">
              {{ inrCompact(chartSixMonthTotal) }} · 6 mo
            </span>
          </div>
          <p class="text-[11px] text-gray-400 mb-4">
            Lifetime collected: {{ inrCompact(summary.total_collected || 0) }}
          </p>
          <div class="flex items-end gap-2 h-32">
            <template v-for="month in chartMonths" :key="month.key">
              <div class="flex-1 flex flex-col items-center gap-1.5 group">
                <p class="text-[10px] font-bold text-gray-500 opacity-0 group-hover:opacity-100 transition-opacity tabular-nums">
                  {{ inrCompact(month.value) }}
                </p>
                <div class="w-full rounded-md transition-all duration-300 group-hover:opacity-90"
                  :style="{ height: month.pct + '%', background: month.pct > 60 ? 'linear-gradient(180deg, #6366f1, #4f46e5)' : month.pct > 30 ? 'linear-gradient(180deg, #818cf8, #6366f1)' : '#e0e7ff' }">
                </div>
                <p class="text-[11px] font-medium text-gray-400">{{ month.label }}</p>
              </div>
            </template>
          </div>
        </div>

        <!-- Quick actions -->
        <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-soft" data-tour="quick-actions">
          <p class="text-sm font-semibold text-gray-900 mb-4">Quick Actions</p>
          <div class="space-y-2">
            <button v-for="action in quickActions" :key="action.path"
              @click="router.push(action.path)"
              class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl bg-gray-50 hover:bg-primary-50 hover:border-primary-200 border border-transparent transition-all text-left group">
              <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white shrink-0"
                :class="action.color">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" :d="action.icon"/>
                </svg>
              </div>
              <span class="text-sm font-medium text-gray-700 group-hover:text-primary-700">{{ action.label }}</span>
              <svg class="w-4 h-4 text-gray-300 group-hover:text-primary-400 ml-auto shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </button>
          </div>

          <!-- This month net -->
          <div class="mt-4 p-3 rounded-xl border"
            :class="netThisMonth >= 0 ? 'bg-emerald-50 border-emerald-100' : 'bg-red-50 border-red-100'">
            <p class="text-[10px] font-bold uppercase tracking-wide mb-1"
              :class="netThisMonth >= 0 ? 'text-emerald-600' : 'text-red-600'">
              Net · {{ monthLabel }}
            </p>
            <p class="text-lg font-bold tabular-nums"
              :class="netThisMonth >= 0 ? 'text-emerald-700' : 'text-red-700'">
              {{ inr(netThisMonth) }}
            </p>
            <p class="text-[11px] mt-0.5"
              :class="netThisMonth >= 0 ? 'text-emerald-500' : 'text-red-500'">
              Collected − expenses this month
            </p>
          </div>
        </div>
      </div>

      <!-- ── Recent invoices ── -->
      <div class="bg-white border border-gray-200 rounded-xl shadow-soft overflow-hidden" data-tour="recent-invoices">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
          <p class="text-sm font-semibold text-gray-900">Recent Invoices</p>
          <button @click="router.push('/invoices')"
            class="text-xs font-semibold text-primary-600 hover:text-primary-700 transition-colors">
            View all →
          </button>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="p-5 space-y-3">
          <div v-for="i in 4" :key="i" class="flex items-center gap-4">
            <div class="skeleton w-8 h-8 rounded-full"></div>
            <div class="flex-1 space-y-1.5">
              <div class="skeleton h-3 w-32 rounded"></div>
              <div class="skeleton h-2.5 w-20 rounded"></div>
            </div>
            <div class="skeleton h-3 w-16 rounded"></div>
          </div>
        </div>

        <!-- Empty -->
        <div v-else-if="recent.length === 0" class="flex flex-col items-center justify-center py-12 text-center">
          <div class="w-12 h-12 bg-gray-100 rounded-2xl flex items-center justify-center mb-3">
            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
          </div>
          <p class="text-sm font-semibold text-gray-700">No invoices yet</p>
          <p class="text-xs text-gray-400 mt-1">Create your first invoice to get started</p>
          <button @click="router.push('/invoices/new')"
            class="mt-4 btn-primary btn-sm">
            Create Invoice
          </button>
        </div>

        <!-- Table -->
        <div v-else class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="bg-gray-50 border-b border-gray-100">
                <th class="px-5 py-2.5 text-left text-[11px] font-bold text-gray-400 uppercase tracking-wider">Invoice</th>
                <th class="px-4 py-2.5 text-left text-[11px] font-bold text-gray-400 uppercase tracking-wider hidden sm:table-cell">Client</th>
                <th class="px-4 py-2.5 text-left text-[11px] font-bold text-gray-400 uppercase tracking-wider hidden md:table-cell">Date</th>
                <th class="px-4 py-2.5 text-right text-[11px] font-bold text-gray-400 uppercase tracking-wider">Amount</th>
                <th class="px-5 py-2.5 text-center text-[11px] font-bold text-gray-400 uppercase tracking-wider">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="inv in recent" :key="inv.id"
                class="hover:bg-gray-50/80 cursor-pointer transition-colors"
                @click="router.push('/invoices/' + inv.id)">
                <td class="px-5 py-3">
                  <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-primary-50 flex items-center justify-center shrink-0">
                      <svg class="w-4 h-4 text-primary-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                      </svg>
                    </div>
                    <span class="text-sm font-semibold text-gray-800">{{ inv.number || '#' + inv.id }}</span>
                  </div>
                </td>
                <td class="px-4 py-3 hidden sm:table-cell">
                  <span class="text-sm text-gray-600 truncate max-w-[140px] block">{{ inv.client_name || '—' }}</span>
                </td>
                <td class="px-4 py-3 hidden md:table-cell">
                  <span class="text-sm text-gray-400">{{ fmtDateShort(inv.issue_date) }}</span>
                </td>
                <td class="px-4 py-3 text-right">
                  <span class="text-sm font-semibold text-gray-900 tabular-nums">{{ inr(inv.total || 0) }}</span>
                </td>
                <td class="px-5 py-3 text-center">
                  <span :class="(statusConfig[inv.status] || statusConfig.draft).cls" class="badge">
                    {{ (statusConfig[inv.status] || statusConfig.draft).label }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="h-6"></div>
    </div>

    <RouterLink to="/invoices/new" class="gpay-fab lg:hidden">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
      </svg>
    </RouterLink>
  </div>
</template>
