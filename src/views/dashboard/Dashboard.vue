<script setup>
import { ref, computed, onMounted } from 'vue'
import { list } from '../../api'
import { inr, inrCompact } from '../../utils/currency'
import { fmtDateShort } from '../../utils/date'
import { statusBadge, statusLabel } from '../../utils/invoice'
import { useAuthStore } from '../../stores/auth'
import { useRouter } from 'vue-router'

const auth   = useAuthStore()
const router = useRouter()

const stats   = ref({ total_due: 0, total_paid_month: 0, overdue_count: 0, draft_count: 0 })
const recent  = ref([])
const overdue = ref([])
const trend   = ref([])
const loading = ref(true)

const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return 'Good morning'
  if (h < 17) return 'Good afternoon'
  return 'Good evening'
})
const firstName = computed(() => auth.user?.name?.split(' ')[0] || '')

// Build last 6 months labels + fetch revenue per month
async function loadTrend() {
  const months = []
  const now = new Date()
  for (let i = 5; i >= 0; i--) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1)
    months.push({
      label: d.toLocaleString('default', { month: 'short' }),
      from:  `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-01`,
      to:    new Date(d.getFullYear(), d.getMonth()+1, 0).toISOString().split('T')[0],
      total: 0,
    })
  }
  await Promise.all(months.map(async m => {
    try {
      const res = await list('Invoice', {
        'filter.from_date': m.from,
        'filter.to_date':   m.to,
        limit: 500,
      })
      const data = res.data?.data || []
      m.total = data.reduce((s, i) => s + parseFloat(i.total || 0), 0)
    } catch {}
  }))
  trend.value = months
}

const trendMax = computed(() => Math.max(...trend.value.map(m => m.total), 1))

onMounted(async () => {
  try {
    const [sR, rR, oR] = await Promise.all([
      list('Dashboard:stats'),
      list('Invoice', { sort_by: 'i.created_at', sort_order: 'desc', limit: 6 }),
      list('Invoice:overdue', { limit: 5 }),
    ])
    stats.value   = sR.data?.data?.[0] || {}
    recent.value  = rR.data?.data  || []
    overdue.value = oR.data?.data  || []
  } catch {}
  loading.value = false
  loadTrend()
})

