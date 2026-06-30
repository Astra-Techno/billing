<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { list, task } from '../../api'
import { inr } from '../../utils/currency'
import { fmtDateShort } from '../../utils/date'
import { statusBadge, statusLabel } from '../../utils/invoice'
import HelpIcon from '../../components/HelpIcon.vue'
import { useTour } from '../../composables/useTour'

const { startTour, isTourSeen } = useTour('invoice-list', [
  { target: '[data-tour="inv-tabs"]', title: 'Filter by Status', text: 'Quickly switch between All, Draft, Awaiting Payment, and Overdue invoices.' },
  { target: '[data-tour="inv-search"]', title: 'Search Invoices', text: 'Open the search panel to find invoices by name, number, or date range.' },
  { target: '[data-tour="inv-new"]', title: 'Create Invoice', text: 'Tap here to create a new invoice for a customer.' },
  { target: '[data-tour="inv-select"]', title: 'Bulk Actions', text: 'Toggle select mode to mark multiple invoices as paid or sent at once.' },
])

const route       = useRoute()
const router      = useRouter()
const invoices    = ref([])
const loading     = ref(true)
const showFilters = ref(false)

// Persistent left panel collapse
const panelCollapsed = ref(localStorage.getItem('inv_panel_collapsed') === '1')
function togglePanel() {
  panelCollapsed.value = !panelCollapsed.value
  localStorage.setItem('inv_panel_collapsed', panelCollapsed.value ? '1' : '0')
}

// ── Select mode ────────────────────────────────────────────────────────────────
const selectMode  = ref(false)
const selected    = ref(new Set())
const allSelected = computed(() =>
  invoices.value.length > 0 && selected.value.size === invoices.value.length
)

function toggleSelectMode() {
  selectMode.value = !selectMode.value
  selected.value   = new Set()
}

function toggleRow(id) {
  const s = new Set(selected.value)
  s.has(id) ? s.delete(id) : s.add(id)
  selected.value = s
}

function toggleAll() {
  selected.value = allSelected.value
    ? new Set()
    : new Set(invoices.value.map(i => i.id))
}

// ── Bulk mark paid ─────────────────────────────────────────────────────────────
const bulkPayModal = ref(false)
const bulkPaying   = ref(false)
const bulkPayForm  = ref({ payment_date: new Date().toISOString().split('T')[0], method: 'cash' })
const bulkError    = ref('')

async function doBulkMarkPaid() {
  bulkError.value  = ''
  bulkPaying.value = true
  try {
    const { data } = await task('Invoice', 'bulkMarkPaid', {
      ids:          [...selected.value],
      payment_date: bulkPayForm.value.payment_date,
      method:       bulkPayForm.value.method,
    })
    if (!data.success) { bulkError.value = data.message; bulkPaying.value = false; return }
    bulkPayModal.value = false
    selected.value     = new Set()
    selectMode.value   = false
    load()
  } catch (e) {
    bulkError.value  = e.response?.data?.message || 'Something went wrong.'
    bulkPaying.value = false
  }
}

// ── Bulk mark sent ─────────────────────────────────────────────────────────────
const bulkSending = ref(false)

async function doBulkMarkSent() {
  bulkSending.value = true
  try {
    await task('Invoice', 'bulkMarkSent', { ids: [...selected.value] })
    selected.value   = new Set()
    selectMode.value = false
    load()
  } catch {}
  bulkSending.value = false
}

