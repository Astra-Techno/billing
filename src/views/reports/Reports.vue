<script setup>
import { ref, computed, onMounted } from 'vue'
import { list } from '../../api'
import HelpIcon from '../../components/HelpIcon.vue'
import { inr } from '../../utils/currency'
import { fmtDateShort, today } from '../../utils/date'

const loading  = ref(false)
const activeTab = ref('outstanding')

const outstanding    = ref([])
const gstSummary     = ref([])
const gstFrom        = ref(financialYearStart())
const gstTo          = ref(today())
const pnl            = ref(null)
const pnlFrom        = ref(financialYearStart())
const pnlTo          = ref(today())
const expenseReport  = ref([])

function financialYearStart() {
  const now  = new Date()
  const year = now.getMonth() >= 3 ? now.getFullYear() : now.getFullYear() - 1
  return `${year}-04-01`
}

const tabs = [
  { key: 'outstanding', label: 'Outstanding' },
  { key: 'gst',         label: 'GST Summary' },
  { key: 'pnl',         label: 'Profit & Loss' },
  { key: 'expenses',    label: 'Expenses' },
]

async function loadOutstanding() {
  loading.value = true
  try {
    // Load sent + partial + overdue separately to avoid comma-separated filter bug
    const [sentRes, partialRes, overdueRes] = await Promise.all([
      list('Invoice', { 'filter.status': 'sent',    sort_by: 'i.due_date', sort_order: 'asc', limit: 500 }),
      list('Invoice', { 'filter.status': 'partial', sort_by: 'i.due_date', sort_order: 'asc', limit: 500 }),
      list('Invoice', { 'filter.status': 'overdue', sort_by: 'i.due_date', sort_order: 'asc', limit: 500 }),
    ])
    const all = [
      ...(sentRes.data?.data    || []),
      ...(partialRes.data?.data || []),
      ...(overdueRes.data?.data || []),
    ]
    // sort by due_date asc
    outstanding.value = all.sort((a, b) => new Date(a.due_date) - new Date(b.due_date))
  } catch {}
  loading.value = false
}

async function loadGst() {
  loading.value = true
  try {
    const [paidRes, partialRes] = await Promise.all([
      list('Invoice', {
        'filter.status':    'paid',
        'filter.from_date': gstFrom.value,
        'filter.to_date':   gstTo.value,
        limit: 500,
        sort_by: 'i.issue_date',
      }),
      list('Invoice', {
        'filter.status':    'partial',
        'filter.from_date': gstFrom.value,
        'filter.to_date':   gstTo.value,
        limit: 500,
        sort_by: 'i.issue_date',
      }),
    ])
    const all = [
      ...(paidRes.data?.data    || []),
      ...(partialRes.data?.data || []),
    ]
    gstSummary.value = all.sort((a, b) => new Date(a.issue_date) - new Date(b.issue_date))
  } catch {}
  loading.value = false
}

async function loadPnl() {
  loading.value = true
  try {
    const [paidRes, partialRes, expRes] = await Promise.all([
      list('Invoice', { 'filter.status': 'paid',    'filter.from_date': pnlFrom.value, 'filter.to_date': pnlTo.value, limit: 500 }),
      list('Invoice', { 'filter.status': 'partial', 'filter.from_date': pnlFrom.value, 'filter.to_date': pnlTo.value, limit: 500 }),
      list('Expense', { 'filter.from_date': pnlFrom.value, 'filter.to_date': pnlTo.value, limit: 500 }),
    ])
    const invoices = [...(paidRes.data?.data || []), ...(partialRes.data?.data || [])]
    const expenses = expRes.data?.data || []
    const revenue  = invoices.reduce((s, i) => s + parseFloat(i.total || 0), 0)
    const taxColl  = invoices.reduce((s, i) => s + parseFloat(i.tax_total || i.cgst_total || 0) + parseFloat(i.sgst_total || 0) + parseFloat(i.igst_total || 0), 0)
    const expTotal = expenses.reduce((s, e) => s + parseFloat(e.total_amount || 0), 0)
    const gstPaid  = expenses.reduce((s, e) => s + parseFloat(e.gst_amount || 0), 0)
    pnl.value = { revenue, taxColl, expTotal, gstPaid, net: (revenue - taxColl) - (expTotal - gstPaid) }
  } catch {}
  loading.value = false
}

async function loadExpenses() {
  loading.value = true
  try {
    const { data } = await list('Expense', { sort_by: 'e.expense_date', sort_order: 'desc', limit: 500 })
    expenseReport.value = data.data || []
  } catch {}
  loading.value = false
}

