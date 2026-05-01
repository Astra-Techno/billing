<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { list } from '../../api'
import { inr } from '../../utils/currency'
import { fmtDateShort } from '../../utils/date'
import HelpIcon from '../../components/HelpIcon.vue'

const router     = useRouter()
const quotes     = ref([])
const loading    = ref(true)
const showFilters = ref(false)

const filter = ref({ status: '', search: '', from_date: '', to_date: '', preset: '' })
let timer = null

// ── Date presets ──────────────────────────────────────────────────────────────
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
  showFilters.value = false
  if (p === 'today') {
    filter.value.from_date = fmt(now); filter.value.to_date = fmt(now)
  } else if (p === 'week') {
    const s = new Date(now); s.setDate(now.getDate() - now.getDay() + 1)
    filter.value.from_date = fmt(s); filter.value.to_date = fmt(now)
  } else if (p === 'month') {
    filter.value.from_date = fmt(new Date(now.getFullYear(), now.getMonth(), 1))
    filter.value.to_date = fmt(now)
  } else if (p === 'last_month') {
    filter.value.from_date = fmt(new Date(now.getFullYear(), now.getMonth() - 1, 1))
    filter.value.to_date = fmt(new Date(now.getFullYear(), now.getMonth(), 0))
  } else if (p === 'quarter') {
    const q = Math.floor(now.getMonth() / 3)
    filter.value.from_date = fmt(new Date(now.getFullYear(), q * 3, 1))
    filter.value.to_date = fmt(now)
  } else if (p === 'year') {
    filter.value.from_date = fmt(new Date(now.getFullYear(), 0, 1))
    filter.value.to_date = fmt(now)
  }
  load()
}

function clearDate() {
  filter.value.preset = ''; filter.value.from_date = ''; filter.value.to_date = ''
  showFilters.value = false; load()
}

const activeDateLabel = () => {
  if (filter.value.preset) return presets.find(p => p.value === filter.value.preset)?.label
  if (filter.value.from_date && filter.value.to_date) return `${filter.value.from_date} → ${filter.value.to_date}`
  return null
}

// ── Load ──────────────────────────────────────────────────────────────────────
async function load() {
  loading.value = true
  try {
    const p = { sort_by: 'q.created_at', sort_order: 'desc' }
    if (filter.value.status)    p['filter.status']    = filter.value.status
    if (filter.value.search)    p['filter.search']    = `%${filter.value.search}%`
    if (filter.value.from_date) p['filter.from_date'] = filter.value.from_date
    if (filter.value.to_date)   p['filter.to_date']   = filter.value.to_date
    const { data } = await list('Quote', p)
    quotes.value = data.data || []
  } catch {}
  loading.value = false
}

function onSearch() { clearTimeout(timer); timer = setTimeout(load, 350) }

function onQuoteClick(q) {
  router.push('/quotes/' + q.id)
}

onMounted(load)

const tabs = [
  { label: 'All',       value: '' },
  { label: 'Draft',     value: 'draft' },
  { label: 'Sent',      value: 'sent' },
  { label: 'Accepted',  value: 'accepted' },
  { label: 'Converted', value: 'converted' },
]

const statusLabel = s => ({ draft: 'Draft', sent: 'Sent', accepted: 'Accepted', declined: 'Declined', expired: 'Expired', converted: 'Converted' }[s] || s)
const badgeClass  = s => ({ draft: 'badge-gray', sent: 'badge-blue', accepted: 'badge-green', declined: 'badge-red', expired: 'badge-yellow', converted: 'badge-green' }[s] || 'badge-gray')

const avatarColors = ['bg-amber-100 text-amber-700', 'bg-blue-100 text-blue-700', 'bg-emerald-100 text-emerald-700', 'bg-purple-100 text-purple-700', 'bg-pink-100 text-pink-700']
const avatarColor  = name => avatarColors[(name?.charCodeAt(0) || 0) % avatarColors.length]
</script>

