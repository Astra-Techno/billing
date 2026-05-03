<script setup>
import { ref, computed, onMounted } from 'vue'
import { list } from '../../api'
import { inr, inrCompact } from '../../utils/currency'
import { fmtDateShort } from '../../utils/date'
import { useRouter } from 'vue-router'

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
  return Array.from(map.values()).slice(0, 8)
})

// Show total for paid, amount_due for all others
const invDisplayAmount = inv => inv.status === 'paid' ? inv.total : inv.amount_due

function remindWhatsApp(inv) {
  const mobile = inv.client_mobile?.replace(/\D/g, '')
  if (!mobile) { router.push(`/invoices/${inv.id}`); return }
  const msg = `Hi ${inv.client_name}, this is a friendly reminder that invoice ${inv.number} for ${inr(inv.amount_due)} is overdue. Kindly arrange payment at the earliest. Thank you!`
  window.open(`https://wa.me/${mobile}?text=${encodeURIComponent(msg)}`, '_blank')
}
</script>

<template>
  <div class="flex flex-col lg:flex-row gap-4 lg:gap-6 w-full lg:h-full lg:min-h-0">

    <!-- ════════════════════════════════════════
         LEFT PANE: Hero + Recent Activity
    ════════════════════════════════════════ -->
    <div class="flex-1 flex flex-col gap-4 w-full lg:w-1/2 shrink-0 lg:h-full lg:min-h-0">

      <!-- HERO -->
      <div class="relative shrink-0 overflow-hidden rounded-[2rem] bg-gradient-to-br from-gray-900 via-gray-800 to-black p-6 sm:p-8 shadow-2xl animate-fade-in-up">
        <div class="absolute -top-24 -right-24 w-40 h-40 bg-primary-500/30 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -left-24 w-40 h-40 bg-emerald-500/20 rounded-full blur-3xl pointer-events-none"></div>

        <!-- Balance -->
        <div class="relative z-10 text-center mb-5">
          <p class="text-gray-400 font-medium tracking-wide mb-1 text-xs">Total Outstanding</p>
          <h1 class="text-4xl lg:text-5xl font-extrabold tracking-tight text-white">
            {{ inrCompact(stats.total_due || 0) }}
          </h1>
        </div>

        <!-- Mini stat strip -->
        <div class="relative z-10 flex justify-center gap-3 mb-5">
          <div class="flex items-center gap-1.5 bg-white/10 backdrop-blur-md rounded-xl px-3 py-2 border border-white/10">
            <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            <span class="text-xs text-gray-300 font-medium">{{ inrCompact(stats.total_paid_month || 0) }}</span>
            <span class="text-[10px] text-gray-500">collected</span>
          </div>
          <div v-if="stats.draft_count > 0"
            class="flex items-center gap-1.5 bg-white/10 backdrop-blur-md rounded-xl px-3 py-2 border border-white/10 cursor-pointer hover:bg-white/20 transition-all"
            @click="router.push('/invoices?status=draft')">
            <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            <span class="text-xs text-white font-bold">{{ stats.draft_count }}</span>
            <span class="text-[10px] text-gray-500">draft{{ stats.draft_count !== 1 ? 's' : '' }}</span>
          </div>
        </div>

        <!-- Single primary action -->
        <div class="relative z-10 flex justify-center">
          <RouterLink to="/invoices/new"
            class="flex items-center justify-center gap-2 px-8 py-3 bg-primary-600 text-white hover:bg-primary-500 font-bold rounded-xl shadow-lg shadow-primary-600/30 border border-primary-500/50 transition-all active:scale-95 text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            New Bill
          </RouterLink>
        </div>
      </div>

      <!-- RECENT ACTIVITY -->
      <div class="flex flex-col flex-1 min-h-0 pb-4 lg:pb-0 animate-fade-in-up anim-delay-200">
        <div class="flex items-center justify-between mb-3 px-1 shrink-0">
          <h2 class="font-bold text-gray-800 text-sm">Recent Activity</h2>
          <RouterLink to="/invoices" class="text-xs text-primary-600 font-bold bg-primary-50 hover:bg-primary-100 px-3 py-1.5 rounded-full transition-colors">Show all</RouterLink>
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
                <p class="font-extrabold text-sm" :class="inv.status === 'paid' ? 'text-gray-400' : 'text-gray-900'">
                  {{ inr(invDisplayAmount(inv)) }}
                </p>
                <p class="text-[9px] font-extrabold mt-1 tracking-wider uppercase px-2 py-0.5 rounded-full"
                   :class="{
                     'bg-emerald-50 text-emerald-600': inv.status === 'paid',
                     'bg-amber-50 text-amber-600':    inv.status === 'draft',
                     'bg-blue-50 text-blue-600':      inv.status === 'sent',
                     'bg-red-50 text-red-600':        inv.status === 'overdue',
                     'bg-purple-50 text-purple-600':  inv.status === 'partial',
                   }">{{ inv.status }}</p>
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

    <!-- ════════════════════════════════════════
         RIGHT PANE: Overdue, Snapshot, People
    ════════════════════════════════════════ -->
    <div class="flex flex-col gap-4 w-full lg:w-1/2 shrink-0 lg:h-full lg:min-h-0 lg:overflow-y-auto hide-scrollbar lg:pb-6">

      <!-- OVERDUE — individual rows with WhatsApp remind -->
      <div v-if="overdue.length > 0" class="shrink-0 bg-white rounded-[1.5rem] shadow-soft border border-red-50 overflow-hidden animate-fade-in-up">
        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-3.5 bg-gradient-to-r from-red-50 to-white border-b border-red-100">
          <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-full bg-red-100 text-red-600 flex items-center justify-center shrink-0">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            </div>
            <div>
              <p class="text-sm font-bold text-gray-900">{{ overdue.length }} overdue</p>
              <p class="text-xs text-red-600 font-semibold">{{ inr(overdue.reduce((s,i) => s + parseFloat(i.amount_due||0), 0)) }} pending</p>
            </div>
          </div>
          <RouterLink to="/invoices?status=overdue" class="text-xs text-gray-400 hover:text-gray-600 font-medium">View all →</RouterLink>
        </div>
        <!-- Per-customer rows -->
        <div class="divide-y divide-gray-50">
          <div v-for="inv in overdue" :key="inv.id"
            class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 transition-colors">
            <div class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 text-sm font-bold" :class="avatarColor(inv.client_name)">
              {{ inv.client_name?.charAt(0)?.toUpperCase() }}
            </div>
            <div class="flex-1 min-w-0 cursor-pointer" @click="router.push(`/invoices/${inv.id}`)">
              <p class="font-bold text-gray-900 text-sm truncate">{{ inv.client_name }}</p>
              <p class="text-xs text-gray-500">{{ inv.number }} · {{ fmtDateShort(inv.due_date) }}</p>
            </div>
            <p class="font-extrabold text-red-600 text-sm shrink-0">{{ inr(inv.amount_due) }}</p>
            <button
              @click="remindWhatsApp(inv)"
              class="shrink-0 flex items-center gap-1.5 bg-green-50 text-green-700 hover:bg-green-100 active:scale-95 transition-all px-2.5 py-1.5 rounded-xl text-xs font-bold border border-green-100">
              <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
              Remind
            </button>
          </div>
        </div>
      </div>

      <!-- BUSINESS SNAPSHOT — stat cards using already-loaded data -->
      <div class="shrink-0 animate-fade-in-up anim-delay-75">
        <p class="text-sm font-bold text-gray-800 mb-3 px-1">This Month</p>
        <div class="grid grid-cols-2 gap-3">

          <!-- Collected this month -->
          <RouterLink to="/invoices?status=paid"
            class="flex flex-col gap-1 p-4 bg-white rounded-[1.25rem] shadow-soft border border-gray-100 hover:border-emerald-200 hover:shadow-md active:scale-95 transition-all group">
            <div class="w-9 h-9 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mb-1">
              <svg class="w-4.5 h-4.5 w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-lg font-extrabold text-gray-900 leading-tight">{{ inrCompact(stats.total_paid_month || 0) }}</p>
            <p class="text-xs text-gray-500 font-medium">Collected</p>
          </RouterLink>

          <!-- Drafts pending -->
          <RouterLink to="/invoices?status=draft"
            class="flex flex-col gap-1 p-4 bg-white rounded-[1.25rem] shadow-soft border border-gray-100 hover:border-amber-200 hover:shadow-md active:scale-95 transition-all group">
            <div class="w-9 h-9 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center mb-1">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </div>
            <p class="text-lg font-extrabold text-gray-900 leading-tight">{{ stats.draft_count || 0 }}</p>
            <p class="text-xs text-gray-500 font-medium">Draft bill{{ stats.draft_count !== 1 ? 's' : '' }}</p>
          </RouterLink>

          <!-- Quotes -->
          <RouterLink to="/quotes"
            class="flex flex-col gap-1 p-4 bg-white rounded-[1.25rem] shadow-soft border border-gray-100 hover:border-indigo-200 hover:shadow-md active:scale-95 transition-all group">
            <div class="w-9 h-9 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center mb-1">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"/></svg>
            </div>
            <p class="text-lg font-extrabold text-gray-900 leading-tight">Quotes</p>
            <p class="text-xs text-gray-500 font-medium">Estimates &amp; proposals</p>
          </RouterLink>

          <!-- Reports -->
          <RouterLink to="/reports"
            class="flex flex-col gap-1 p-4 bg-white rounded-[1.25rem] shadow-soft border border-gray-100 hover:border-sky-200 hover:shadow-md active:scale-95 transition-all group">
            <div class="w-9 h-9 rounded-full bg-sky-50 text-sky-600 flex items-center justify-center mb-1">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </div>
            <p class="text-lg font-extrabold text-gray-900 leading-tight">Reports</p>
            <p class="text-xs text-gray-500 font-medium">P&amp;L, sales &amp; dues</p>
          </RouterLink>

        </div>
      </div>

      <!-- PEOPLE — no redundant Add button, shows outstanding -->
      <div class="shrink-0 animate-fade-in-up anim-delay-150">
        <div class="flex items-center justify-between mb-3 px-1">
          <p class="text-sm font-bold text-gray-800">People</p>
          <RouterLink to="/clients" class="text-xs text-gray-400 hover:text-gray-600 font-medium">All customers →</RouterLink>
        </div>
        <div class="flex gap-3 overflow-x-auto pb-2 hide-scrollbar">
          <div v-for="person in recentPeople" :key="person.client_id"
               class="shrink-0 flex flex-col items-center gap-1.5 cursor-pointer group w-[4.5rem]"
               @click="router.push('/clients/' + person.client_id)">
            <div class="w-[3.5rem] h-[3.5rem] rounded-2xl flex items-center justify-center text-xl font-bold shadow-sm border border-gray-100 group-active:scale-95 transition-transform"
                 :class="avatarColor(person.client_name)">
              {{ person.client_name?.charAt(0)?.toUpperCase() }}
            </div>
            <p class="text-xs font-semibold text-gray-700 truncate w-full text-center">{{ person.client_name?.split(' ')[0] }}</p>
            <p v-if="parseFloat(person.amount_due) > 0" class="text-[9px] font-bold text-red-500 leading-none">
              {{ inrCompact(person.amount_due) }}
            </p>
          </div>
        </div>
      </div>

    </div>
  </div>
</template>