const gstTotals = computed(() => {
  const rows = gstSummary.value
  return {
    taxable: rows.reduce((s, r) => s + parseFloat(r.subtotal     || 0), 0),
    cgst:    rows.reduce((s, r) => s + parseFloat(r.cgst_total   || 0), 0),
    sgst:    rows.reduce((s, r) => s + parseFloat(r.sgst_total   || 0), 0),
    igst:    rows.reduce((s, r) => s + parseFloat(r.igst_total   || 0), 0),
    total:   rows.reduce((s, r) => s + parseFloat(r.total        || 0), 0),
  }
})

const outstandingTotal = computed(() =>
  outstanding.value.reduce((s, i) => s + parseFloat(i.amount_due || 0), 0)
)

const expenseByCategory = computed(() => {
  const map = {}
  for (const e of expenseReport.value) {
    const cat = e.category_name || 'Uncategorized'
    map[cat] = (map[cat] || 0) + parseFloat(e.total_amount || 0)
  }
  return Object.entries(map).sort((a, b) => b[1] - a[1])
})

// GST Payable = GST collected on invoices − GST paid on expenses (input credit)
const gstPayable = computed(() => {
  const collected = gstTotals.value.cgst + gstTotals.value.sgst + gstTotals.value.igst
  const inputCredit = expenseReport.value.reduce((s, e) => s + parseFloat(e.gst_amount || 0), 0)
  return { collected, inputCredit, payable: collected - inputCredit }
})

function exportCSV(rows, headers, filename) {
  const lines = [headers.join(',')]
  for (const r of rows) lines.push(r.map(v => `"${String(v ?? '').replace(/"/g, '""')}"`).join(','))
  const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' })
  const url  = URL.createObjectURL(blob)
  const a    = document.createElement('a'); a.href = url; a.download = filename; a.click()
  URL.revokeObjectURL(url)
}

function exportGstCSV() {
  exportCSV(
    gstSummary.value.map(r => [r.number, r.client_name, fmtDateShort(r.issue_date), r.subtotal, r.cgst_total, r.sgst_total, r.igst_total, r.total]),
    ['Invoice No','Client','Date','Taxable','CGST','SGST','IGST','Total'],
    'gst-summary.csv'
  )
}

function exportOutstandingCSV() {
  exportCSV(
    outstanding.value.map(r => [r.number, r.client_name, fmtDateShort(r.due_date), r.total, r.amount_paid, r.amount_due, r.status]),
    ['Invoice No','Client','Due Date','Total','Paid','Balance','Status'],
    'outstanding.csv'
  )
}

function selectTab(key) {
  activeTab.value = key
  if      (key === 'outstanding') loadOutstanding()
  else if (key === 'gst')         loadGst()
  else if (key === 'pnl')         loadPnl()
  else if (key === 'expenses')    loadExpenses()
}

onMounted(() => loadOutstanding())
</script>

