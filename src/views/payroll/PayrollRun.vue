<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { list, task } from '../../api'
import { inr } from '../../utils/currency'
import { useToast } from '../../composables/useToast'
import { useTour } from '../../composables/useTour'

const { startTour, isTourSeen } = useTour('payroll-run', [
  { target: '[data-tour="payroll-period"]', title: 'Select Period', text: 'Choose the month and year for which you want to run payroll.' },
  { target: '[data-tour="payroll-generate"]', title: 'Generate Payroll', text: 'Creates salary entries for all active staff members for the selected month.' },
  { target: '[data-tour="payroll-table"]', title: 'Payroll Table', text: 'Edit days worked, bonus, and deductions. Changes are auto-saved.' },
  { target: '[data-tour="payroll-pay"]', title: 'Process Payment', text: 'Mark all draft salaries as paid in one click with the chosen payment method.' },
])

const router = useRouter()
const toast  = useToast()

const now   = new Date()
const month = ref(now.getMonth() + 1)   // 1-12
const year  = ref(now.getFullYear())

const months = [
  { value: 1,  label: 'January'   },
  { value: 2,  label: 'February'  },
  { value: 3,  label: 'March'     },
  { value: 4,  label: 'April'     },
  { value: 5,  label: 'May'       },
  { value: 6,  label: 'June'      },
  { value: 7,  label: 'July'      },
  { value: 8,  label: 'August'    },
  { value: 9,  label: 'September' },
  { value: 10, label: 'October'   },
  { value: 11, label: 'November'  },
  { value: 12, label: 'December'  },
]

const years = Array.from({ length: 6 }, (_, i) => now.getFullYear() - 2 + i)

const payroll     = ref([])
const loading     = ref(false)
const generating  = ref(false)
const payingAll   = ref(false)
const error       = ref('')
const payMethod   = ref('cash')
const paidDate    = ref(now.toISOString().split('T')[0])

// Inline edit debounce timers
const updateTimers = {}

async function load() {
  loading.value = true
  error.value   = ''
  try {
    const { data } = await list('Payroll', { month: month.value, year: year.value })
    payroll.value = data.data || []
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to load payroll data.'
  }
  loading.value = false
}

async function generate() {
  generating.value = true
  error.value      = ''
  try {
    await task('Payroll', 'generate', { month: month.value, year: year.value })
    toast.success('Payroll generated.')
    await load()
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to generate payroll.'
  }
  generating.value = false
}

function onFieldChange(row) {
  clearTimeout(updateTimers[row.id])
  updateTimers[row.id] = setTimeout(() => updateRow(row), 600)
}

async function updateRow(row) {
  try {
    await task('Payroll', 'update', {
      id:          row.id,
      days_worked: row.days_worked,
      bonus:       row.bonus,
      deductions:  row.deductions,
    })
  } catch {}
}

function netPay(row) {
  const salary     = parseFloat(row.basic_salary)        || 0
  const bonus      = parseFloat(row.bonus)         || 0
  const deductions = parseFloat(row.deductions)    || 0
  const days       = parseFloat(row.days_worked)   || 0
  const totalDays  = parseFloat(row.working_days)    || 30
  const earned     = totalDays > 0 ? (salary / totalDays) * days : salary
  return earned + bonus - deductions
}

const totalNet = computed(() =>
  payroll.value.reduce((sum, r) => sum + netPay(r), 0)
)

const totalSalary = computed(() =>
  payroll.value.reduce((sum, r) => sum + (parseFloat(r.basic_salary) || 0), 0)
)

async function payAll() {
  if (!payroll.value.length) return
  payingAll.value = true
  error.value     = ''
  try {
    await task('Payroll', 'payAll', {
      month:     month.value,
      year:      year.value,
      method:    payMethod.value,
      paid_date: paidDate.value,
    })
    toast.success('All salaries marked as paid.')
    await load()
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to process payment.'
  }
  payingAll.value = false
}

