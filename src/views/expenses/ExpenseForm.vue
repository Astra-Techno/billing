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
const saved         = ref(false)
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
      emit('refresh')
      toast.success('Expense updated.')
      saved.value = true
    } else {
      const res = await task('Expense', 'create', form.value)
      emit('refresh')
      toast.success('Expense recorded.')
      const newId = res.data?.data?.expense_id
      router.push(newId ? `/expenses/${newId}/edit` : '/expenses')
    }
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
  <div class="inv-shell">
    <!-- Toolbar -->
    <div class="inv-toolbar">
      <div class="flex items-center gap-3 min-w-0">
        <button type="button" @click="router.back()" class="inv-back-btn">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <h1 class="inv-page-title">{{ isEdit ? 'Edit Expense' : 'Record Expense' }}</h1>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" @click="router.back()" class="inv-btn-secondary hidden sm:inline-flex">Cancel</button>
        <button type="submit" form="expense-form" class="inv-btn-primary" :disabled="saving">
          {{ saving ? 'Saving…' : saved ? 'Saved ✓' : 'Save Expense' }}
        </button>
      </div>
    </div>

    <!-- Body -->
    <div class="inv-body">
      <div v-if="loading" class="flex items-center justify-center p-12 text-gray-400">
        Loading expense details...
      </div>
      
      <form v-else id="expense-form" @submit.prevent="save" @input="saved = false" class="inv-layout">
        <!-- Main Column -->
        <div class="inv-main">
          <div v-if="error" class="text-sm text-danger-600 bg-danger-50 border border-danger-100 rounded-xl px-4 py-3 font-medium">
            {{ error }}
          </div>

          <!-- Expense Details Card -->
          <div class="inv-card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wider mb-2">Expense Details</h2>
            
            <div>
              <label class="inv-label">What did you spend on? *</label>
              <input v-model="form.description" type="text" class="inv-input font-medium !bg-white" required placeholder="e.g. Office rent, Stock purchase, Courier…" />
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label class="inv-label">Total Amount (₹) *</label>
                <div class="relative mt-1">
                  <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₹</span>
                  <input v-model="form.total_amount" type="number" min="0" step="0.01" required class="inv-input pl-7 font-semibold !bg-white" placeholder="0.00" />
                </div>
              </div>
              
              <div>
                <label class="inv-label">Date *</label>
                <input v-model="form.expense_date" type="date" required class="inv-input mt-1 !bg-white" />
              </div>
            </div>
          </div>

          <!-- Vendor & Payment Card -->
          <div class="inv-card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wider mb-2">Vendor & Payment</h2>
            
            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label class="inv-label">Payment Method</label>
                <select v-model="form.method" class="inv-select mt-1 !bg-white">
                  <option value="cash">Cash</option>
                  <option value="upi">UPI / PhonePe / GPay</option>
                  <option value="neft">Bank Transfer (NEFT)</option>
                  <option value="cheque">Cheque</option>
                  <option value="card">Card</option>
                  <option value="other">Other</option>
                </select>
              </div>

              <div>
                <label class="inv-label">Paid To <span class="text-gray-400 font-normal">(vendor name)</span></label>
                <input v-model="form.vendor_name" type="text" class="inv-input mt-1 !bg-white" placeholder="e.g. Sharma Stationery" />
              </div>

              <div>
                <label class="inv-label">Reference / Receipt No.</label>
                <input v-model="form.reference" type="text" class="inv-input mt-1 !bg-white" placeholder="UTR / cheque no." />
              </div>

              <div>
                <label class="inv-label">GST Paid (₹)</label>
                <div class="relative mt-1">
                  <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₹</span>
                  <input v-model="form.gst_amount" type="number" min="0" step="0.01" class="inv-input pl-7 !bg-white" placeholder="0.00" />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Sidebar Column -->
        <div class="inv-sidebar">
          <!-- Categorization Card -->
          <div class="inv-card p-5 space-y-4">
            <div class="flex items-center justify-between mb-1">
              <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wider">Categorization</h2>
              <button type="button" @click="showAddCat = !showAddCat" class="text-xs text-primary-600 hover:text-primary-700 font-semibold flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                New
              </button>
            </div>
            
            <div>
              <label class="inv-label">Category</label>
              <select v-model="form.category_id" class="inv-select mt-1 !bg-white">
                <option value="">General</option>
                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>

            <div v-if="showAddCat" class="animate-fade-in-up flex gap-2 pt-1">
              <input v-model="newCatName" type="text" class="inv-input flex-1 py-1.5 text-sm !bg-white" placeholder="Category name…" @keyup.enter="addCategory" />
              <button type="button" @click="addCategory" :disabled="addingCat" class="inv-btn-primary text-xs px-3.5 py-1.5 shrink-0">{{ addingCat ? '…' : 'Add' }}</button>
            </div>
          </div>

          <!-- Notes Card -->
          <div class="inv-card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wider mb-2">Notes</h2>
            <div>
              <textarea v-model="form.notes" class="inv-textarea !bg-white" rows="4" placeholder="Any additional details…"></textarea>
            </div>
          </div>
        </div>
      </form>
    </div>

    <!-- Mobile Footer Actions -->
    <div class="form-footer-mobile lg:hidden">
      <button type="button" @click="router.back()" class="inv-btn-secondary flex-1 justify-center">Cancel</button>
      <button type="submit" form="expense-form" class="inv-btn-primary flex-1 justify-center" :disabled="saving">
        {{ saving ? 'Saving…' : saved ? 'Saved ✓' : 'Save Expense' }}
      </button>
    </div>
  </div>
</template>