<template>
  <div class="space-y-5">
    <h1 class="page-title flex items-center gap-2">Reports <HelpIcon section="reports" /></h1>

    <!-- Tabs -->
    <div class="flex gap-1.5 overflow-x-auto pb-1">
      <button v-for="t in tabs" :key="t.key" @click="selectTab(t.key)"
        class="px-4 py-2 rounded-xl text-sm font-semibold whitespace-nowrap transition-all duration-150 shrink-0"
        :class="activeTab === t.key
          ? 'bg-primary-600 text-white shadow-sm shadow-primary-200'
          : 'bg-white text-gray-500 border border-gray-200 hover:border-gray-300 hover:text-gray-700'">
        {{ t.label }}
      </button>
    </div>

    <div v-if="loading" class="card p-10 text-center text-gray-400 text-sm">Loading…</div>

    <!-- Outstanding -->
    <template v-if="!loading && activeTab === 'outstanding'">
      <div class="card">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <h2 class="section-title mb-0">Bills to Collect</h2>
          <div class="flex items-center gap-3">
            <span class="font-bold text-danger-600">{{ inr(outstandingTotal) }}</span>
            <button v-if="outstanding.length" @click="exportOutstandingCSV"
              class="text-xs px-2.5 py-1 rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 font-medium flex items-center gap-1">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
              CSV
            </button>
          </div>
        </div>
        <div v-if="!outstanding.length" class="p-8 text-center text-gray-400 text-sm">No bills to collect</div>
        <div v-else class="divide-y divide-gray-100">
          <div v-for="inv in outstanding" :key="inv.id" class="flex items-center justify-between px-5 py-3 hover:bg-blue-50/40 transition-colors">
            <div class="min-w-0">
              <p class="font-medium text-gray-800 truncate">{{ inv.client_name }}</p>
              <p class="text-xs text-gray-400 mt-0.5">{{ inv.number }} · Due {{ fmtDateShort(inv.due_date) }}
                <span v-if="inv.status === 'overdue'" class="text-danger-500 font-medium"> · Overdue</span>
              </p>
            </div>
            <div class="ml-4 text-right shrink-0">
              <p class="font-semibold text-danger-600">{{ inr(inv.amount_due) }}</p>
              <p class="text-xs text-gray-400">of {{ inr(inv.total) }}</p>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- GST Summary -->
    <template v-if="!loading && activeTab === 'gst'">
      <div class="card card-body">
        <div class="flex flex-wrap gap-3 items-end">
          <div>
            <label class="form-label">From</label>
            <input v-model="gstFrom" type="date" class="form-input" />
          </div>
          <div>
            <label class="form-label">To</label>
            <input v-model="gstTo" type="date" class="form-input" />
          </div>
          <button @click="loadGst" class="btn-primary">Apply</button>
        </div>
      </div>

      <div class="card">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <h2 class="section-title mb-0">GST Summary</h2>
          <button v-if="gstSummary.length" @click="exportGstCSV"
            class="text-xs px-2.5 py-1 rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 font-medium flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            CSV
          </button>
        </div>
        <div v-if="!gstSummary.length" class="p-8 text-center text-gray-400 text-sm">No data for selected period</div>
        <template v-else>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-5 border-b border-gray-100">
            <div class="bg-gray-50 rounded-xl p-4">
              <p class="text-xs text-gray-500 mb-1">Taxable Amount</p>
              <p class="font-bold text-gray-900">{{ inr(gstTotals.taxable) }}</p>
            </div>
            <div class="bg-blue-50 rounded-xl p-4">
              <p class="text-xs text-gray-500 mb-1">CGST Collected</p>
              <p class="font-bold text-blue-700">{{ inr(gstTotals.cgst) }}</p>
            </div>
            <div class="bg-blue-50 rounded-xl p-4">
              <p class="text-xs text-gray-500 mb-1">SGST Collected</p>
              <p class="font-bold text-blue-700">{{ inr(gstTotals.sgst) }}</p>
            </div>
            <div class="bg-green-50 rounded-xl p-4">
              <p class="text-xs text-gray-500 mb-1">IGST Collected</p>
              <p class="font-bold text-green-700">{{ inr(gstTotals.igst) }}</p>
            </div>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                  <th class="px-4 py-3 text-left">Invoice</th>
                  <th class="px-4 py-3 text-left">Client</th>
                  <th class="px-4 py-3 text-right">Taxable</th>
                  <th class="px-4 py-3 text-right">CGST</th>
                  <th class="px-4 py-3 text-right">SGST</th>
                  <th class="px-4 py-3 text-right">IGST</th>
                  <th class="px-4 py-3 text-right">Total</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="inv in gstSummary" :key="inv.id" class="hover:bg-gray-50">
                  <td class="px-4 py-3 font-medium text-gray-800">{{ inv.number }}</td>
                  <td class="px-4 py-3 text-gray-600">{{ inv.client_name }}</td>
                  <td class="px-4 py-3 text-right">{{ inr(inv.subtotal) }}</td>
                  <td class="px-4 py-3 text-right text-blue-600">{{ inr(inv.cgst_total) }}</td>
                  <td class="px-4 py-3 text-right text-blue-600">{{ inr(inv.sgst_total) }}</td>
                  <td class="px-4 py-3 text-right text-green-600">{{ inr(inv.igst_total) }}</td>
                  <td class="px-4 py-3 text-right font-semibold">{{ inr(inv.total) }}</td>
                </tr>
              </tbody>
              <tfoot class="bg-gray-50 font-semibold">
                <tr>
                  <td colspan="2" class="px-4 py-3 text-gray-700">Total</td>
                  <td class="px-4 py-3 text-right">{{ inr(gstTotals.taxable) }}</td>
                  <td class="px-4 py-3 text-right text-blue-600">{{ inr(gstTotals.cgst) }}</td>
                  <td class="px-4 py-3 text-right text-blue-600">{{ inr(gstTotals.sgst) }}</td>
                  <td class="px-4 py-3 text-right text-green-600">{{ inr(gstTotals.igst) }}</td>
                  <td class="px-4 py-3 text-right">{{ inr(gstTotals.total) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>
        </template>
      </div>
    </template>

    <!-- GST Payable summary card -->
    <template v-if="!loading && activeTab === 'gst' && gstSummary.length">
      <div class="card card-body">
        <h2 class="section-title mb-3">GST Payable Estimate</h2>
        <div class="space-y-2 text-sm">
          <div class="flex justify-between text-gray-600">
            <span>GST Collected (CGST+SGST+IGST)</span>
            <span class="font-semibold text-gray-900">{{ inr(gstPayable.collected) }}</span>
          </div>
          <div class="flex justify-between text-gray-600">
            <span>Input Tax Credit (GST on expenses)</span>
            <span class="text-success-600 font-semibold">− {{ inr(gstPayable.inputCredit) }}</span>
          </div>
          <div class="flex justify-between font-bold text-base border-t border-gray-200 pt-2 mt-1"
            :class="gstPayable.payable >= 0 ? 'text-danger-600' : 'text-success-700'">
            <span>Net GST Payable</span>
            <span>{{ inr(Math.abs(gstPayable.payable)) }} {{ gstPayable.payable < 0 ? '(Credit)' : '' }}</span>
          </div>
        </div>
        <p class="text-xs text-gray-400 mt-3">* Estimate only. Based on paid/partial invoices in the selected period vs all recorded expenses.</p>
      </div>
    </template>

    <!-- P&L -->
    <template v-if="!loading && activeTab === 'pnl'">
      <div class="card card-body">
        <div class="flex flex-wrap gap-3 items-end">
          <div><label class="form-label">From</label><input v-model="pnlFrom" type="date" class="form-input" /></div>
          <div><label class="form-label">To</label><input v-model="pnlTo" type="date" class="form-input" /></div>
          <button @click="loadPnl" class="btn-primary">Apply</button>
        </div>
      </div>
      <div v-if="pnl" class="space-y-4">
        <div class="card card-body">
          <h2 class="section-title">Revenue</h2>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between text-gray-600"><span>Gross Revenue (incl. GST)</span><span class="font-semibold text-gray-900">{{ inr(pnl.revenue) }}</span></div>
            <div class="flex justify-between text-gray-500"><span>GST Collected (liability)</span><span class="text-danger-500">- {{ inr(pnl.taxColl) }}</span></div>
            <div class="flex justify-between font-bold text-gray-900 border-t border-gray-200 pt-2 mt-2"><span>Net Revenue</span><span>{{ inr(pnl.revenue - pnl.taxColl) }}</span></div>
          </div>
        </div>
        <div class="card card-body">
          <h2 class="section-title">Expenses</h2>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between text-gray-600"><span>Total Expenses (incl. GST)</span><span class="font-semibold text-gray-900">{{ inr(pnl.expTotal) }}</span></div>
            <div class="flex justify-between text-gray-500"><span>GST Paid (input credit)</span><span class="text-success-600">- {{ inr(pnl.gstPaid) }}</span></div>
            <div class="flex justify-between font-bold text-gray-900 border-t border-gray-200 pt-2 mt-2"><span>Net Expenses</span><span>{{ inr(pnl.expTotal - pnl.gstPaid) }}</span></div>
          </div>
        </div>
        <div class="card card-body">
          <div class="flex justify-between text-lg font-bold" :class="pnl.net >= 0 ? 'text-success-700' : 'text-danger-600'">
            <span>Net Profit / Loss</span><span>{{ inr(pnl.net) }}</span>
          </div>
        </div>
      </div>
      <div v-else class="card p-8 text-center text-gray-400 text-sm">Click Apply to generate report</div>
    </template>

    <!-- Expenses -->
    <template v-if="!loading && activeTab === 'expenses'">
      <div class="card card-body">
        <h2 class="section-title mb-3">By Category</h2>
        <div v-if="!expenseByCategory.length" class="text-gray-400 text-sm">No expenses recorded</div>
        <div v-else class="space-y-2">
          <div v-for="[cat, amt] in expenseByCategory" :key="cat" class="flex items-center justify-between">
            <span class="text-sm text-gray-700">{{ cat }}</span>
            <span class="font-semibold text-gray-900 text-sm">{{ inr(amt) }}</span>
          </div>
          <div class="flex items-center justify-between border-t border-gray-200 pt-2 mt-2 font-bold text-gray-900">
            <span>Total</span>
            <span>{{ inr(expenseReport.reduce((s, e) => s + parseFloat(e.total_amount || 0), 0)) }}</span>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="divide-y divide-gray-100">
          <div v-for="e in expenseReport" :key="e.id" class="flex items-center justify-between px-5 py-3">
            <div class="min-w-0">
              <p class="font-medium text-gray-800 truncate">{{ e.description }}</p>
              <p class="text-xs text-gray-400 mt-0.5">{{ e.category_name || 'Uncategorized' }} · {{ fmtDateShort(e.expense_date) }}</p>
            </div>
            <div class="ml-4 text-right shrink-0">
              <p class="font-semibold text-gray-900">{{ inr(e.total_amount) }}</p>
              <p v-if="e.gst_amount > 0" class="text-xs text-gray-400">GST: {{ inr(e.gst_amount) }}</p>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
