<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { task, all } from '../../api'
import HelpIcon from '../../components/HelpIcon.vue'
import ExpenseForm from './ExpenseForm.vue'
import { inr } from '../../utils/currency'
import { fmtDateShort } from '../../utils/date'
import { useTour } from '../../composables/useTour'
import { usePagedList } from '../../composables/usePagedList'
import { useRole } from '../../composables/useRole'

const { startTour, isTourSeen } = useTour('expense-list', [
  { target: '[data-tour="exp-categories"]', title: 'Category Tabs', text: 'Filter expenses by category. All your expense categories appear here.' },
  { target: '[data-tour="exp-list"]', title: 'Expense List', text: 'Your recorded expenses are listed here with amount, category, and payment method.' },
  { target: '[data-tour="exp-add"]', title: 'Add Expense', text: 'Record a new business expense with vendor, category, and GST details.' },
])

const { can } = useRole()
const router       = useRouter()
const route        = useRoute()
const categories   = ref([])
const deleteTarget = ref(null)
const deleting     = ref(false)
const catFilter    = ref('')
const showFilters = ref(false)
const filter = ref({ preset: '', from_date: '', to_date: '' })

const { items: expenses, loading, loadingMore, total, hasMore, search: searchQ, onSearch, loadMore, reload } =
  usePagedList('Expense', {
    limit: 50,
    scrollContainer: '#expense-scroll',
    listRouteName: 'Expenses',
    params: () => {
      const p = { sort_by: 'e.expense_date', sort_order: 'desc' }
      if (filter.value.from_date) p['filter.from_date'] = filter.value.from_date
      if (filter.value.to_date)   p['filter.to_date']   = filter.value.to_date
      return p
    },
  })

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
  reload()
}
function clearDate() { filter.value.preset = ''; filter.value.from_date = ''; filter.value.to_date = ''; showFilters.value = false; reload() }
const activeDateLabel = () => {
  if (filter.value.preset) return presets.find(p => p.value === filter.value.preset)?.label
  if (filter.value.from_date && filter.value.to_date) return `${filter.value.from_date} → ${filter.value.to_date}`
  return null
}

// ── Load categories separately ───────────────────────────────────────────────
onMounted(async () => {
  try {
    const cRes = await all('ExpenseCategory')
    categories.value = cRes.data?.data || []
  } catch {}
  setTimeout(() => { if (!isTourSeen()) startTour() }, 800)
})

