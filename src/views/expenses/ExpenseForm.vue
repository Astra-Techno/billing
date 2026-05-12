<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { item, task, all } from '../../api'
import { today } from '../../utils/date'
import { useToast } from '../../composables/useToast'
import HelpIcon from '../../components/HelpIcon.vue'

const router = useRouter()
const route  = useRoute()
const emit   = defineEmits(['refresh'])

const toast         = useToast()
const categories    = ref([])
const loading       = ref(false)
const saving        = ref(false)
const error         = ref('')
const newCatName    = ref('')
const addingCat     = ref(false)
const showAddCat    = ref(false)

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
  notes: '',
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
          notes:        exp.notes        || '',
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
      toast.success('Expense updated.')
    } else {
      await task('Expense', 'create', form.value)
      toast.success('Expense recorded.')
    }
    emit('refresh')
    router.push('/expenses')
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save. Please try again.'
  } finally {
    saving.value = false
  }
}

async function addCategory() {
  if (!newCatName.value.trim()) return
  addingCat.value = true
  try {
    const res = await task('Expense', 'addCategory', { name: newCatName.value.trim() })
    const catId = res.data?.data?.category_id
    const cRes = await all('ExpenseCategory')
    categories.value = cRes.data?.data || []
    if (catId) form.value.category_id = catId
    newCatName.value = ''
    showAddCat.value = false
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not add category.'
  } finally { addingCat.value = false }
}

onMounted(load)
</script>

<template>
  <div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center gap-3">
      <button @click="router.back()" class="p-2 rounded-xl hover:bg-gray-100 shrink-0 transition">
        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      </button>
      <div>
        <h1 class="page-title">{{ isEdit ? 'Edit Expense' : 'Record Expense' }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ isEdit ? 'Update expense details' : 'Keep track of your business spending' }}</p>
      </div>
    </div>

    <div v-if="loading" class="card p-12 text-center text-gray-400">Loading...</div>
    
    <form v-else @submit.prevent="save" class="space-y-6 animate-fade-in-up">
      <div v-if="error" class="text-sm text-danger-600 bg-danger-50 border border-danger-100 rounded-xl px-4 py-3 font-medium">{{ error }}</div>

      <div class="card card-body space-y-5">
        <h2 class="section-title mb-0">Expense Details</h2>
        <div>
          <label class="form-label">What did you spend on? *</label>
          <input v-model="form.description" type="text" class="form-input text-lg font-medium" placeholder="e.g. Office rent, Stock purchase, Courier…" />
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Total Amount (₹) *</label>
            <input v-model="form.total_amount" type="number" min="0" step="0.01" class="form-input text-lg font-semibold" placeholder="0.00" />
          </div>
          <div>
            <label class="form-label">Date *</label>
            <input v-model="form.expense_date" type="date" class="form-input" />
          </div>
        </div>
      </div>

      <div class="grid sm:grid-cols-2 gap-4">
        <div class="card card-body space-y-4">
          <h2 class="section-title mb-0">Categorization</h2>
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="form-label mb-0">Category</label>
              <button type="button" @click="showAddCat = !showAddCat" class="text-xs text-primary-600 hover:text-primary-700 font-semibold flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                New
              </button>
            </div>
            <select v-model="form.category_id" class="form-select">
              <option value="">General</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
            <div v-if="showAddCat" class="mt-2 flex gap-2">
              <input v-model="newCatName" type="text" class="form-input flex-1 py-1.5 text-sm" placeholder="Category name…" @keyup.enter="addCategory" />
              <button type="button" @click="addCategory" :disabled="addingCat" class="btn-primary text-xs px-3 py-1.5">{{ addingCat ? '…' : 'Add' }}</button>
            </div>
          </div>
          <div>
            <label class="form-label">GST Paid (₹)</label>
            <input v-model="form.gst_amount" type="number" min="0" step="0.01" class="form-input" placeholder="0.00" />
          </div>
        </div>

        <div class="card card-body space-y-4">
          <h2 class="section-title mb-0">Payment</h2>
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
            <label class="form-label">Paid To <span class="text-gray-400 font-normal">(vendor name)</span></label>
            <input v-model="form.vendor_name" type="text" class="form-input" placeholder="e.g. Sharma Stationery" />
          </div>
        </div>
      </div>

      <div class="card card-body space-y-4">
        <div>
          <label class="form-label">Reference / Receipt No.</label>
          <input v-model="form.reference" type="text" class="form-input" placeholder="UTR / cheque no." />
        </div>
        <div>
          <label class="form-label">Notes <span class="text-gray-400 font-normal">(optional)</span></label>
          <textarea v-model="form.notes" class="form-textarea" rows="2" placeholder="Any additional details…"></textarea>
        </div>
      </div>

      <div class="flex gap-3 pb-6">
        <button type="button" @click="router.back()" class="btn-outline flex-1">Cancel</button>
        <button type="submit" class="btn-primary flex-1" :disabled="saving">
          {{ saving ? 'Saving…' : 'Save Expense' }}
        </button>
      </div>

    </form>
  </div>
</template>
