<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { item, task } from '../../api'
import { useToast } from '../../composables/useToast'
import { useFormKeys } from '../../composables/useFormKeys'

const router = useRouter()
const route  = useRoute()
const emit   = defineEmits(['refresh'])
const toast  = useToast()
useFormKeys({ formId: 'staff-form' })

const loading = ref(false)
const saving  = ref(false)
const saved   = ref(false)
const error   = ref('')

const isEdit  = route.params.id && route.params.id !== 'new'
const staffId = isEdit ? route.params.id : null

const form = ref({
  name:           '',
  role:           '',
  mobile:         '',
  monthly_salary: '',
  upi_id:         '',
  bank_name:      '',
  bank_account_no:'',
  bank_ifsc:      '',
  join_date:      '',
})

async function load() {
  if (!isEdit) return
  loading.value = true
  try {
    const res = await item('StaffMember', { id: staffId })
    const s   = res.data?.data
    if (s) {
      form.value = {
        name:            s.name            || '',
        role:            s.role            || '',
        mobile:          s.mobile          || '',
        monthly_salary:  s.monthly_salary  || '',
        upi_id:          s.upi_id          || '',
        bank_name:       s.bank_name       || '',
        bank_account_no: s.bank_account_no || '',
        bank_ifsc:       s.bank_ifsc       || '',
        join_date:       s.join_date       || '',
      }
    }
  } catch {
    error.value = 'Failed to load staff details.'
  }
  loading.value = false
}

async function save() {
  error.value = ''
  if (!form.value.name)           return (error.value = 'Name is required.')
  if (!form.value.monthly_salary) return (error.value = 'Monthly salary is required.')

  saving.value = true
  try {
    if (isEdit) {
      await task('StaffMember', 'update', { ...form.value, id: staffId })
      emit('refresh')
      toast.success('Staff member updated.')
      saved.value = true
    } else {
      const res = await task('StaffMember', 'create', form.value)
      emit('refresh')
      toast.success('Staff member added.')
      const newId = res.data?.data?.staff_id
      router.push(newId ? `/payroll/staff/${newId}/edit` : '/payroll')
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save. Please try again.'
  } finally {
    saving.value = false
  }
}

onMounted(load)
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
        <h1 class="inv-page-title">{{ isEdit ? 'Edit Staff' : 'Add Staff Member' }}</h1>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" @click="router.back()" class="inv-btn-secondary hidden sm:inline-flex">Cancel</button>
        <button type="submit" form="staff-form" class="inv-btn-primary" :disabled="saving" title="Ctrl+Enter">
          {{ saving ? 'Saving…' : saved ? 'Saved ✓' : 'Save Staff' }}
          <kbd v-if="!saving" class="ml-1 opacity-60 text-[10px] font-mono">⌃↵</kbd>
        </button>
      </div>
    </div>

    <!-- Body -->
    <div class="inv-body">
      <div v-if="loading" class="flex items-center justify-center p-12 text-gray-400">
        Loading staff details...
      </div>

      <form v-else id="staff-form" @submit.prevent="save" @input="saved = false" class="inv-layout">
        <!-- Main Column -->
        <div class="inv-main">
          <div v-if="error" class="text-sm text-danger-600 bg-danger-50 border border-danger-100 rounded-xl px-4 py-3 font-medium">
            {{ error }}
          </div>

          <!-- Basic Details -->
          <div class="inv-card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wider mb-2">Staff Details</h2>

            <div>
              <label class="inv-label">Full Name *</label>
              <input v-model="form.name" type="text" class="inv-input font-medium !bg-white" required
                placeholder="e.g. Ramesh Kumar" />
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label class="inv-label">Role / Designation</label>
                <input v-model="form.role" type="text" class="inv-input !bg-white"
                  placeholder="e.g. Delivery Boy, Accountant" />
              </div>
              <div>
                <label class="inv-label">Mobile</label>
                <input v-model="form.mobile" type="tel" class="inv-input !bg-white"
                  placeholder="e.g. 9876543210" />
              </div>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label class="inv-label">Monthly Salary (₹) *</label>
                <div class="relative mt-1">
                  <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₹</span>
                  <input v-model="form.monthly_salary" type="number" min="0" step="0.01" required
                    class="inv-input pl-7 font-semibold !bg-white" placeholder="0.00" />
                </div>
              </div>
              <div>
                <label class="inv-label">Join Date</label>
                <input v-model="form.join_date" type="date" class="inv-input mt-1 !bg-white" />
              </div>
            </div>
          </div>

          <!-- Payment Details -->
          <div class="inv-card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wider mb-2">Payment Details</h2>

            <div>
              <label class="inv-label">UPI ID</label>
              <input v-model="form.upi_id" type="text" class="inv-input !bg-white"
                placeholder="e.g. ramesh@okaxis" />
            </div>

            <div class="grid sm:grid-cols-3 gap-4">
              <div>
                <label class="inv-label">Bank Name</label>
                <input v-model="form.bank_name" type="text" class="inv-input !bg-white"
                  placeholder="State Bank of India" />
              </div>
              <div>
                <label class="inv-label">Account No.</label>
                <input v-model="form.bank_account_no" type="text" class="inv-input !bg-white"
                  placeholder="0000000000000" />
              </div>
              <div>
                <label class="inv-label">IFSC Code</label>
                <input v-model="form.bank_ifsc" type="text" class="inv-input !bg-white"
                  placeholder="SBIN0001234" style="text-transform:uppercase" />
              </div>
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="inv-sidebar">
          <div class="inv-card p-5 space-y-3">
            <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wider mb-2">Summary</h2>
            <div class="text-sm text-gray-500 space-y-2">
              <div class="flex justify-between">
                <span>Monthly Salary</span>
                <span class="font-bold text-gray-800">
                  ₹{{ form.monthly_salary ? Number(form.monthly_salary).toLocaleString('en-IN') : '—' }}
                </span>
              </div>
              <div class="flex justify-between">
                <span>Annual CTC</span>
                <span class="font-semibold text-gray-700">
                  ₹{{ form.monthly_salary ? (Number(form.monthly_salary) * 12).toLocaleString('en-IN') : '—' }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>

    <!-- Mobile Footer -->
    <div class="form-footer-mobile lg:hidden">
      <button type="button" @click="router.back()" class="inv-btn-secondary flex-1 justify-center">Cancel</button>
      <button type="submit" form="staff-form" class="inv-btn-primary flex-1 justify-center" :disabled="saving">
        {{ saving ? 'Saving…' : saved ? 'Saved ✓' : 'Save Staff' }}
      </button>
    </div>
  </div>
</template>