const quickActions = [
  { label: 'New Bill',     to: '/invoices/new', bg: 'bg-blue-500',   icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
  { label: 'New Quote',    to: '/quotes/new',   bg: 'bg-amber-500',  icon: 'M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2' },
  { label: 'Add Customer', to: '/clients/new',  bg: 'bg-emerald-500',icon: 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z' },
  { label: 'Expenses',     to: '/expenses',     bg: 'bg-orange-500', icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z' },
  { label: 'Products',     to: '/products',     bg: 'bg-purple-500', icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' },
  { label: 'GST Filing',   to: '/gst-returns',  bg: 'bg-teal-500',   icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
]

function remindWhatsApp(inv) {
  const msg = `Dear ${inv.client_name},\n\nThis is a gentle reminder that your invoice *${inv.number}* for *${inr(inv.amount_due)}* is overdue by ${inv.days_overdue} day(s).\n\nKindly arrange payment at the earliest.\n\nThank you!`
  window.open('https://wa.me/?text=' + encodeURIComponent(msg), '_blank')
}
</script>

<template>
  <div class="flex flex-col gap-4 lg:h-full">

    <!-- ===== HERO CARD ===== -->
    <div class="rounded-3xl bg-gradient-to-br from-primary-600 via-primary-600 to-blue-700 text-white px-6 py-4 shadow-xl shadow-primary-200 relative overflow-hidden shrink-0">
      <div class="absolute -top-8 -right-8 w-40 h-40 bg-white/5 rounded-full"></div>
      <div class="absolute -bottom-12 -right-4 w-56 h-56 bg-white/5 rounded-full"></div>
      <div class="relative">
        <p class="text-primary-200 text-sm font-medium">{{ greeting }}, {{ firstName }}!</p>
        <div class="mt-3 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
          <div>
            <p class="text-primary-200 text-xs uppercase tracking-widest font-semibold">Money to Collect</p>
            <p class="text-3xl lg:text-4xl font-bold mt-0.5 tracking-tight">{{ inrCompact(stats.total_due || 0) }}</p>
            <p class="text-primary-200 text-sm mt-0.5">from pending bills</p>
          </div>
          <div class="flex gap-3 sm:gap-5">
            <div class="bg-white/10 rounded-2xl px-3 py-2.5 backdrop-blur-sm">
              <p class="text-primary-200 text-xs font-medium uppercase tracking-wide">This Month</p>
              <p class="text-lg font-bold mt-0.5">{{ inrCompact(stats.total_paid_month || 0) }}</p>
              <p class="text-primary-200 text-xs mt-0.5">received</p>
            </div>
            <div class="bg-white/10 rounded-2xl px-3 py-2.5 backdrop-blur-sm">
              <p class="text-primary-200 text-xs font-medium uppercase tracking-wide">Overdue</p>
              <p class="text-lg font-bold mt-0.5 text-red-300">{{ stats.overdue_count || 0 }}</p>
              <p class="text-primary-200 text-xs mt-0.5">late bills</p>
            </div>
            <div class="hidden sm:block bg-white/10 rounded-2xl px-3 py-2.5 backdrop-blur-sm">
              <p class="text-primary-200 text-xs font-medium uppercase tracking-wide">Drafts</p>
              <p class="text-lg font-bold mt-0.5 text-yellow-300">{{ stats.draft_count || 0 }}</p>
              <p class="text-primary-200 text-xs mt-0.5">not sent</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ===== QUICK ACTIONS ===== -->
    <div class="shrink-0">
      <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-2">Quick Actions</p>
      <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
        <RouterLink v-for="a in quickActions" :key="a.label" :to="a.to"
          class="flex flex-col items-center gap-2 p-3 rounded-2xl bg-white border border-gray-100 shadow-sm hover:shadow-md hover:-translate-y-0.5 active:scale-95 transition-all duration-150 group">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-sm shrink-0" :class="a.bg">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" :d="a.icon" />
            </svg>
          </div>
          <span class="text-xs font-semibold text-gray-600 text-center leading-tight group-hover:text-gray-900">{{ a.label }}</span>
        </RouterLink>
      </div>
    </div>

    <!-- ===== MAIN CONTENT GRID ===== -->
    <div class="grid lg:grid-cols-5 gap-4 lg:flex-1 lg:min-h-0">

      <!-- Recent Bills + Revenue Chart -->
      <div class="lg:col-span-3 flex flex-col gap-4 lg:min-h-0">

        <!-- Recent Bills -->
        <div class="card overflow-hidden flex flex-col lg:min-h-0 lg:flex-1">
          <div class="flex items-center justify-between px-5 py-3 border-b border-gray-50 shrink-0">
            <h2 class="font-bold text-gray-900">Recent Bills</h2>
            <RouterLink to="/invoices" class="text-sm text-primary-600 font-semibold hover:underline">View all</RouterLink>
          </div>

          <div v-if="loading" class="p-6 space-y-3">
            <div v-for="i in 4" :key="i" class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-xl bg-gray-100 animate-pulse shrink-0"></div>
              <div class="flex-1 space-y-1.5">
                <div class="h-3 bg-gray-100 rounded animate-pulse w-2/3"></div>
                <div class="h-2.5 bg-gray-100 rounded animate-pulse w-1/3"></div>
              </div>
              <div class="h-3 bg-gray-100 rounded animate-pulse w-16"></div>
            </div>
          </div>

          <div v-else-if="!recent.length" class="flex-1 flex flex-col items-center justify-center p-8 text-center">
            <div class="w-14 h-14 rounded-full bg-primary-50 flex items-center justify-center mb-3">
              <svg class="w-7 h-7 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <p class="text-gray-500 font-semibold">No bills yet</p>
            <RouterLink to="/invoices/new" class="btn-primary btn-sm mt-3">Create First Bill</RouterLink>
          </div>

          <div v-else class="divide-y divide-gray-50 overflow-y-auto flex-1">
            <div v-for="inv in recent" :key="inv.id"
              class="flex items-center justify-between px-5 py-3.5 hover:bg-blue-50/40 cursor-pointer transition-colors group"
              @click="router.push(`/invoices/${inv.id}`)">
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-primary-100 to-primary-200 flex items-center justify-center shrink-0">
                  <span class="text-primary-700 font-bold text-sm">{{ inv.client_name?.charAt(0)?.toUpperCase() }}</span>
                </div>
                <div class="min-w-0">
                  <p class="font-semibold text-gray-800 truncate text-sm group-hover:text-primary-700 transition-colors">{{ inv.client_name }}</p>
                  <p class="text-xs text-gray-400 mt-0.5">{{ inv.number }} · {{ fmtDateShort(inv.issue_date) }}</p>
                </div>
              </div>
              <div class="text-right ml-4 shrink-0">
                <p class="font-bold text-gray-900 text-sm">{{ inr(inv.amount_due) }}</p>
                <span :class="statusBadge(inv.status)" class="mt-0.5">{{ statusLabel(inv.status) }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Revenue Trend -->
        <div class="card card-body shrink-0">
          <div class="flex items-center justify-between mb-3">
            <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">Revenue – Last 6 Months</p>
            <RouterLink to="/reports" class="text-xs text-primary-600 font-semibold hover:underline">Reports →</RouterLink>
          </div>
          <div v-if="!trend.length" class="h-20 flex items-center justify-center text-xs text-gray-400">Loading…</div>
          <div v-else class="flex items-end gap-2 h-24">
            <div v-for="m in trend" :key="m.label" class="flex-1 flex flex-col items-center gap-1">
              <p class="text-[9px] text-gray-400 font-medium">{{ m.total > 0 ? inrCompact(m.total) : '' }}</p>
              <div class="w-full rounded-t-lg transition-all duration-500"
                :class="m.total > 0 ? 'bg-primary-500' : 'bg-gray-100'"
                :style="{ height: m.total > 0 ? Math.max(8, Math.round((m.total / trendMax) * 72)) + 'px' : '8px' }">
              </div>
              <p class="text-[10px] text-gray-500 font-semibold">{{ m.label }}</p>
            </div>
          </div>
        </div>

      </div>

      <!-- Right column -->
      <div class="lg:col-span-2 flex flex-col gap-3 lg:min-h-0">

        <!-- Overdue card -->
        <div v-if="overdue.length" class="card overflow-hidden border-red-100 flex flex-col lg:min-h-0 lg:flex-1">
          <div class="px-5 py-3 border-b border-red-50 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-2">
              <div class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></div>
              <h2 class="font-bold text-red-600">Late Payments</h2>
            </div>
            <span class="badge-red">{{ overdue.length }}</span>
          </div>
          <div class="divide-y divide-red-50 overflow-y-auto flex-1">
            <div v-for="inv in overdue" :key="inv.id"
              class="flex items-center gap-2 px-4 py-3 hover:bg-red-50/50 transition-colors">
              <div class="w-8 h-8 rounded-xl bg-red-100 flex items-center justify-center shrink-0">
                <span class="text-red-600 text-xs font-bold">{{ inv.client_name?.charAt(0)?.toUpperCase() }}</span>
              </div>
              <div class="flex-1 min-w-0 cursor-pointer" @click="router.push(`/invoices/${inv.id}`)">
                <p class="text-sm font-semibold text-gray-800 truncate">{{ inv.client_name }}</p>
                <p class="text-xs text-red-400">{{ inv.days_overdue }}d late</p>
              </div>
              <p class="text-sm font-bold text-red-600 shrink-0">{{ inr(inv.amount_due) }}</p>
              <button @click="remindWhatsApp(inv)" title="Send WhatsApp reminder"
                class="shrink-0 w-8 h-8 rounded-lg bg-green-50 hover:bg-green-100 flex items-center justify-center transition-colors">
                <svg class="w-4 h-4 text-green-600" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                  <path d="M12 0C5.373 0 0 5.373 0 12c0 2.137.565 4.147 1.554 5.887L0 24l6.305-1.524A11.94 11.94 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.818 9.818 0 01-5.006-1.375l-.359-.214-3.735.902.948-3.632-.234-.373A9.818 9.818 0 1112 21.818z"/>
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- All on time -->
        <div v-else class="card card-body text-center py-6 shrink-0">
          <div class="w-12 h-12 rounded-full bg-emerald-50 flex items-center justify-center mx-auto mb-2">
            <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          </div>
          <p class="font-semibold text-emerald-700">All payments on time!</p>
          <p class="text-xs text-gray-400 mt-1">No overdue bills right now</p>
        </div>

        <!-- Mini stat cards -->
        <div class="grid grid-cols-2 gap-3 shrink-0">
          <div class="card card-body text-center py-3">
            <p class="text-2xl font-bold text-amber-600">{{ stats.draft_count || 0 }}</p>
            <p class="text-xs text-gray-500 mt-1 font-medium">Bills Not Sent</p>
          </div>
          <div class="card card-body text-center py-3">
            <p class="text-2xl font-bold text-primary-600">{{ inrCompact(stats.total_paid_month || 0) }}</p>
            <p class="text-xs text-gray-500 mt-1 font-medium">This Month</p>
          </div>
        </div>

      </div>
    </div>

  </div>
</template>
