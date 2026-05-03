<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { list, task, all } from '../../api'
import HelpIcon from '../../components/HelpIcon.vue'
import { inr } from '../../utils/currency'
import { fmtDateShort } from '../../utils/date'

const router       = useRouter()
const expenses     = ref([])
const categories   = ref([])
const loading      = ref(true)
const deleteTarget = ref(null)
const deleting     = ref(false)
const searchQ      = ref('')
const catFilter    = ref('')
const showFilters = ref(false)
const filter = ref({ preset: '', from_date: '', to_date: '' })

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
  filter.value.preset = p; showFilters.value = false
  if (p === 'today') { filter.value.from_date = fmt(now); filter.value.to_date = fmt(now) }
  else if (p === 'week') { const s = new Date(now); s.setDate(now.getDate() - now.getDay() + 1); filter.value.from_date = fmt(s); filter.value.to_date = fmt(now) }
  else if (p === 'month') { filter.value.from_date = fmt(new Date(now.getFullYear(), now.getMonth(), 1)); filter.value.to_date = fmt(now) }
  else if (p === 'last_month') { filter.value.from_date = fmt(new Date(now.getFullYear(), now.getMonth() - 1, 1)); filter.value.to_date = fmt(new Date(now.getFullYear(), now.getMonth(), 0)) }
  else if (p === 'quarter') { const q = Math.floor(now.getMonth() / 3); filter.value.from_date = fmt(new Date(now.getFullYear(), q * 3, 1)); filter.value.to_date = fmt(now) }
  else if (p === 'year') { filter.value.from_date = fmt(new Date(now.getFullYear(), 0, 1)); filter.value.to_date = fmt(now) }
  load()
}
function clearDate() { filter.value.preset = ''; filter.value.from_date = ''; filter.value.to_date = ''; showFilters.value = false; load() }
const activeDateLabel = () => {
  if (filter.value.preset) return presets.find(p => p.value === filter.value.preset)?.label
  if (filter.value.from_date && filter.value.to_date) return `${filter.value.from_date} → ${filter.value.to_date}`
  return null
}

// ── Load ──────────────────────────────────────────────────────────────────────
async function load() {
  loading.value = true
  try {
    const p = { sort_by: 'e.expense_date', sort_order: 'desc' }
    if (filter.value.from_date) p['filter.from_date'] = filter.value.from_date
    if (filter.value.to_date)   p['filter.to_date']   = filter.value.to_date
    const [eRes, cRes] = await Promise.all([list('Expense', p), all('ExpenseCategory')])
    expenses.value   = eRes.data?.data || []
    categories.value = cRes.data?.data || []
  } catch {}
  loading.value = false
}

const filteredExpenses = () => {
  let e = expenses.value
  if (catFilter.value) e = e.filter(x => x.category_id == catFilter.value)
  if (searchQ.value) {
    const q = searchQ.value.toLowerCase()
    e = e.filter(x => x.description?.toLowerCase().includes(q) || x.vendor_name?.toLowerCase().includes(q))
  }
  return e
}

function openAdd() {
  router.push('/expenses/new')
}

function openEdit(exp) {
  router.push(`/expenses/${exp.id}/edit`)
}

async function confirmDelete() {
  deleting.value = true
  try {
    await task('Expense', 'delete', { id: deleteTarget.value.id })
    deleteTarget.value = null; selectedId.value = null
    await load()
  } catch { deleteTarget.value = null }
  finally { deleting.value = false }
}

// Method icons / colors
const methodColors = {
  cash: 'bg-green-100 text-green-700', upi: 'bg-purple-100 text-purple-700',
  neft: 'bg-blue-100 text-blue-700', cheque: 'bg-amber-100 text-amber-700',
  card: 'bg-pink-100 text-pink-700', other: 'bg-gray-100 text-gray-600',
}
const methodLabel = m => ({ cash: 'Cash', upi: 'UPI', neft: 'NEFT', cheque: 'Cheque', card: 'Card', other: 'Other' }[m] || m)

onMounted(load)
</script>

