<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { item, list } from '../../api'
import { inr } from '../../utils/currency'
import { fmtDateShort } from '../../utils/date'

const route  = useRoute()
const router = useRouter()

const client   = ref(null)
const invoices = ref([])
const payments = ref([])
const loading  = ref(true)

function fyStart() {
  const now = new Date()
  const year = now.getMonth() >= 3 ? now.getFullYear() : now.getFullYear() - 1
  return `${year}-04-01`
}
function today() { return new Date().toISOString().split('T')[0] }

const fromDate = ref(fyStart())
const toDate   = ref(today())

async function load() {
  loading.value = true
  try {
    const id = route.params.id
    const [cRes, iRes] = await Promise.all([
      item('Client', { id }),
      list('Invoice', { 'filter.client_id': id, sort_by: 'i.issue_date', sort_order: 'asc', limit: 1000 }),
    ])
    client.value   = cRes.data?.data
    invoices.value = iRes.data?.data || []

    // Load payments for each invoice
    const payRes = await Promise.all(
      invoices.value.map(inv => list('Invoice:payments', { invoice_id: inv.id }))
    )
    const allPay = []
    invoices.value.forEach((inv, i) => {
      for (const p of (payRes[i].data?.data || [])) {
        allPay.push({ ...p, invoice_number: inv.number })
      }
    })
    payments.value = allPay
  } catch {}
  loading.value = false
}

onMounted(load)

// Build ledger rows: filter by date range, merge invoices + payments, sort by date
const ledgerRows = computed(() => {
  const rows = []
  const from = fromDate.value
  const to   = toDate.value

  for (const inv of invoices.value) {
    if (inv.issue_date < from || inv.issue_date > to) continue
    rows.push({
      date:   inv.issue_date,
      type:   'Invoice',
      ref:    inv.number,
      debit:  parseFloat(inv.total || 0),
      credit: 0,
      id:     inv.id,
      nav:    `/invoices/${inv.id}`,
    })
  }

  for (const p of payments.value) {
    if (p.payment_date < from || p.payment_date > to) continue
    rows.push({
      date:   p.payment_date,
      type:   'Payment',
      ref:    p.invoice_number + (p.reference ? ' · ' + p.reference : ''),
      debit:  0,
      credit: parseFloat(p.amount || 0),
      id:     'p' + p.id,
      nav:    null,
    })
  }

  rows.sort((a, b) => a.date.localeCompare(b.date))

  let balance = 0
  return rows.map(r => {
    balance += r.debit - r.credit
    return { ...r, balance }
  })
})

const totals = computed(() => ({
  debit:  ledgerRows.value.reduce((s, r) => s + r.debit,  0),
  credit: ledgerRows.value.reduce((s, r) => s + r.credit, 0),
  balance: ledgerRows.value.length ? ledgerRows.value[ledgerRows.value.length - 1].balance : 0,
}))

function exportCSV() {
  const lines = [['Date','Type','Reference','Debit','Credit','Balance'].join(',')]
  for (const r of ledgerRows.value) {
    lines.push([r.date, r.type, `"${r.ref}"`, r.debit.toFixed(2), r.credit.toFixed(2), r.balance.toFixed(2)].join(','))
  }
  const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = Object.assign(document.createElement('a'), {
    href: url,
    download: `${client.value?.name || 'customer'}-statement.csv`,
  })
  a.click()
  URL.revokeObjectURL(url)
}
</script>

