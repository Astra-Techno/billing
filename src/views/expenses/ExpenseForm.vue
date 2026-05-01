<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { item, task, all } from '../../api'
import { today } from '../../utils/date'
import HelpIcon from '../../components/HelpIcon.vue'

const router = useRouter()
const route  = useRoute()
const emit   = defineEmits(['refresh'])

const categories = ref([])
const loading    = ref(false)
const saving     = ref(false)
const error      = ref('')

const isEdit = route.params.id && route.params.id !== 'new'
const expenseId = isEdit ? route.params.id : null

const form = ref({
  description: '',
  vendor_name: '',
  category_id: '',
  total_amount: '',
  gst_amount: 0,
  expense_date: today(),
  method: 'cash',
  reference: '',
})

async function load() {
  loading.value = true
  try {
    const cRes = await all('ExpenseCategory')
    categories.value = cRes.data?.data || []

    if (isEdit) {
      const eRes = await item('Expense', { id: expenseId })
      const exp = eRes.data?.data
      if (exp) {
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
      }
    }
  } catch (err) {
    error.value = 'Failed to load expense details.'
  }
  loading.value = false
}

async function save() {
  error.value = ''
  if (!form.value.description) return (error.value = 'Description is required.')
  if (!form.value.total_amount) return (error.value = 'Amount is required.')
  
  saving.value = true
  try {
    if (isEdit) {
      await task('Expense', 'update', { ...form.value, id: expenseId })
    } else {
      await task('Expense', 'create', form.value)
    }
    emit('refresh')
    router.push('/expenses')
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save. Please try again.'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="max-w-3xl mx-auto space-y-6 pb-20">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <button @click="router.push('/expenses')" class="w-10 h-10 rounded-full bg-white shadow-soft flex items-center justify-center text-gray-500 hover:text-gray-900 transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <h1 class="page-title flex items-center gap-2">{{ isEdit ? 'Edit Expense' : 'Record Expense' }} <HelpIcon section="expenses" /></h1>
      </div>
      <div class="flex gap-3">
        <button @click="router.push('/expenses')" class="btn-outline hidden sm:flex">Cancel</button>
        <button @click="save" :disabled="saving || loading" class="btn-primary">
          <svg v-if="saving" class="w-4 h-4 animate-spin inline mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
          {{ saving ? 'Saving…' : 'Save Expense' }}
        </button>
      </div>
    </div>

    <div v-if="loading" class="card p-12 text-center text-gray-400">Loading...</div>
    
    <div v-else class="card p-6 sm:p-8 space-y-6 animate-fade-in-up">
      <div v-if="error" class="text-sm text-danger-500 bg-danger-50 rounded-lg px-4 py-3">{{ error }}</div>

      <div>
        <label class="form-label">What did you spend on? *</label>
        <input v-model="form.description" type="text" class="form-input text-lg font-semibold" placeholder="e.g. Office rent, Stock purchase, Courier…" />
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div>
          <label class="form-label">Total Amount (₹) *</label>
          <input v-model="form.total_amount" type="number" min="0" step="0.01" class="form-input text-lg font-bold text-gray-900" placeholder="0.00" />
        </div>
        <div>
          <label class="form-label">Date *</label>
          <input v-model="form.expense_date" type="date" class="form-input" />
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div>
          <label class="form-label">Category</label>
          <select v-model="form.category_id" class="form-select">
            <option value="">General</option>
            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div>
          <label class="form-label">GST Paid (₹)</label>
          <input v-model="form.gst_amount" type="number" min="0" step="0.01" class="form-input" placeholder="0.00" />
        </div>
      </div>

      <hr class="border-gray-100" />

      <div>
        <label class="form-label">Paid To <span class="text-gray-400 font-normal">(vendor / shop name)</span></label>
        <input v-model="form.vendor_name" type="text" class="form-input" placeholder="e.g. Sharma Stationery, Vodafone" />
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
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

    </div>
  </div>
</template>
