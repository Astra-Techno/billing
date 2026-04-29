<script setup>
import { ref, onMounted } from 'vue'
import { list, task, all } from '../../api'
import { inr } from '../../utils/currency'
import { fmtDateShort, today } from '../../utils/date'

const expenses     = ref([])
const categories   = ref([])
const loading      = ref(true)
const saving       = ref(false)
const error        = ref('')
const selectedId   = ref(null)   // null | 'new' | expense id
const deleteTarget = ref(null)
const deleting     = ref(false)
const searchQ      = ref('')
const catFilter    = ref('')
const showDatePicker = ref(false)
const filter = ref({ preset: '', from_date: '', to_date: '' })

const blankForm = () => ({
  description: '', vendor_name: '', category_id: '',
  total_amount: '', gst_amount: 0, expense_date: today(), method: 'cash', reference: '',
})
const form = ref(blankForm())

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
  filter.value.preset = p; showDatePicker.value = false
  if (p === 'today') { filter.value.from_date = fmt(now); filter.value.to_date = fmt(now) }
  else if (p === 'week') { const s = new Date(now); s.setDate(now.getDate() - now.getDay() + 1); filter.value.from_date = fmt(s); filter.value.to_date = fmt(now) }
  else if (p === 'month') { filter.value.from_date = fmt(new Date(now.getFullYear(), now.getMonth(), 1)); filter.value.to_date = fmt(now) }
  else if (p === 'last_month') { filter.value.from_date = fmt(new Date(now.getFullYear(), now.getMonth() - 1, 1)); filter.value.to_date = fmt(new Date(now.getFullYear(), now.getMonth(), 0)) }
  else if (p === 'quarter') { const q = Math.floor(now.getMonth() / 3); filter.value.from_date = fmt(new Date(now.getFullYear(), q * 3, 1)); filter.value.to_date = fmt(now) }
  else if (p === 'year') { filter.value.from_date = fmt(new Date(now.getFullYear(), 0, 1)); filter.value.to_date = fmt(now) }
  load()
}
function clearDate() { filter.value.preset = ''; filter.value.from_date = ''; filter.value.to_date = ''; showDatePicker.value = false; load() }
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

// ── Open add / edit ───────────────────────────────────────────────────────────
function openAdd() { form.value = blankForm(); error.value = ''; selectedId.value = 'new' }

function openEdit(exp) {
  form.value = {
    description:  exp.description,
    vendor_name:  exp.vendor_name  || '',
    category_id:  exp.category_id  || '',
    total_amount: exp.total_amount,
    gst_amount:   exp.gst_amount   || 0,
    expense_date: exp.expense_date,
    method:       exp.method       || 'cash',
    reference:    exp.reference    || '',
  }
  error.value = ''
  selectedId.value = exp.id
}