<template>
  <div class="max-w-3xl mx-auto space-y-5 pb-10">

    <!-- Back -->
    <div class="flex items-center gap-3 pt-2">
      <button @click="router.push(`/clients/${route.params.id}`)" class="p-2 -ml-2 rounded-full hover:bg-gray-100 transition-colors">
        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
      </button>
      <div class="flex-1 min-w-0">
        <h1 class="page-title truncate">Statement</h1>
        <p v-if="client" class="text-sm text-gray-500 font-medium truncate">{{ client.name }}</p>
      </div>
      <button @click="exportCSV" class="btn bg-gray-50 text-gray-700 hover:bg-gray-100 border border-gray-100 shadow-soft px-4 py-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        CSV
      </button>
    </div>

    <!-- Date Filter -->
    <div class="bg-white rounded-[2rem] shadow-soft p-4 flex flex-wrap gap-3 items-end animate-fade-in-up">
      <div class="flex-1 min-w-[130px]">
        <label class="form-label">From</label>
        <input v-model="fromDate" type="date" class="form-input" @change="load" />
      </div>
      <div class="flex-1 min-w-[130px]">
        <label class="form-label">To</label>
        <input v-model="toDate" type="date" class="form-input" @change="load" />
      </div>
      <button @click="load" class="btn-primary px-6 py-3">Apply</button>
    </div>

    <div v-if="loading" class="flex justify-center p-12">
      <div class="w-8 h-8 border-4 border-primary-100 border-t-primary-600 rounded-full animate-spin"></div>
    </div>

    <template v-else>
      <!-- Summary Cards -->
      <div class="grid grid-cols-3 gap-3 animate-fade-in-up">
        <div class="bg-white rounded-[1.5rem] shadow-soft p-4 text-center">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Invoiced</p>
          <p class="text-xl font-extrabold text-gray-900">{{ inr(totals.debit) }}</p>
        </div>
        <div class="bg-white rounded-[1.5rem] shadow-soft p-4 text-center">
          <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Received</p>
          <p class="text-xl font-extrabold text-emerald-600">{{ inr(totals.credit) }}</p>
        </div>
        <div class="rounded-[1.5rem] p-4 text-center" :class="totals.balance > 0 ? 'bg-red-50' : 'bg-emerald-50'">
          <p class="text-[10px] font-bold uppercase tracking-widest mb-1" :class="totals.balance > 0 ? 'text-red-400' : 'text-emerald-400'">Balance</p>
          <p class="text-xl font-extrabold" :class="totals.balance > 0 ? 'text-red-600' : 'text-emerald-600'">{{ inr(Math.abs(totals.balance)) }}</p>
        </div>
      </div>

      <!-- Ledger Table -->
      <div class="bg-white rounded-[2rem] shadow-soft overflow-hidden animate-fade-in-up anim-delay-75">
        <div class="px-5 py-4 border-b border-gray-100">
          <p class="font-semibold text-gray-800 text-sm">Account Ledger</p>
        </div>

        <div v-if="!ledgerRows.length" class="p-10 text-center">
          <p class="text-gray-500 font-bold">No transactions found</p>
          <p class="text-sm text-gray-400 mt-1">Try changing the date range</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider font-semibold">
              <tr>
                <th class="px-4 py-3 text-left">Date</th>
                <th class="px-4 py-3 text-left">Type</th>
                <th class="px-4 py-3 text-left">Reference</th>
                <th class="px-4 py-3 text-right">Debit</th>
                <th class="px-4 py-3 text-right">Credit</th>
                <th class="px-4 py-3 text-right">Balance</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
              <tr v-for="row in ledgerRows" :key="row.id"
                class="transition-colors"
                :class="row.nav ? 'hover:bg-blue-50/40 cursor-pointer' : ''"
                @click="row.nav ? router.push(row.nav) : null">
                <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ fmtDateShort(row.date) }}</td>
                <td class="px-4 py-3">
                  <span class="text-xs font-bold px-2 py-0.5 rounded-full"
                    :class="row.type === 'Invoice' ? 'bg-blue-50 text-blue-700' : 'bg-emerald-50 text-emerald-700'">
                    {{ row.type }}
                  </span>
                </td>
                <td class="px-4 py-3 font-medium text-gray-700">{{ row.ref }}</td>
                <td class="px-4 py-3 text-right font-semibold" :class="row.debit > 0 ? 'text-gray-900' : 'text-gray-300'">
                  {{ row.debit > 0 ? inr(row.debit) : '—' }}
                </td>
                <td class="px-4 py-3 text-right font-semibold" :class="row.credit > 0 ? 'text-emerald-600' : 'text-gray-300'">
                  {{ row.credit > 0 ? inr(row.credit) : '—' }}
                </td>
                <td class="px-4 py-3 text-right font-bold" :class="row.balance > 0 ? 'text-red-600' : 'text-emerald-600'">
                  {{ inr(Math.abs(row.balance)) }}
                  <span class="text-[10px] font-bold ml-0.5">{{ row.balance > 0 ? 'DR' : 'CR' }}</span>
                </td>
              </tr>
            </tbody>
            <tfoot class="bg-gray-50 font-bold text-sm">
              <tr>
                <td colspan="3" class="px-4 py-3 text-gray-700">Total</td>
                <td class="px-4 py-3 text-right text-gray-900">{{ inr(totals.debit) }}</td>
                <td class="px-4 py-3 text-right text-emerald-600">{{ inr(totals.credit) }}</td>
                <td class="px-4 py-3 text-right" :class="totals.balance > 0 ? 'text-red-600' : 'text-emerald-600'">
                  {{ inr(Math.abs(totals.balance)) }}
                  <span class="text-[10px] ml-0.5">{{ totals.balance > 0 ? 'DR' : 'CR' }}</span>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>