const hasDraft = computed(() => payroll.value.some(r => r.status === 'draft'))
const allPaid  = computed(() => payroll.value.length > 0 && payroll.value.every(r => r.status === 'paid'))

onMounted(() => {
  load().then(() => {
    setTimeout(() => { if (!isTourSeen()) startTour() }, 800)
  })
})
</script>

<template>
  <div class="inv-shell">
    <!-- Toolbar -->
    <div class="inv-toolbar">
      <div class="flex items-center gap-3 min-w-0">
        <button type="button" @click="router.back()" class="inv-back-btn">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
        </button>
        <h1 class="inv-page-title">Run Payroll
          <button @click="startTour()" class="text-[10px] font-bold text-primary-500 hover:text-primary-700 ml-2" title="Take a tour">Tour</button>
        </h1>
      </div>
    </div>

    <!-- Body -->
    <div class="inv-body">
      <div class="max-w-5xl mx-auto px-4 py-4 space-y-4">

        <!-- Error -->
        <div v-if="error" class="text-sm text-danger-600 bg-danger-50 border border-danger-100 rounded-xl px-4 py-3 font-medium">
          {{ error }}
        </div>

        <!-- Month/Year Selector + Generate -->
        <div class="inv-card p-5" data-tour="payroll-period">
          <div class="flex flex-wrap items-center gap-3">
            <select v-model="month" @change="load"
              class="inv-select w-40 !bg-white">
              <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
            <select v-model="year" @change="load"
              class="inv-select w-28 !bg-white">
              <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
            </select>
            <button @click="generate" :disabled="generating"
              class="inv-btn-primary" data-tour="payroll-generate">
              {{ generating ? 'Generating…' : 'Generate Payroll' }}
            </button>
            <button @click="load" :disabled="loading"
              class="inv-btn-secondary">
              Refresh
            </button>
          </div>
        </div>

        <!-- Payroll Table -->
        <div class="inv-card overflow-hidden" data-tour="payroll-table">
          <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wider">
              {{ months.find(m => m.value === month)?.label }} {{ year }}
            </h2>
            <span v-if="allPaid" class="text-[11px] font-bold text-emerald-600 bg-emerald-50 border border-emerald-100 px-2.5 py-1 rounded-full">All Paid</span>
          </div>

          <!-- Loading skeleton -->
          <div v-if="loading" class="p-6 space-y-3">
            <div v-for="i in 4" :key="i" class="h-10 bg-gray-100 rounded-lg animate-pulse"></div>
          </div>

          <!-- Empty state -->
          <div v-else-if="!payroll.length" class="p-10 text-center">
            <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
              <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
              </svg>
            </div>
            <p class="font-bold text-gray-900 text-[13px]">No payroll data</p>
            <p class="text-[11px] text-gray-500 mt-1">Click "Generate Payroll" to create entries for this month</p>
          </div>

          <!-- Table -->
          <div v-else class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead class="bg-gray-50/70 border-b border-gray-100">
                <tr>
                  <th class="text-left px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Name</th>
                  <th class="text-right px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Salary</th>
                  <th class="text-center px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Days</th>
                  <th class="text-right px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Bonus</th>
                  <th class="text-right px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Deduct</th>
                  <th class="text-right px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Net Pay</th>
                  <th class="text-center px-4 py-3 text-[11px] font-bold text-gray-500 uppercase tracking-wider">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-50">
                <tr v-for="row in payroll" :key="row.id"
                  class="hover:bg-gray-50/40 transition-colors"
                  :class="row.status === 'paid' ? 'opacity-70' : ''">

                  <!-- Name -->
                  <td class="px-4 py-3">
                    <div class="font-semibold text-gray-900">{{ row.staff_name }}</div>
                    <div v-if="row.role" class="text-[11px] text-gray-400">{{ row.role }}</div>
                  </td>

                  <!-- Salary -->
                  <td class="px-4 py-3 text-right font-semibold text-gray-700 tabular-nums">
                    {{ inr(row.basic_salary) }}
                  </td>

                  <!-- Days Worked -->
                  <td class="px-4 py-3 text-center">
                    <input v-if="row.status === 'draft'"
                      v-model="row.days_worked"
                      @input="onFieldChange(row)"
                      type="number" min="0" :max="row.working_days || 31" step="0.5"
                      class="w-16 text-center border border-gray-200 rounded-lg px-2 py-1 text-sm font-semibold focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 bg-white" />
                    <span v-else class="font-semibold text-gray-600">{{ row.days_worked }}</span>
                  </td>

                  <!-- Bonus -->
                  <td class="px-4 py-3 text-right">
                    <input v-if="row.status === 'draft'"
                      v-model="row.bonus"
                      @input="onFieldChange(row)"
                      type="number" min="0" step="0.01"
                      class="w-24 text-right border border-gray-200 rounded-lg px-2 py-1 text-sm font-semibold focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 bg-white" />
                    <span v-else class="font-semibold text-gray-600 tabular-nums">{{ inr(row.bonus || 0) }}</span>
                  </td>

                  <!-- Deductions -->
                  <td class="px-4 py-3 text-right">
                    <input v-if="row.status === 'draft'"
                      v-model="row.deductions"
                      @input="onFieldChange(row)"
                      type="number" min="0" step="0.01"
                      class="w-24 text-right border border-gray-200 rounded-lg px-2 py-1 text-sm font-semibold focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 bg-white" />
                    <span v-else class="font-semibold text-gray-600 tabular-nums">{{ inr(row.deductions || 0) }}</span>
                  </td>

                  <!-- Net Pay -->
                  <td class="px-4 py-3 text-right font-bold text-gray-900 tabular-nums">
                    {{ inr(netPay(row)) }}
                  </td>

                  <!-- Status -->
                  <td class="px-4 py-3 text-center">
                    <span v-if="row.status === 'paid'"
                      class="inline-flex items-center gap-1 text-[10px] font-bold px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-600 border border-emerald-100">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                      </svg>
                      PAID
                    </span>
                    <span v-else
                      class="text-[10px] font-bold px-2.5 py-1 rounded-full bg-amber-50 text-amber-600 border border-amber-100">
                      DRAFT
                    </span>
                  </td>
                </tr>
              </tbody>
              <tfoot class="border-t-2 border-gray-200 bg-gray-50/70">
                <tr>
                  <td class="px-4 py-3 font-bold text-gray-700 text-sm">Total</td>
                  <td class="px-4 py-3 text-right font-bold text-gray-700 tabular-nums">{{ inr(totalSalary) }}</td>
                  <td></td>
                  <td></td>
                  <td></td>
                  <td class="px-4 py-3 text-right font-bold text-gray-900 text-base tabular-nums">{{ inr(totalNet) }}</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <!-- Pay All Actions -->
        <div v-if="hasDraft && payroll.length" class="inv-card p-5" data-tour="payroll-pay">
          <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wider mb-4">Process Payment</h2>
          <div class="flex flex-wrap gap-3 items-end">
            <div>
              <label class="inv-label">Payment Method</label>
              <select v-model="payMethod" class="inv-select mt-1 !bg-white w-40">
                <option value="cash">Cash</option>
                <option value="upi">UPI / GPay</option>
                <option value="neft">Bank Transfer (NEFT)</option>
              </select>
            </div>
            <div>
              <label class="inv-label">Payment Date</label>
              <input v-model="paidDate" type="date" class="inv-input mt-1 !bg-white w-44" />
            </div>
            <button @click="payAll" :disabled="payingAll"
              class="inv-btn-primary">
              {{ payingAll ? 'Processing…' : `Pay All — ${inr(totalNet)}` }}
            </button>
          </div>
        </div>

      </div>
    </div>
  </div>
</template>