const filteredExpenses = () => {
  let e = expenses.value
  if (catFilter.value) e = e.filter(x => x.category_id == catFilter.value)
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
    deleteTarget.value = null
    await reload()
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

</script>

<template>
  <div class="flex flex-col lg:flex-row h-full min-h-0 w-full overflow-hidden">
    
    <!-- Left Pane: List -->
    <div id="c3-left-panel" :class="{ 'hidden lg:flex': $route.name !== 'Expenses', 'split-pane-left transition-all duration-300 relative z-30 h-full': true }">
      
      <!-- Top Sticky Header Area -->
      <div class="px-5 py-4 border-b border-gray-200/60 bg-white/60 backdrop-blur-md sticky top-0 z-10">
        <!-- Header & Actions -->
        <div class="flex justify-between items-center mb-4">
            <h2 class="font-bold text-gray-900 text-sm tracking-tight flex items-center gap-2">Expenses <HelpIcon section="expenses" class="w-3.5 h-3.5" />
              <button @click="startTour()" class="text-[10px] font-bold text-primary-500 hover:text-primary-700 ml-1" title="Take a tour">Tour</button>
            </h2>
            <div class="flex gap-2">
                <!-- Search Toggle -->
                <button @click="showFilters = !showFilters" class="w-7 h-7 bg-white border border-gray-200/80 shadow-sm hover:shadow hover:border-gray-300 rounded-lg flex items-center justify-center transition-all" :class="showFilters ? 'text-primary-600 border-primary-200 bg-primary-50' : 'text-gray-600'">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </button>
                <!-- New Expense -->
                <button v-if="can('expenses.create')" @click="openAdd" data-tour="exp-add" class="w-7 h-7 bg-white border border-gray-200/80 shadow-sm hover:shadow hover:border-gray-300 rounded-lg flex items-center justify-center text-gray-600 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                </button>
            </div>
        </div>

        <!-- Search / Filter Expansion -->
        <div v-show="showFilters" class="mb-4 space-y-2 animate-fade-in-up">
            <input :value="searchQ" @input="onSearch($event.target.value)" type="text"
              class="w-full bg-white border border-gray-200 shadow-sm text-gray-900 text-xs font-semibold rounded-lg focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 block px-3 py-2 transition-all"
              placeholder="Search vendor, description, amount..." />
            
            <div class="flex gap-2 items-center">
              <input v-model="filter.from_date" type="date" class="w-full bg-white border border-gray-200 shadow-sm text-gray-900 text-[11px] font-semibold rounded-lg px-2 py-1.5 focus:border-primary-500 transition-all" @change="filter.preset = ''; reload()" />
              <span class="text-gray-400 text-[10px] font-bold uppercase">to</span>
              <input v-model="filter.to_date" type="date" class="w-full bg-white border border-gray-200 shadow-sm text-gray-900 text-[11px] font-semibold rounded-lg px-2 py-1.5 focus:border-primary-500 transition-all" @change="filter.preset = ''; reload()" />
            </div>
        </div>

        <!-- Category Tabs -->
        <div v-if="categories.length" data-tour="exp-categories" class="flex gap-1 bg-gray-100/80 p-1 rounded-[10px] ring-1 ring-inset ring-gray-200/50 overflow-x-auto hide-scrollbar">
            <button @click="catFilter = ''"
              class="flex-1 text-[11px] font-semibold rounded-md py-1.5 transition-all whitespace-nowrap px-3"
              :class="catFilter === '' ? 'bg-white shadow-sm text-gray-900 font-bold' : 'text-gray-500 hover:text-gray-700'">
              All
            </button>
            <button v-for="c in categories" :key="c.id"
              @click="catFilter = c.id"
              class="flex-1 text-[11px] font-semibold rounded-md py-1.5 transition-all whitespace-nowrap px-3"
              :class="catFilter === c.id ? 'bg-white shadow-sm text-gray-900 font-bold' : 'text-gray-500 hover:text-gray-700'">
              {{ c.name }}
            </button>
        </div>
      </div>

      <!-- Scrollable List -->
      <div id="expense-scroll" class="flex-1 overflow-y-auto px-3 py-3 space-y-1.5 custom-scrollbar min-h-0" data-tour="exp-list">

          <div v-if="loading" class="space-y-1.5">
            <div v-for="i in 6" :key="i" class="p-4 rounded-xl border border-gray-100 bg-white/40 animate-pulse flex justify-between">
              <div class="space-y-2"><div class="h-3.5 bg-gray-200 rounded w-24"></div><div class="h-2.5 bg-gray-100 rounded w-16"></div></div>
              <div class="h-3.5 bg-gray-200 rounded w-16"></div>
            </div>
          </div>

          <div v-else-if="!filteredExpenses().length" class="p-8 text-center">
            <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
              <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            </div>
            <p class="font-bold text-gray-900 text-[13px]">No expenses found</p>
            <p class="text-[11px] text-gray-500 mt-1">Record an expense to get started</p>
          </div>

          <div v-else v-for="(e, idx) in filteredExpenses()" :key="e.id"
            class="p-4 rounded-xl border cursor-pointer transition-all group relative overflow-hidden list-item-1"
            :style="{ animationDelay: (idx * 0.05) + 's' }"
            :class="[
              $route.params.id == e.id ? 'bg-white border-gray-200 shadow-[0_2px_8px_rgba(0,0,0,0.03)]' : 'border-transparent hover:border-gray-200/60 hover:bg-white hover:shadow-[0_2px_8px_rgba(0,0,0,0.02)]'
            ]"
            @click="openEdit(e)">
            
            <!-- Active Indicator Line -->
            <div v-if="$route.params.id == e.id" class="absolute left-0 top-0 bottom-0 w-[3px] bg-gray-900 rounded-l-xl"></div>
            
            <div class="flex gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-start mb-0.5">
                        <span class="text-[14px] font-bold truncate pr-2 tracking-tight transition-colors"
                              :class="$route.params.id == e.id ? 'text-primary-600' : 'text-gray-900 group-hover:text-primary-600'">
                              {{ e.description }}
                        </span>
                        <span class="text-[14px] font-bold tabular-nums text-gray-900 shrink-0">
                              {{ inr(e.total_amount) }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center mt-1.5">
                        <span class="text-[12px] font-semibold text-gray-400 truncate pr-2">
                            {{ e.category_name || 'General' }} <span v-if="e.vendor_name" class="text-gray-300 mx-1">•</span> <span v-if="e.vendor_name">{{ e.vendor_name }}</span>
                        </span>
                        
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-[5px] tracking-wider flex items-center border shrink-0 whitespace-nowrap"
                              :class="{
                                  'text-emerald-600 bg-emerald-50 border-emerald-100': e.method === 'cash',
                                  'text-purple-600 bg-purple-50 border-purple-100': e.method === 'upi',
                                  'text-blue-600 bg-blue-50 border-blue-100': e.method === 'neft',
                                  'text-amber-600 bg-amber-50 border-amber-100': e.method === 'cheque',
                                  'text-pink-600 bg-pink-50 border-pink-100': e.method === 'card',
                                  'text-gray-600 bg-gray-100 border-gray-200/60': e.method === 'other' || !e.method
                              }">
                            {{ methodLabel(e.method) || 'Other' }}
                        </span>
                    </div>
                    <div class="text-[10px] font-medium text-gray-400 mt-1">{{ fmtDateShort(e.expense_date) }}</div>
                </div>
            </div>
          </div>

          <!-- Load more / Showing all -->
          <div v-if="!loading && expenses.length" class="text-center py-3">
            <div v-if="loadingMore" class="text-[11px] text-gray-400 font-semibold">Loading more...</div>
            <div v-else-if="hasMore" class="text-[11px] text-gray-400 font-semibold">Showing {{ expenses.length }} of {{ total }} — scroll for more</div>
            <div v-else class="text-[11px] text-gray-400 font-semibold">Showing all {{ expenses.length }}</div>
          </div>
      </div>
    </div>

    <!-- Right Pane: Detail/Form wrapper -->
    <div id="c3-right-view" class="split-pane-right relative z-20 hidden lg:flex" :class="$route.name !== 'Expenses' ? '!flex' : ''">
      <router-view v-slot="{ Component }">
        <component v-if="Component" :is="Component" :key="$route.fullPath" @refresh="reload" />
        <ExpenseForm v-else :key="'default-new'" @refresh="reload" />
      </router-view>
    </div>

    <!-- Delete confirm -->
    <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/40 backdrop-blur-sm">
      <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl p-6 space-y-4 border border-gray-100 animate-fade-in-up">
        <h3 class="font-bold text-[16px] text-gray-900 tracking-tight">Remove this expense?</h3>
        <p class="text-[13px] text-gray-500"><strong>{{ deleteTarget.description }}</strong> will be permanently deleted.</p>
        <div class="flex gap-2.5 mt-2">
          <button @click="deleteTarget = null" class="flex-1 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-xl text-[13px] font-bold text-gray-700 transition-colors" :disabled="deleting">Cancel</button>
          <button @click="confirmDelete" :disabled="deleting" class="flex-1 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white shadow-md shadow-red-600/20 border border-red-600 rounded-xl text-[13px] font-bold transition-all disabled:opacity-60 flex justify-center items-center">
            {{ deleting ? 'Removing…' : 'Yes, Remove' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
