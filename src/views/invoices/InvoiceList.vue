<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { list, task } from '../../api'
import { inr } from '../../utils/currency'
import { fmtDateShort } from '../../utils/date'
import { statusBadge, statusLabel } from '../../utils/invoice'
import HelpIcon from '../../components/HelpIcon.vue'

const route       = useRoute()
const router      = useRouter()
const invoices    = ref([])
const loading     = ref(true)
const showFilters = ref(false)

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
const filter = ref({ status: '', search: '', from_date: '', to_date: '', preset: '' })
let timer    = null

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
async function load() {
  loading.value  = true
  selected.value = new Set()
  try {
    const p = { sort_by: 'i.created_at', sort_order: 'desc' }
    if (filter.value.status)    p['filter.status']    = filter.value.status
    if (filter.value.search)    p['filter.search']    = `%${filter.value.search}%`
    if (filter.value.from_date) p['filter.from_date'] = filter.value.from_date
    if (filter.value.to_date)   p['filter.to_date']   = filter.value.to_date
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
  if (route.query.status) filter.value.status = route.query.status
  load()
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
  <div class="flex flex-col lg:flex-row gap-6 h-full min-h-0">

    <!-- Left Pane: List -->
    <div :class="{ 'hidden lg:flex': $route.name !== 'Invoices', 'w-full lg:w-[35%] flex flex-col min-h-0 relative': true }">
      <div class="flex flex-col gap-2 pr-1 shrink-0 z-10 relative">

        <!-- Header -->
        <div class="flex items-center justify-between gap-3">
          <h1 class="page-title flex items-center gap-2">Bills <HelpIcon section="bills" /></h1>
          <div class="flex items-center gap-2">

            <!-- Export CSV -->
            <button @click="exportCsv" title="Export CSV"
              class="p-2 text-gray-500 hover:text-emerald-600 hover:bg-emerald-50 rounded-full transition-colors shadow-sm bg-white border border-gray-100">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
              </svg>
            </button>

            <!-- Select Toggle -->
            <button @click="toggleSelectMode" title="Select invoices"
              class="p-2 rounded-full transition-colors shadow-sm border"
              :class="selectMode
                ? 'bg-primary-600 text-white border-primary-600'
                : 'bg-white text-gray-500 border-gray-100 hover:text-primary-600 hover:bg-primary-50'">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
              </svg>
            </button>

            <!-- Filter -->
            <button @click="showFilters = !showFilters"
              class="p-2 text-gray-500 hover:text-primary-600 hover:bg-primary-50 rounded-full transition-colors shadow-sm bg-white border border-gray-100"
              :class="{'bg-primary-50 text-primary-600 border-primary-100': showFilters || filter.preset || filter.from_date}">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
            </button>

            <!-- Mobile New Bill -->
            <button @click="router.push('/invoices/new')" class="lg:hidden p-2 text-white bg-primary-600 hover:bg-primary-700 rounded-full transition-colors shadow-soft-blue">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            </button>
          </div>
        </div>

        <!-- Select mode banner -->
        <div v-if="selectMode" class="flex items-center gap-3 bg-primary-50 border border-primary-100 rounded-xl px-4 py-2.5 animate-fade-in-up">
          <input type="checkbox" :checked="allSelected" @change="toggleAll"
            class="w-4 h-4 rounded accent-primary-600 cursor-pointer shrink-0" />
          <span class="text-sm font-bold text-primary-700 flex-1">
            {{ selected.size > 0 ? `${selected.size} of ${invoices.length} selected` : 'Tap rows to select' }}
          </span>
          <button v-if="selected.size > 0" @click="exportCsv"
            class="text-xs font-bold text-emerald-700 bg-emerald-50 px-2.5 py-1.5 rounded-lg hover:bg-emerald-100 transition-colors shrink-0">
            Export
          </button>
        </div>

        <!-- Search -->
        <div class="relative animate-fade-in-up">
          <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input v-model="filter.search" @input="onSearch" type="text"
            class="w-full bg-white border-0 shadow-soft text-gray-900 text-sm rounded-xl focus:ring-2 focus:ring-primary-500 block pl-10 p-2.5 transition-shadow"
            placeholder="Search bills..." />
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

        <!-- Date Filters -->
        <div v-show="showFilters" class="flex flex-col gap-3 animate-fade-in-up">
          <div class="flex items-center gap-2 flex-wrap">
            <button v-for="p in presets" :key="p.value"
              @click="applyPreset(p.value)"
              class="px-2.5 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap shrink-0 transition-all"
              :class="filter.preset === p.value
                ? 'bg-indigo-100 text-indigo-700 border border-indigo-200'
                : 'bg-white text-gray-500 border border-gray-200 hover:border-gray-300 shadow-soft'">
              {{ p.label }}
            </button>
            <button v-if="activeDateLabel()" @click="clearDate"
              class="px-2.5 py-1.5 rounded-lg text-xs font-bold bg-red-50 text-red-500 border border-red-100 hover:bg-red-100 shrink-0 flex items-center gap-1">
              ✕ Clear
            </button>
          </div>
          <div class="flex gap-2 items-center bg-white p-2.5 rounded-xl shadow-soft border border-gray-100">
            <input v-model="filter.from_date" type="date" class="form-input text-xs flex-1 border-0 bg-gray-50 rounded-lg"
              @change="filter.preset = ''; load()" />
            <span class="text-gray-400 text-xs font-medium">to</span>
            <input v-model="filter.to_date" type="date" class="form-input text-xs flex-1 border-0 bg-gray-50 rounded-lg"
              @change="filter.preset = ''; load()" />
          </div>
        </div>
      </div>

      <!-- Scrollable List -->
      <div class="flex-1 overflow-y-auto pr-1 pb-32 mt-2 no-scrollbar min-h-0">
        <div class="bg-white rounded-[2rem] shadow-soft border-0 overflow-hidden animate-fade-in-up anim-delay-75">

          <!-- Skeleton -->
          <div v-if="loading" class="divide-y divide-gray-50">
            <div v-for="i in 6" :key="i" class="flex items-center gap-4 px-5 py-4 animate-pulse">
              <div class="w-12 h-12 rounded-full bg-gray-100 shrink-0"></div>
              <div class="flex-1 space-y-2">
                <div class="h-3 bg-gray-100 rounded w-2/3"></div>
                <div class="h-2.5 bg-gray-100 rounded w-1/3"></div>
              </div>
              <div class="h-3 bg-gray-100 rounded w-16"></div>
            </div>
          </div>

          <!-- Empty -->
          <div v-else-if="!invoices.length" class="p-12 text-center">
            <div class="w-16 h-16 rounded-full bg-primary-50 flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <p class="font-extrabold text-gray-900 text-lg">No bills found</p>
            <p class="text-sm text-gray-500 mt-1">Create your first bill to get started</p>
            <button @click="router.push('/invoices/new')" class="btn bg-primary-600 text-white hover:bg-primary-700 shadow-soft-blue rounded-full px-6 py-2.5 mt-5 inline-flex items-center gap-2 font-bold">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
              Create First Bill
            </button>
          </div>

          <!-- Rows -->
          <div v-else class="divide-y divide-gray-50">
            <div v-for="inv in invoices" :key="inv.id"
              class="flex items-center gap-4 px-5 py-4 hover:bg-gray-50 active:bg-gray-100 cursor-pointer transition-colors group"
              :class="{ 'bg-primary-50/60 hover:bg-primary-50': selectMode && selected.has(inv.id) }"
              @click="onRowClick(inv)">

              <!-- Checkbox or Avatar -->
              <div v-if="selectMode" class="w-12 h-12 flex items-center justify-center shrink-0">
                <input type="checkbox" :checked="selected.has(inv.id)" @click.stop="toggleRow(inv.id)"
                  class="w-5 h-5 rounded accent-primary-600 cursor-pointer" />
              </div>
              <div v-else class="w-12 h-12 rounded-full flex items-center justify-center shrink-0 font-extrabold text-lg" :class="avatarColor(inv.client_name)">
                {{ inv.client_name?.charAt(0)?.toUpperCase() }}
              </div>

              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <p class="font-bold text-gray-900 text-base truncate group-hover:text-primary-700 transition-colors">{{ inv.client_name }}</p>
                  <span :class="statusBadge(inv.status)" class="text-[10px]">{{ statusLabel(inv.status) }}</span>
                </div>
                <p class="text-xs text-gray-500 mt-0.5 truncate">{{ inv.number }} · {{ fmtDateShort(inv.issue_date) }}</p>
              </div>

              <div class="text-right shrink-0 flex flex-col items-end">
                <p class="font-extrabold text-gray-900 text-base">{{ inr(inv.total) }}</p>
                <p v-if="inv.amount_due > 0 && inv.status !== 'paid'" class="text-[10px] uppercase font-bold tracking-wider mt-1 text-danger-600 bg-danger-50 px-2 py-0.5 rounded-full">{{ inr(inv.amount_due) }} Due</p>
                <p v-else-if="inv.status === 'paid'" class="text-[10px] uppercase font-bold tracking-wider mt-1 text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-full">Paid</p>
              </div>

              <svg v-if="!selectMode" class="w-5 h-5 text-gray-300 shrink-0 group-hover:text-primary-500 group-hover:translate-x-1 transition-all ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </div>
          </div>

          <!-- Footer -->
          <div v-if="!loading && invoices.length" class="bg-gray-50/80 border-t border-gray-100 px-6 py-4 flex items-center justify-between">
            <span class="text-xs text-gray-500 font-medium">Showing <span class="font-bold text-gray-800">{{ invoices.length }}</span> bill{{ invoices.length !== 1 ? 's' : '' }}</span>
            <span v-if="invoices.some(i => i.amount_due > 0)" class="text-xs font-bold text-danger-600">
              {{ inr(invoices.reduce((s, i) => s + parseFloat(i.amount_due || 0), 0)) }} due
            </span>
          </div>
        </div>
      </div>

      <!-- Bulk Action Bar -->
      <Transition
        enter-active-class="transition-all duration-200 ease-out"
        enter-from-class="translate-y-4 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition-all duration-150 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-4 opacity-0">
        <div v-if="selectMode && selected.size > 0" class="absolute bottom-0 left-0 right-1 z-20">
          <div class="bg-white border border-gray-100 rounded-[1.5rem] shadow-2xl p-3 flex items-center gap-2 flex-wrap">
            <span class="text-xs font-extrabold text-gray-700 pl-1 shrink-0">{{ selected.size }} selected</span>
            <div class="flex-1"></div>
            <button @click="doBulkMarkSent" :disabled="bulkSending"
              class="flex items-center gap-1.5 px-3 py-2 text-xs font-bold rounded-xl bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors disabled:opacity-60 shrink-0">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
              {{ bulkSending ? 'Updating…' : 'Mark Sent' }}
            </button>
            <button @click="bulkPayModal = true; bulkError = ''"
              class="flex items-center gap-1.5 px-3 py-2 text-xs font-bold rounded-xl bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition-colors shrink-0">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              Mark Paid
            </button>
            <button @click="exportCsv"
              class="flex items-center gap-1.5 px-3 py-2 text-xs font-bold rounded-xl bg-gray-50 text-gray-600 hover:bg-gray-100 transition-colors shrink-0">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
              CSV
            </button>
          </div>
        </div>
      </Transition>
    </div>

    <!-- Right Pane: Detail/Form -->
    <div v-if="$route.name !== 'Invoices'" class="w-full lg:w-[65%] flex-1 overflow-y-auto no-scrollbar pb-10">
      <router-view v-slot="{ Component }">
        <component :is="Component" :key="$route.fullPath" @refresh="load" />
      </router-view>
    </div>
  </div>

  <!-- Bulk Mark Paid Modal -->
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0">
      <div v-if="bulkPayModal"
        class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
        @click.self="bulkPayModal = false">
        <div class="bg-white rounded-[2rem] shadow-2xl p-6 w-full max-w-sm animate-fade-in-up">
          <h3 class="font-extrabold text-gray-900 text-lg mb-1">
            Mark {{ selected.size }} Invoice{{ selected.size !== 1 ? 's' : '' }} as Paid
          </h3>
          <p class="text-sm text-gray-500 mb-5">Records full payment for each selected invoice</p>

          <div class="space-y-4">
            <div>
              <label class="form-label">Payment Date</label>
              <input v-model="bulkPayForm.payment_date" type="date" class="form-input" />
            </div>
            <div>
              <label class="form-label">Payment Method</label>
              <select v-model="bulkPayForm.method" class="form-input">
                <option v-for="m in METHODS" :key="m" :value="m" class="capitalize">{{ m.toUpperCase() }}</option>
              </select>
            </div>
            <div v-if="bulkError" class="text-sm text-danger-600 bg-danger-50 rounded-lg px-3 py-2">{{ bulkError }}</div>
          </div>

          <div class="flex gap-3 mt-6">
            <button @click="bulkPayModal = false"
              class="flex-1 btn bg-gray-100 text-gray-700 hover:bg-gray-200 rounded-xl py-3 font-bold">
              Cancel
            </button>
            <button @click="doBulkMarkPaid" :disabled="bulkPaying"
              class="flex-1 btn bg-emerald-600 text-white hover:bg-emerald-700 rounded-xl py-3 font-bold disabled:opacity-60">
              {{ bulkPaying ? 'Saving…' : 'Confirm Paid' }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