<template>
  <div class="flex flex-col lg:flex-row gap-6 h-full min-h-0">
    <!-- Left Pane: List -->
    <div :class="{ 'hidden lg:flex': $route.name !== 'Expenses', 'w-full lg:w-[35%] flex flex-col min-h-0': true }">
      <div class="flex flex-col gap-2 pr-1 shrink-0 z-10 relative">
        <!-- Compact Header -->
        <div class="flex items-center justify-between gap-3">
          <h1 class="page-title flex items-center gap-2">Expenses <HelpIcon section="expenses" /></h1>
          <div class="flex items-center gap-2">
            <button @click="showFilters = !showFilters" class="p-2 text-gray-500 hover:text-primary-600 hover:bg-primary-50 rounded-full transition-colors shadow-sm bg-white border border-gray-100" :class="{'bg-primary-50 text-primary-600 border-primary-100': showFilters || filter.preset || filter.from_date || catFilter}">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
            </button>
            <button @click="openAdd" class="lg:hidden p-2 text-white bg-primary-600 hover:bg-primary-700 rounded-full transition-colors shadow-soft-blue">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            </button>
          </div>
        </div>

        <!-- Search Row -->
        <div class="flex gap-2 animate-fade-in-up z-10 relative">
          <div class="relative flex-1 min-w-0">
            <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input v-model="searchQ" type="text"
              class="w-full bg-white border-0 shadow-soft text-gray-900 text-sm rounded-xl focus:ring-2 focus:ring-primary-500 block pl-10 p-2.5 transition-shadow"
              placeholder="Search by description or vendor…" />
          </div>
        </div>

        <!-- Category Pill Tabs -->
        <div v-if="categories.length" class="flex gap-1.5 overflow-x-auto no-scrollbar pb-0.5">
          <button
            @click="catFilter = ''"
            class="shrink-0 px-3 py-1.5 rounded-full text-xs font-bold whitespace-nowrap transition-all"
            :class="catFilter === '' ? 'bg-primary-600 text-white shadow-soft-blue' : 'bg-white text-gray-500 shadow-soft hover:bg-gray-50'">All
          </button>
          <button v-for="c in categories" :key="c.id"
            @click="catFilter = c.id"
            class="shrink-0 px-3 py-1.5 rounded-full text-xs font-bold whitespace-nowrap transition-all"
            :class="catFilter === c.id ? 'bg-primary-600 text-white shadow-soft-blue' : 'bg-white text-gray-500 shadow-soft hover:bg-gray-50'">{{ c.name }}
          </button>
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
        <div class="bg-white rounded-[2rem] shadow-soft border-0 overflow-hidden mt-2 lg:mt-0 animate-fade-in-up anim-delay-75">
          <div v-if="loading" class="p-12 text-center text-gray-400 text-sm">Loading…</div>
          <div v-else-if="!filteredExpenses().length" class="p-12 text-center">
            <div class="w-16 h-16 rounded-full bg-orange-50 flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            </div>
            <p class="font-extrabold text-gray-900 text-lg">No expenses found</p>
            <p class="text-sm text-gray-500 mt-1">Record rent, purchases, travel and other costs</p>
            <button @click="openAdd" class="btn bg-primary-600 text-white hover:bg-primary-700 shadow-soft-blue rounded-full px-6 py-2.5 mt-5 inline-flex items-center gap-2 font-bold">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
              Record First Expense
            </button>
          </div>
          <div v-else class="divide-y divide-gray-50">
            <div v-for="e in filteredExpenses()" :key="e.id"
              class="flex items-center gap-4 px-5 py-4 hover:bg-gray-50 active:bg-gray-100 cursor-pointer transition-colors group"
              @click="openEdit(e)">

              <!-- Category icon -->
              <div class="w-12 h-12 rounded-full flex items-center justify-center shrink-0 text-lg font-extrabold"
                :class="methodColors[e.method] || 'bg-gray-100 text-gray-600'">
                {{ methodLabel(e.method).charAt(0) }}
              </div>

              <div class="flex-1 min-w-0">
                <p class="font-bold text-gray-900 text-base truncate group-hover:text-primary-700 transition-colors">{{ e.description }}</p>
                <p class="text-xs text-gray-500 mt-0.5 truncate">
                  {{ e.category_name || 'General' }} · {{ fmtDateShort(e.expense_date) }}
                  <span v-if="e.vendor_name"> · {{ e.vendor_name }}</span>
                </p>
              </div>

              <div class="text-right shrink-0 flex flex-col items-end">
                <p class="font-extrabold text-gray-900 text-base">{{ inr(e.total_amount) }}</p>
                <p v-if="e.gst_amount > 0" class="text-[10px] uppercase font-bold tracking-wider mt-1 text-gray-400 bg-gray-50 px-2 py-0.5 rounded-full">GST {{ inr(e.gst_amount) }}</p>
                <p v-else class="text-[10px] uppercase font-bold tracking-wider mt-1 px-2 py-0.5 rounded-full" :class="methodColors[e.method] || 'text-gray-400 bg-gray-50'">{{ methodLabel(e.method) }}</p>
              </div>

              <svg class="w-5 h-5 text-gray-300 shrink-0 group-hover:text-primary-500 group-hover:translate-x-1 transition-all ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </div>
          </div>
          <!-- List Footer -->
          <div v-if="!loading && filteredExpenses().length" class="bg-gray-50/80 border-t border-gray-100 px-6 py-4 flex items-center justify-between">
            <span class="text-xs text-gray-500 font-medium">Showing <span class="font-bold text-gray-800">{{ filteredExpenses().length }}</span> expense{{ filteredExpenses().length !== 1 ? 's' : '' }}</span>
            <span class="text-[10px] text-gray-400 font-bold uppercase tracking-widest flex items-center gap-1.5">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
              End of list
            </span>
          </div>
        </div>
      </div>

    <!-- Delete confirm -->
    <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
      <div class="bg-white rounded-2xl w-full max-w-sm shadow-xl p-6 space-y-4">
        <h3 class="font-semibold text-gray-900">Remove this expense?</h3>
        <p class="text-sm text-gray-600"><strong>{{ deleteTarget.description }}</strong> will be permanently deleted.</p>
        <div class="flex gap-3">
          <button @click="deleteTarget = null" class="btn-outline flex-1" :disabled="deleting">Cancel</button>
          <button @click="confirmDelete" :disabled="deleting" class="btn-primary flex-1 bg-danger-600 hover:bg-danger-700 border-danger-600">
            {{ deleting ? 'Removing…' : 'Yes, Remove' }}
          </button>
        </div>
      </div>
    </div>
    </div>

  <!-- Right Pane: Detail/Form -->
    <div v-if="$route.name !== 'Expenses'" class="w-full lg:w-[65%] flex-1 overflow-y-auto no-scrollbar pb-10">
      <router-view v-slot="{ Component }">
        <component :is="Component" :key="$route.fullPath" @refresh="load" />
      </router-view>
    </div>
  </div>
</template>