// ── CSV export ─────────────────────────────────────────────────────────────────
function exportCsv() {
  const rows = (selectMode.value && selected.value.size > 0)
    ? invoices.value.filter(i => selected.value.has(i.id))
    : invoices.value

  const headers = ['Invoice #', 'Client', 'Company', 'Date', 'Due Date', 'Status', 'Total', 'Paid', 'Due']
  const escape  = v => `"${String(v ?? '').replace(/"/g, '""')}"`

  const lines = [
    headers.join(','),
    ...rows.map(i => [
      escape(i.number),
      escape(i.client_name),
      escape(i.client_company || ''),
      i.issue_date,
      i.due_date,
      i.status,
      parseFloat(i.total        || 0).toFixed(2),
      parseFloat(i.amount_paid  || 0).toFixed(2),
      parseFloat(i.amount_due   || 0).toFixed(2),
    ].join(','))
  ]

  const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' })
  const url  = URL.createObjectURL(blob)
  const a    = document.createElement('a')
  a.href     = url
  a.download = `invoices-${new Date().toISOString().split('T')[0]}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

// ── Date presets ───────────────────────────────────────────────────────────────
const filter       = ref({ status: '', search: '', from_date: '', to_date: '', preset: '', client_id: '', client_name: '' })
let timer          = null

const presets = [
  { label: 'Today',        value: 'today' },
  { label: 'This Week',    value: 'week' },
  { label: 'This Month',   value: 'month' },
  { label: 'Last Month',   value: 'last_month' },
  { label: 'This Quarter', value: 'quarter' },
  { label: 'This Year',    value: 'year' },
]

function fmt(d) { return d.toISOString().split('T')[0] }

function applyPreset(p) {
  const now = new Date()
  filter.value.preset = p
  showFilters.value   = false
  if (p === 'today') {
    filter.value.from_date = fmt(now); filter.value.to_date = fmt(now)
  } else if (p === 'week') {
    const s = new Date(now); s.setDate(now.getDate() - now.getDay() + 1)
    filter.value.from_date = fmt(s); filter.value.to_date = fmt(now)
  } else if (p === 'month') {
    filter.value.from_date = fmt(new Date(now.getFullYear(), now.getMonth(), 1))
    filter.value.to_date   = fmt(now)
  } else if (p === 'last_month') {
    filter.value.from_date = fmt(new Date(now.getFullYear(), now.getMonth() - 1, 1))
    filter.value.to_date   = fmt(new Date(now.getFullYear(), now.getMonth(), 0))
  } else if (p === 'quarter') {
    const q = Math.floor(now.getMonth() / 3)
    filter.value.from_date = fmt(new Date(now.getFullYear(), q * 3, 1))
    filter.value.to_date   = fmt(now)
  } else if (p === 'year') {
    filter.value.from_date = fmt(new Date(now.getFullYear(), 0, 1))
    filter.value.to_date   = fmt(now)
  }
  load()
}

function clearDate() {
  filter.value.preset    = ''
  filter.value.from_date = ''
  filter.value.to_date   = ''
  showFilters.value      = false
  load()
}

// ── Load invoices ──────────────────────────────────────────────────────────────
function clearClientFilter() {
  filter.value.client_id   = ''
  filter.value.client_name = ''
  load()
}

async function load() {
  loading.value  = true
  selected.value = new Set()
  try {
    const p = { sort_by: 'i.created_at', sort_order: 'desc', limit: 200 }
    if (filter.value.status)    p['filter.status']    = filter.value.status
    if (filter.value.search)    p['filter.search']    = `%${filter.value.search}%`
    if (filter.value.from_date) p['filter.from_date'] = filter.value.from_date
    if (filter.value.to_date)   p['filter.to_date']   = filter.value.to_date
    if (filter.value.client_id) p['filter.client_id'] = filter.value.client_id
    const { data } = await list('Invoice', p)
    invoices.value = data.data || []
  } catch {}
  loading.value = false
}

function onSearch() { clearTimeout(timer); timer = setTimeout(load, 350) }

function onRowClick(inv) {
  if (selectMode.value) { toggleRow(inv.id); return }
  router.push(`/invoices/${inv.id}`)
}

onMounted(() => {
  if (route.query.status)      filter.value.status      = route.query.status
  if (route.query.client_id)   filter.value.client_id   = route.query.client_id
  if (route.query.client_name) filter.value.client_name = route.query.client_name
  load().then(() => {
    setTimeout(() => { if (!isTourSeen()) startTour() }, 800)
  })
})

watch(() => route.query.status, val => {
  filter.value.status = val || ''
  load()
})

const tabs = [
  { label: 'All',              value: '' },
  { label: 'Draft',            value: 'draft' },
  { label: 'Awaiting Payment', value: 'sent' },
  { label: 'Overdue',          value: 'overdue' },
  { label: 'Paid',             value: 'paid' },
]

const METHODS = ['cash','upi','neft','rtgs','imps','cheque','card','netbanking','other']

const avatarColors = ['bg-blue-100 text-blue-700','bg-emerald-100 text-emerald-700','bg-purple-100 text-purple-700','bg-amber-100 text-amber-700','bg-pink-100 text-pink-700','bg-teal-100 text-teal-700']
const avatarColor  = name => avatarColors[(name?.charCodeAt(0) || 0) % avatarColors.length]

const activeDateLabel = () => {
  if (filter.value.preset) return presets.find(p => p.value === filter.value.preset)?.label
  if (filter.value.from_date && filter.value.to_date) return `${filter.value.from_date} → ${filter.value.to_date}`
  if (filter.value.from_date) return `From ${filter.value.from_date}`
  return null
}
</script>

<template>
  <div class="flex flex-col lg:flex-row h-full min-h-0 w-full overflow-hidden">

    <!-- Left Pane: List -->
    <div id="c3-left-panel" :class="[
      'split-pane-left transition-all duration-300 relative z-30 h-full',
      $route.name !== 'Invoices' ? 'hidden lg:flex' : '',
      panelCollapsed && $route.name !== 'Invoices' ? 'lg:!w-0 lg:!min-w-0 lg:overflow-hidden lg:!p-0 lg:!border-0' : ''
    ]">
      
      <!-- Top Sticky Header Area -->
      <div class="px-5 py-4 border-b border-gray-200/60 bg-white/60 backdrop-blur-md sticky top-0 z-10">
        
        <!-- Header & Actions -->
        <div class="flex justify-between items-center mb-4">
            <h2 class="font-bold text-gray-900 text-sm tracking-tight flex items-center gap-2">Invoices <HelpIcon section="bills" class="w-3.5 h-3.5" />
              <button @click="startTour()" class="text-[10px] font-bold text-primary-500 hover:text-primary-700 ml-1" title="Take a tour">Tour</button>
            </h2>
            <div class="flex gap-2">
                <!-- Select Mode Toggle -->
                <button @click="toggleSelectMode" data-tour="inv-select" class="w-7 h-7 bg-white border border-gray-200/80 shadow-sm hover:shadow hover:border-gray-300 rounded-lg flex items-center justify-center transition-all" :class="selectMode ? 'text-primary-600 border-primary-200 bg-primary-50' : 'text-gray-600'">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                </button>
                <!-- Search Toggle -->
                <button @click="showFilters = !showFilters" data-tour="inv-search" class="w-7 h-7 bg-white border border-gray-200/80 shadow-sm hover:shadow hover:border-gray-300 rounded-lg flex items-center justify-center transition-all" :class="showFilters ? 'text-primary-600 border-primary-200 bg-primary-50' : 'text-gray-600'">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </button>
                <!-- New Invoice -->
                <button @click="router.push('/invoices/new')" data-tour="inv-new" class="w-7 h-7 bg-white border border-gray-200/80 shadow-sm hover:shadow hover:border-gray-300 rounded-lg flex items-center justify-center text-gray-600 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                </button>
            </div>
        </div>

        <!-- Search / Filter Expansion -->
        <div v-show="showFilters" class="mb-4 space-y-2 animate-fade-in-up">
            <input v-model="filter.search" @input="onSearch" type="text"
              class="w-full bg-white border border-gray-200 shadow-sm text-gray-900 text-xs font-semibold rounded-lg focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 block px-3 py-2 transition-all"
              placeholder="Search invoices..." />
            
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

        <!-- Tabs -->
        <div data-tour="inv-tabs" class="flex gap-1 bg-gray-100/80 p-1 rounded-[10px] ring-1 ring-inset ring-gray-200/50 overflow-x-auto hide-scrollbar">
            <button v-for="t in tabs.slice(0, 4)" :key="t.value"
              @click="filter.status = t.value; load()"
              class="flex-1 text-[11px] font-semibold rounded-md py-1.5 transition-all whitespace-nowrap px-2"
              :class="filter.status === t.value ? 'bg-white shadow-sm text-gray-900 font-bold' : 'text-gray-500 hover:text-gray-700'">
              {{ t.label }}
            </button>
        </div>
        
        <!-- Select Mode Banner -->
        <div v-if="selectMode" class="mt-3 flex items-center gap-2 bg-primary-50/50 border border-primary-100 rounded-lg px-3 py-2 animate-fade-in-up">
          <input type="checkbox" :checked="allSelected" @change="toggleAll" class="w-3.5 h-3.5 rounded accent-primary-600 cursor-pointer shrink-0" />
          <span class="text-[11px] font-bold text-primary-700 flex-1">
            {{ selected.size > 0 ? `${selected.size} selected` : 'Select rows' }}
          </span>
          <button v-if="selected.size > 0" @click="exportCsv" class="text-[10px] font-bold text-gray-600 bg-white border border-gray-200 px-2 py-1 rounded hover:bg-gray-50 transition-colors shrink-0 shadow-sm">
            CSV
          </button>
        </div>

      </div>

      <!-- Scrollable List -->
      <div class="flex-1 overflow-y-auto px-3 py-3 space-y-1.5 custom-scrollbar min-h-0">
          
          <div v-if="loading" class="space-y-0">
            <div v-for="i in 5" :key="i" class="flex items-center gap-3 px-4 py-4 border-b border-google-divider/40">
              <div class="skeleton w-10 h-10 rounded-xl"></div>
              <div class="flex-1 space-y-2">
                <div class="skeleton h-3.5 w-32 rounded"></div>
                <div class="skeleton h-3 w-48 rounded"></div>
              </div>
              <div class="text-right space-y-2">
                <div class="skeleton h-3.5 w-20 rounded ml-auto"></div>
                <div class="skeleton h-5 w-14 rounded-full ml-auto"></div>
              </div>
            </div>
          </div>

          <div v-else-if="!invoices.length" class="flex flex-col items-center justify-center py-16 px-6 text-center">
            <div class="w-20 h-20 rounded-3xl bg-primary-50 flex items-center justify-center mb-5">
              <svg class="w-10 h-10 text-primary-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
              </svg>
            </div>
            <h3 class="text-lg font-bold text-ink mb-1">No invoices yet</h3>
            <p class="text-sm text-google-muted mb-5 max-w-xs">Create your first invoice to start tracking payments and growing your business.</p>
            <RouterLink to="/invoices/new" class="btn-primary btn-sm">+ New Invoice</RouterLink>
          </div>

          <div v-else v-for="(inv, idx) in invoices" :key="inv.id"
            class="p-4 rounded-xl border cursor-pointer transition-all group relative overflow-hidden list-item-1"
            :style="{ animationDelay: (idx * 0.05) + 's' }"
            :class="[
              $route.params.id == inv.id && !selectMode ? 'bg-white border-gray-200 shadow-[0_2px_8px_rgba(0,0,0,0.03)]' : 'border-transparent hover:border-gray-200/60 hover:bg-white hover:shadow-[0_2px_8px_rgba(0,0,0,0.02)]',
              selectMode && selected.has(inv.id) ? 'bg-primary-50/40 border-primary-100' : ''
            ]"
            @click="onRowClick(inv)">
            
            <!-- Active Indicator Line -->
            <div v-if="$route.params.id == inv.id && !selectMode" class="absolute left-0 top-0 bottom-0 w-[3px] bg-gray-900 rounded-l-xl"></div>
            
            <div class="flex gap-3">
                <div v-if="selectMode" class="flex items-center justify-center shrink-0">
                  <input type="checkbox" :checked="selected.has(inv.id)" @click.stop="toggleRow(inv.id)" class="w-4 h-4 rounded accent-primary-600 cursor-pointer" />
                </div>
                
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between mb-1">
                        <span class="text-[14px] font-bold truncate pr-2 tracking-tight transition-colors"
                              :class="$route.params.id == inv.id ? 'text-primary-600' : 'text-gray-900 group-hover:text-primary-600'">
                              {{ inv.client_name }}
                        </span>
                        <span class="text-[14px] font-bold tabular-nums"
                              :class="inv.status === 'overdue' ? 'text-red-600' : 'text-gray-900'">
                              {{ inr(inv.total) }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center mt-1.5">
                        <span class="text-[12px] font-semibold text-gray-400">{{ inv.number }} • {{ fmtDateShort(inv.issue_date) }}</span>
                        
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-[5px] tracking-wider flex items-center border"
                              :class="{
                                  'text-emerald-600 bg-emerald-50 border-emerald-100': inv.status === 'paid',
                                  'text-gray-600 bg-gray-100 border-gray-200/60': inv.status === 'draft',
                                  'text-blue-600 bg-blue-50 border-blue-100': inv.status === 'sent',
                                  'text-red-600 bg-red-50 border-red-100': inv.status === 'overdue',
                                  'text-purple-600 bg-purple-50 border-purple-100': inv.status === 'partial',
                              }">
                            {{ inv.status.toUpperCase() }}
                        </span>
                    </div>
                </div>
            </div>
          </div>
      </div>

      <!-- Bulk Action Bar Overlay -->
      <Transition
        enter-active-class="transition-all duration-200 ease-out"
        enter-from-class="translate-y-4 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition-all duration-150 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-4 opacity-0">
        <div v-if="selectMode && selected.size > 0" class="absolute bottom-4 left-3 right-3 z-40">
          <div class="bg-white/90 backdrop-blur-xl border border-gray-200/80 shadow-lg rounded-xl p-2 flex flex-col gap-2">
            <button @click="doBulkMarkSent" :disabled="bulkSending" class="w-full flex justify-center items-center gap-1.5 py-1.5 text-[12px] font-bold rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors disabled:opacity-60">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg> Mark Sent
            </button>
            <button @click="bulkPayModal = true; bulkError = ''" class="w-full flex justify-center items-center gap-1.5 py-1.5 text-[12px] font-bold rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition-colors">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg> Mark Paid
            </button>
          </div>
        </div>
      </Transition>
    </div>

    <!-- Right Pane: Detail/Form wrapper -->
    <div v-if="$route.name !== 'Invoices'" id="c3-right-view" class="split-pane-right relative z-20">
      <!-- Panel toggle button -->
      <button @click="togglePanel" title="Toggle invoice list"
        class="hidden lg:flex absolute top-3 left-3 z-30 w-7 h-7 items-center justify-center rounded-lg bg-white border border-gray-200 shadow-sm hover:bg-gray-50 text-gray-500 hover:text-gray-700 transition-all">
        <svg class="w-4 h-4 transition-transform" :class="panelCollapsed ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
        </svg>
      </button>
      <router-view v-slot="{ Component }">
        <component :is="Component" :key="$route.fullPath" @refresh="load" />
      </router-view>
    </div>
  </div>

  <!-- Bulk Mark Paid Modal (Kept mostly as is but stylized slightly) -->
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0">
      <div v-if="bulkPayModal"
        class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-gray-900/40 backdrop-blur-sm"
        @click.self="bulkPayModal = false">
        <div class="bg-white rounded-2xl shadow-2xl p-6 w-full max-w-sm animate-fade-in-up border border-gray-100">
          <h3 class="font-bold text-gray-900 text-[16px] tracking-tight mb-1">
            Mark {{ selected.size }} Invoice{{ selected.size !== 1 ? 's' : '' }} as Paid
          </h3>
          <p class="text-[13px] text-gray-500 mb-5">Records full payment for each selected invoice</p>

          <div class="space-y-4">
            <div>
              <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Payment Date</label>
              <input v-model="bulkPayForm.payment_date" type="date" class="w-full bg-white border border-gray-200 text-gray-900 text-[14px] font-semibold rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 block p-3 transition-all" />
            </div>
            <div>
              <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Payment Method</label>
              <select v-model="bulkPayForm.method" class="w-full bg-white border border-gray-200 text-gray-900 text-[14px] font-semibold rounded-xl focus:ring-4 focus:ring-primary-500/10 focus:border-primary-500 block p-3 transition-all cursor-pointer">
                <option v-for="m in METHODS" :key="m" :value="m" class="capitalize">{{ m.toUpperCase() }}</option>
              </select>
            </div>
            <div v-if="bulkError" class="text-sm text-red-600 bg-red-50 rounded-lg px-3 py-2 border border-red-100">{{ bulkError }}</div>
          </div>

          <div class="flex gap-2.5 mt-6">
            <button @click="bulkPayModal = false"
              class="flex-1 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-xl text-[13px] font-bold text-gray-700 transition-colors">
              Cancel
            </button>
            <button @click="doBulkMarkPaid" :disabled="bulkPaying"
              class="flex-1 px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-white shadow-md shadow-primary-600/20 border border-primary-600 rounded-xl text-[13px] font-bold transition-all disabled:opacity-60 flex justify-center items-center">
              {{ bulkPaying ? 'Saving…' : 'Confirm Paid' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
