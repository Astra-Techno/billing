<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { list } from '../../api'
import { inr } from '../../utils/currency'
import { fmtDateShort } from '../../utils/date'
import QuoteDetail from './QuoteDetail.vue'

const router     = useRouter()
const quotes     = ref([])
const loading    = ref(true)
const selectedId = ref(null)
const showDatePicker = ref(false)

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
  showDatePicker.value = false
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
  showDatePicker.value = false; load()
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
  if (window.innerWidth >= 1024) selectedId.value = String(q.id)
  else router.push('/quotes/' + q.id)
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
  <div class="lg:flex lg:h-full lg:-mx-8 lg:-mt-5">

    <!-- ── LEFT: 40% list ──────────────────────────────────────────────────── -->
    <div class="lg:w-2/5 lg:flex lg:flex-col lg:border-r border-gray-200 lg:overflow-hidden"
         :class="selectedId ? 'hidden lg:flex' : ''">

      <div class="lg:px-5 lg:pt-5 pb-3 lg:border-b lg:border-gray-100 space-y-3">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="page-title">Quotations</h1>
            <p class="text-xs text-gray-400 mt-0.5">Send price quotes to your customers</p>
          </div>
          <RouterLink to="/quotes/new" class="btn-primary text-sm py-2 px-4">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            New Quote
          </RouterLink>
        </div>

        <!-- Status tabs -->
        <div class="flex gap-1 overflow-x-auto pb-1 no-scrollbar">
          <button v-for="t in tabs" :key="t.value"
            @click="filter.status = t.value; load()"
            class="px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition-all shrink-0"
            :class="filter.status === t.value
              ? 'bg-primary-600 text-white shadow-sm'
              : 'bg-white text-gray-500 border border-gray-200 hover:border-gray-300'">
            {{ t.label }}
          </button>
        </div>

        <!-- Search -->
        <div class="relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input v-model="filter.search" @input="onSearch" type="text" class="form-input pl-9 py-2 text-sm" placeholder="Search by quote no. or customer…" />
        </div>

        <!-- Date presets -->
        <div class="flex items-center gap-1.5 flex-wrap">
          <div class="flex gap-1 overflow-x-auto no-scrollbar">
            <button v-for="p in presets" :key="p.value" @click="applyPreset(p.value)"
              class="px-2.5 py-1 rounded-lg text-xs font-medium whitespace-nowrap shrink-0 transition-all"
              :class="filter.preset === p.value ? 'bg-indigo-100 text-indigo-700 border border-indigo-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'">
              {{ p.label }}
            </button>
          </div>
          <button @click="showDatePicker = !showDatePicker"
            class="px-2.5 py-1 rounded-lg text-xs font-medium bg-gray-100 text-gray-500 hover:bg-gray-200 shrink-0 flex items-center gap-1">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Custom
          </button>
          <button v-if="activeDateLabel()" @click="clearDate"
            class="px-2 py-1 rounded-lg text-xs font-medium bg-red-50 text-red-500 hover:bg-red-100 shrink-0">
            ✕ {{ activeDateLabel() }}
          </button>
        </div>

        <div v-if="showDatePicker" class="flex gap-2 items-center">
          <input v-model="filter.from_date" type="date" class="form-input py-1.5 text-xs flex-1" @change="filter.preset = ''; load()" />
          <span class="text-gray-400 text-xs">to</span>
          <input v-model="filter.to_date" type="date" class="form-input py-1.5 text-xs flex-1" @change="filter.preset = ''; load()" />
        </div>
      </div>

      <!-- List -->
      <div class="lg:flex-1 lg:overflow-y-auto">
        <div class="card overflow-hidden lg:rounded-none lg:border-x-0 lg:border-b-0 lg:shadow-none mt-4 lg:mt-0">
          <div v-if="loading" class="divide-y divide-gray-50">
            <div v-for="i in 5" :key="i" class="flex items-center gap-3 px-4 py-3.5 animate-pulse">
              <div class="w-10 h-10 rounded-xl bg-gray-100 shrink-0"></div>
              <div class="flex-1 space-y-2">
                <div class="h-3 bg-gray-100 rounded w-2/3"></div>
                <div class="h-2.5 bg-gray-100 rounded w-1/3"></div>
              </div>
              <div class="h-3 bg-gray-100 rounded w-16"></div>
            </div>
          </div>
          <div v-else-if="!quotes.length" class="p-10 text-center">
            <div class="w-14 h-14 rounded-2xl bg-warning-50 flex items-center justify-center mx-auto mb-3">
              <svg class="w-7 h-7 text-warning-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"/></svg>
            </div>
            <p class="font-semibold text-gray-700 text-sm">No quotations found</p>
            <p class="text-xs text-gray-400 mt-1">Create a quote to send to your customers</p>
            <RouterLink to="/quotes/new" class="btn-primary btn-sm mt-3">Create First Quote</RouterLink>
          </div>
          <div v-else class="divide-y divide-gray-50">
            <div v-for="q in quotes" :key="q.id"
              class="flex items-center gap-3 px-4 py-3.5 hover:bg-blue-50/40 cursor-pointer transition-colors group"
              :class="selectedId === String(q.id) ? 'bg-primary-50 border-l-2 border-primary-500' : ''"
              @click="onQuoteClick(q)">
              <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 font-bold text-sm" :class="avatarColor(q.client_name)">
                {{ q.client_name?.charAt(0)?.toUpperCase() }}
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <p class="font-semibold text-gray-800 text-sm truncate group-hover:text-primary-700">{{ q.client_name }}</p>
                  <span :class="badgeClass(q.status)" class="text-[10px]">{{ statusLabel(q.status) }}</span>
                </div>
                <p class="text-[11px] text-gray-400 mt-0.5 truncate">{{ q.number }} · {{ fmtDateShort(q.issue_date) }}</p>
              </div>
              <div class="text-right shrink-0">
                <p class="font-bold text-gray-900 text-sm">{{ inr(q.total) }}</p>
                <p class="text-[11px] text-gray-400">Valid till {{ fmtDateShort(q.valid_until) }}</p>
              </div>
              <svg class="w-3.5 h-3.5 text-gray-300 shrink-0 group-hover:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── RIGHT: 60% detail ───────────────────────────────────────────────── -->
    <div class="hidden lg:flex lg:flex-1 lg:flex-col lg:overflow-y-auto bg-slate-50">
      <div v-if="!selectedId" class="flex-1 flex flex-col items-center justify-center text-center gap-3 text-gray-400 p-10">
        <div class="w-20 h-20 rounded-3xl bg-white border-2 border-dashed border-gray-200 flex items-center justify-center">
          <svg class="w-9 h-9 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"/></svg>
        </div>
        <div>
          <p class="font-semibold text-gray-500">Select a quotation to view details</p>
          <p class="text-sm mt-0.5">or create a new one</p>
        </div>
        <RouterLink to="/quotes/new" class="btn-primary mt-2">+ New Quotation</RouterLink>
      </div>

      <div v-else class="flex-1 overflow-y-auto">
        <div class="p-4 border-b border-gray-200 bg-white flex items-center gap-3 sticky top-0 z-10">
          <button @click="selectedId = null" class="text-gray-400 hover:text-gray-700 p-1 rounded-lg hover:bg-gray-100">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
          </button>
          <span class="text-sm font-semibold text-gray-600">Quote Detail</span>
          <RouterLink :to="'/quotes/' + selectedId" class="ml-auto text-xs text-primary-600 hover:underline">Open full page →</RouterLink>
        </div>
        <div class="p-4">
          <QuoteDetail :panelId="selectedId" @back="selectedId = null" @refresh="load" />
        </div>
      </div>
    </div>

  </div>
</template>