// ── Save / delete ─────────────────────────────────────────────────────────────
async function save() {
  error.value = ''
  if (!form.value.description) return (error.value = 'Description is required.')
  if (!form.value.total_amount) return (error.value = 'Amount is required.')
  saving.value = true
  try {
    if (selectedId.value && selectedId.value !== 'new') {
      await task('Expense', 'update', { ...form.value, id: selectedId.value })
    } else {
      await task('Expense', 'create', form.value)
    }
    selectedId.value = null
    await load()
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save. Please try again.'
  } finally { saving.value = false }
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
  <div class="lg:flex lg:h-full lg:-mx-8 lg:-mt-5">

    <!-- ── LEFT: 40% list ──────────────────────────────────────────────────── -->
    <div class="lg:w-2/5 lg:flex lg:flex-col lg:border-r border-gray-200 lg:overflow-hidden"
         :class="selectedId ? 'hidden lg:flex' : ''">

      <div class="lg:px-5 lg:pt-5 pb-3 lg:border-b lg:border-gray-100 space-y-3">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="page-title">Expenses</h1>
            <p class="text-xs text-gray-400 mt-0.5">Track your business spending</p>
          </div>
          <button @click="openAdd" class="btn-primary text-sm py-2 px-4">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            Record
          </button>
        </div>

        <!-- Category filter -->
        <div class="flex gap-1 overflow-x-auto pb-1 no-scrollbar">
          <button @click="catFilter = ''"
            class="px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap shrink-0 transition-all"
            :class="catFilter === '' ? 'bg-primary-600 text-white' : 'bg-white text-gray-500 border border-gray-200 hover:border-gray-300'">
            All
          </button>
          <button v-for="c in categories" :key="c.id" @click="catFilter = c.id"
            class="px-3 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap shrink-0 transition-all"
            :class="catFilter == c.id ? 'bg-primary-600 text-white' : 'bg-white text-gray-500 border border-gray-200 hover:border-gray-300'">
            {{ c.name }}
          </button>
        </div>

        <!-- Search -->
        <div class="relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input v-model="searchQ" type="text" class="form-input pl-9 py-2 text-sm" placeholder="Search by description or vendor…" />
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
          <div v-if="loading" class="p-10 text-center text-gray-400 text-sm">Loading…</div>
          <div v-else-if="!filteredExpenses().length" class="p-10 text-center">
            <div class="w-14 h-14 rounded-2xl bg-orange-50 flex items-center justify-center mx-auto mb-3">
              <svg class="w-7 h-7 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            </div>
            <p class="font-semibold text-gray-700 text-sm">No expenses found</p>
            <p class="text-xs text-gray-400 mt-1">Record rent, purchases, travel and other costs</p>
            <button @click="openAdd" class="btn-primary btn-sm mt-3">Record First Expense</button>
          </div>
          <div v-else class="divide-y divide-gray-50">
            <div v-for="e in filteredExpenses()" :key="e.id"
              class="flex items-center gap-3 px-4 py-3.5 hover:bg-blue-50/40 cursor-pointer transition-colors group"
              :class="selectedId == e.id ? 'bg-primary-50 border-l-2 border-primary-500' : ''"
              @click="openEdit(e)">

              <!-- Category icon -->
              <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 text-xs font-bold"
                :class="methodColors[e.method] || 'bg-gray-100 text-gray-600'">
                {{ methodLabel(e.method).charAt(0) }}
              </div>

              <div class="flex-1 min-w-0">
                <p class="font-semibold text-gray-800 text-sm truncate group-hover:text-primary-700">{{ e.description }}</p>
                <p class="text-[11px] text-gray-400 mt-0.5 truncate">
                  {{ e.category_name || 'General' }} · {{ fmtDateShort(e.expense_date) }}
                  <span v-if="e.vendor_name"> · {{ e.vendor_name }}</span>
                </p>
              </div>

              <div class="text-right shrink-0">
                <p class="font-bold text-gray-900 text-sm">{{ inr(e.total_amount) }}</p>
                <p v-if="e.gst_amount > 0" class="text-[11px] text-gray-400">GST {{ inr(e.gst_amount) }}</p>
                <p v-else class="text-[11px]" :class="methodColors[e.method] || 'text-gray-400'">{{ methodLabel(e.method) }}</p>
              </div>

              <svg class="w-3.5 h-3.5 text-gray-300 shrink-0 group-hover:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── RIGHT: 60% add/edit panel ──────────────────────────────────────── -->
    <div class="hidden lg:flex lg:flex-1 lg:flex-col lg:overflow-y-auto bg-slate-50">

      <!-- Placeholder -->
      <div v-if="!selectedId" class="flex-1 flex flex-col items-center justify-center text-center gap-3 text-gray-400 p-10">
        <div class="w-20 h-20 rounded-3xl bg-white border-2 border-dashed border-gray-200 flex items-center justify-center">
          <svg class="w-9 h-9 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        </div>
        <div>
          <p class="font-semibold text-gray-500">Select an expense to edit</p>
          <p class="text-sm mt-0.5">or record a new one</p>
        </div>
        <button @click="openAdd" class="btn-primary mt-2">+ Record Expense</button>
      </div>

      <!-- Form panel -->
      <div v-else class="flex-1 overflow-y-auto">
        <div class="p-4 border-b border-gray-200 bg-white flex items-center gap-3 sticky top-0 z-10">
          <button @click="selectedId = null" class="text-gray-400 hover:text-gray-700 p-1 rounded-lg hover:bg-gray-100">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
          </button>
          <span class="text-sm font-semibold text-gray-700">{{ selectedId === 'new' ? 'Record Expense' : 'Edit Expense' }}</span>
          <button v-if="selectedId !== 'new'" @click="deleteTarget = expenses.find(e => e.id == selectedId)"
            class="ml-auto text-xs text-red-500 hover:text-red-700 flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            Delete
          </button>
        </div>

        <div class="p-5 space-y-4 max-w-lg">

          <div>
            <label class="form-label">What did you spend on? *</label>
            <input v-model="form.description" type="text" class="form-input" placeholder="e.g. Office rent, Stock purchase, Courier…" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="form-label">Category</label>
              <select v-model="form.category_id" class="form-select">
                <option value="">General</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div>
              <label class="form-label">Date *</label>
              <input v-model="form.expense_date" type="date" class="form-input" />
            </div>
            <div>
              <label class="form-label">Total Amount (₹) *</label>
              <input v-model="form.total_amount" type="number" min="0" step="0.01" class="form-input" placeholder="0.00" />
            </div>
            <div>
              <label class="form-label">GST Paid (₹)</label>
              <input v-model="form.gst_amount" type="number" min="0" step="0.01" class="form-input" placeholder="0.00" />
            </div>
          </div>

          <div>
            <label class="form-label">Paid To <span class="text-gray-400 font-normal">(vendor / shop name)</span></label>
            <input v-model="form.vendor_name" type="text" class="form-input" placeholder="e.g. Sharma Stationery, Vodafone" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="form-label">Payment Method</label>
              <select v-model="form.method" class="form-select">
                <option value="cash">Cash</option>
                <option value="upi">UPI / PhonePe / GPay</option>
                <option value="neft">Bank Transfer (NEFT)</option>
                <option value="cheque">Cheque</option>
                <option value="card">Card</option>
                <option value="other">Other</option>
              </select>
            </div>
            <div>
              <label class="form-label">Reference / Receipt No.</label>
              <input v-model="form.reference" type="text" class="form-input" placeholder="UTR / cheque no." />
            </div>
          </div>

          <div v-if="error" class="text-sm text-danger-500 bg-danger-50 rounded-lg px-3 py-2">{{ error }}</div>

          <div class="flex gap-3 pt-2">
            <button @click="selectedId = null" class="btn-outline flex-1">Cancel</button>
            <button @click="save" :disabled="saving" class="btn-primary flex-1">
              <svg v-if="saving" class="w-4 h-4 animate-spin inline mr-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
              {{ saving ? 'Saving…' : selectedId === 'new' ? 'Record Expense' : 'Save Changes' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Mobile modal -->
    <div v-if="selectedId" class="lg:hidden fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/40">
      <div class="bg-white rounded-2xl w-full max-w-md shadow-xl">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <h3 class="font-semibold text-gray-800">{{ selectedId === 'new' ? 'Record Expense' : 'Edit Expense' }}</h3>
          <button @click="selectedId = null" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
        <div class="p-5 space-y-4">
          <div><label class="form-label">Description *</label><input v-model="form.description" type="text" class="form-input" placeholder="e.g. Office rent, Stock purchase…" /></div>
          <div class="grid grid-cols-2 gap-3">
            <div><label class="form-label">Category</label><select v-model="form.category_id" class="form-select"><option value="">General</option><option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option></select></div>
            <div><label class="form-label">Date *</label><input v-model="form.expense_date" type="date" class="form-input" /></div>
            <div><label class="form-label">Amount (₹) *</label><input v-model="form.total_amount" type="number" min="0" step="0.01" class="form-input" placeholder="0.00" /></div>
            <div><label class="form-label">GST Paid (₹)</label><input v-model="form.gst_amount" type="number" min="0" step="0.01" class="form-input" placeholder="0.00" /></div>
          </div>
          <div><label class="form-label">Vendor / Paid To</label><input v-model="form.vendor_name" type="text" class="form-input" placeholder="Vendor name" /></div>
          <div class="grid grid-cols-2 gap-3">
            <div><label class="form-label">Payment Method</label><select v-model="form.method" class="form-select"><option value="cash">Cash</option><option value="upi">UPI</option><option value="neft">NEFT</option><option value="cheque">Cheque</option><option value="card">Card</option><option value="other">Other</option></select></div>
            <div><label class="form-label">Reference No.</label><input v-model="form.reference" type="text" class="form-input" placeholder="UTR / receipt" /></div>
          </div>
          <div v-if="error" class="text-sm text-danger-500 bg-danger-50 rounded-lg px-3 py-2">{{ error }}</div>
        </div>
        <div class="px-5 pb-5 flex gap-3">
          <button @click="selectedId = null" class="btn-outline flex-1">Cancel</button>
          <button @click="save" :disabled="saving" class="btn-primary flex-1">{{ saving ? 'Saving…' : selectedId === 'new' ? 'Record' : 'Save' }}</button>
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
</template>