<template>
  <div class="flex flex-col lg:flex-row gap-6 h-full min-h-0">
    <!-- Left Pane: List -->
    <div :class="{ 'hidden lg:flex': $route.name !== 'Quotes', 'w-full lg:w-[35%] flex flex-col min-h-0': true }">
      <div class="flex flex-col gap-2 pr-1 shrink-0 z-10 relative">
    <!-- Compact Header -->
    <div class="flex items-center justify-between gap-3">
      <h1 class="page-title flex items-center gap-2">Quotations <HelpIcon section="quotes" /></h1>
      <div class="flex items-center gap-2">
        <button @click="showFilters = !showFilters" class="p-2 text-gray-500 hover:text-primary-600 hover:bg-primary-50 rounded-full transition-colors shadow-sm bg-white border border-gray-100" :class="{'bg-primary-50 text-primary-600 border-primary-100': showFilters || filter.preset || filter.from_date}">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
        </button>
        <!-- Mobile only New Bill -->
        <RouterLink to="/quotes/new" class="lg:hidden p-2 text-white bg-primary-600 hover:bg-primary-700 rounded-full transition-colors shadow-soft-blue">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
        </RouterLink>
      </div>
    </div>

    <!-- Search & Status Row -->
    <div class="flex gap-2 animate-fade-in-up z-10 relative">
      <div class="relative flex-1 min-w-0">
        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input v-model="filter.search" @input="onSearch" type="text"
          class="w-full bg-white border-0 shadow-soft text-gray-900 text-sm rounded-xl focus:ring-2 focus:ring-primary-500 block pl-10 p-2.5 transition-shadow"
          placeholder="Search by quote no. or customer…" />
      </div>
      <div class="shrink-0 w-[110px] relative">
        <select v-model="filter.status" @change="load()" class="w-full h-full bg-white border-0 shadow-soft text-gray-700 text-xs rounded-xl focus:ring-2 focus:ring-primary-500 pl-3 pr-8 appearance-none cursor-pointer font-bold">
          <option value="">All Status</option>
          <option v-for="t in tabs.filter(t => t.value)" :key="t.value" :value="t.value">{{ t.label }}</option>
        </select>
        <svg class="w-3.5 h-3.5 text-gray-400 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
      </div>
    </div>

    <!-- Collapsible Date Filters -->
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
      
      <!-- Custom date picker (always visible when filters are open, if preset is empty) -->
      <div class="flex gap-2 items-center bg-white p-2.5 rounded-xl shadow-soft border border-gray-100">
        <input v-model="filter.from_date" type="date" class="form-input text-xs flex-1 border-0 bg-gray-50 rounded-lg"
          @change="filter.preset = ''; load()" />
        <span class="text-gray-400 text-xs font-medium">to</span>
        <input v-model="filter.to_date" type="date" class="form-input text-xs flex-1 border-0 bg-gray-50 rounded-lg"
          @change="filter.preset = ''; load()" />
      </div>
    </div>
    </div>

    <!-- Scrollable List Wrapper -->
    <div class="flex-1 overflow-y-auto pr-1 pb-10 mt-2 no-scrollbar min-h-0">
      <!-- List -->
    <div class="bg-white rounded-[2rem] shadow-soft border-0 overflow-hidden animate-fade-in-up anim-delay-75">
          <div v-if="loading" class="divide-y divide-gray-50">
            <div v-for="i in 5" :key="i" class="flex items-center gap-4 px-5 py-4 animate-pulse">
              <div class="w-12 h-12 rounded-full bg-gray-100 shrink-0"></div>
              <div class="flex-1 space-y-2">
                <div class="h-3 bg-gray-100 rounded w-2/3"></div>
                <div class="h-2.5 bg-gray-100 rounded w-1/3"></div>
              </div>
              <div class="h-3 bg-gray-100 rounded w-16"></div>
            </div>
          </div>
          <div v-else-if="!quotes.length" class="p-12 text-center">
            <div class="w-16 h-16 rounded-full bg-warning-50 flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8 text-warning-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"/></svg>
            </div>
            <p class="font-extrabold text-gray-900 text-lg">No quotations found</p>
            <p class="text-sm text-gray-500 mt-1">Create a quote to send to your customers</p>
            <RouterLink to="/quotes/new" class="btn bg-primary-600 text-white hover:bg-primary-700 shadow-soft-blue rounded-full px-6 py-2.5 mt-5 inline-flex items-center gap-2 font-bold">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
              Create First Quote
            </RouterLink>
          </div>
          <div v-else class="divide-y divide-gray-50">
            <div v-for="q in quotes" :key="q.id"
              class="flex items-center gap-4 px-5 py-4 hover:bg-gray-50 active:bg-gray-100 cursor-pointer transition-colors group"
              @click="onQuoteClick(q)">
              <div class="w-12 h-12 rounded-full flex items-center justify-center shrink-0 font-extrabold text-lg" :class="avatarColor(q.client_name)">
                {{ q.client_name?.charAt(0)?.toUpperCase() }}
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <p class="font-bold text-gray-900 text-base truncate group-hover:text-primary-700 transition-colors">{{ q.client_name }}</p>
                  <span :class="badgeClass(q.status)" class="text-[10px]">{{ statusLabel(q.status) }}</span>
                </div>
                <p class="text-xs text-gray-500 mt-0.5 truncate">{{ q.number }} · {{ fmtDateShort(q.issue_date) }}</p>
              </div>
              <div class="text-right shrink-0 flex flex-col items-end">
                <p class="font-extrabold text-gray-900 text-base">{{ inr(q.total) }}</p>
                <p class="text-[10px] uppercase font-bold tracking-wider mt-1 text-gray-400 bg-gray-50 px-2 py-0.5 rounded-full">Till {{ fmtDateShort(q.valid_until) }}</p>
              </div>
            </div>
          </div>

          <!-- List Footer -->
          <div v-if="!loading && quotes.length" class="bg-gray-50/80 border-t border-gray-100 px-6 py-4 flex items-center justify-between">
            <span class="text-xs text-gray-500 font-medium">Showing <span class="font-bold text-gray-800">{{ quotes.length }}</span> quote{{ quotes.length !== 1 ? 's' : '' }}</span>
            <span class="text-[10px] text-gray-400 font-bold uppercase tracking-widest flex items-center gap-1.5">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
              End of list
            </span>
          </div>

        </div>
      </div>
    </div>

    <!-- Right Pane: Detail/Form -->
    <div v-if="$route.name !== 'Quotes'" class="w-full lg:w-[65%] flex-1 overflow-y-auto no-scrollbar pb-10">
      <router-view v-slot="{ Component }">
        <component :is="Component" :key="$route.fullPath" @refresh="load" />
      </router-view>
    </div>
  </div>
</template>
