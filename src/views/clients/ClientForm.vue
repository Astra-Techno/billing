<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { task, item, all } from '../../api'

const router = useRouter()
const route  = useRoute()
const emit   = defineEmits(['refresh'])

const states  = ref([])
const loading = ref(false)
const error   = ref('')
const isEdit  = computed(() => !!route.params.id)

const form = ref({
  type: 'business', name: '', company: '', gstin: '', pan: '',
  email: '', mobile: '', phone: '',
  address_line1: '', address_line2: '', city: '', state_id: '', pincode: '',
  credit_days: 30, notes: '',
  contact_name: '', contact_designation: '', contact_mobile: '', contact_email: '',
})

onMounted(async () => {
  const [statesRes] = await Promise.all([all('IndianState')])
  states.value = statesRes.data.data || []

  if (isEdit.value) {
    try {
      const res = await item('Client', { id: route.params.id })
      const c = res.data?.data
      if (c) {
        Object.keys(form.value).forEach(k => { if (c[k] !== undefined) form.value[k] = c[k] })
      }
    } catch {
      error.value = 'Could not load customer details. Please try again.'
    }
  }
})

async function submit() {
  error.value = ''
  loading.value = true
  try {
    if (isEdit.value) {
      await task('Client', 'update', { ...form.value, id: route.params.id })
      emit('refresh')
      router.push(`/clients/${route.params.id}`)
    } else {
      await task('Client', 'create', form.value)
      emit('refresh')
      router.push('/clients')
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save. Please check the details and try again.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="max-w-2xl mx-auto space-y-5">
    <div class="flex items-center gap-3">
      <button @click="router.back()" class="p-2 rounded-lg hover:bg-gray-100">
        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      </button>
      <div>
        <h1 class="page-title">{{ isEdit ? 'Edit Customer' : 'Add New Customer' }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">{{ isEdit ? 'Update customer information' : 'Save your customer\'s details for quick billing' }}</p>
      </div>
    </div>

    <form @submit.prevent="submit" class="space-y-5">

      <!-- Type toggle -->
      <div class="card card-body">
        <label class="form-label">What type of customer is this?</label>
        <div class="flex gap-3 mt-1">
          <button type="button" @click="form.type='business'"
            class="flex-1 py-3 rounded-xl text-sm font-medium border-2 transition"
            :class="form.type==='business' ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'">
            Business / Shop
          </button>
          <button type="button" @click="form.type='individual'"
            class="flex-1 py-3 rounded-xl text-sm font-medium border-2 transition"
            :class="form.type==='individual' ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'">
            Individual Person
          </button>
        </div>
      </div>

      <!-- Basic info -->
      <div class="card card-body space-y-4">
        <h2 class="section-title">Basic Details</h2>
        <div class="grid sm:grid-cols-2 gap-4">
          <div class="sm:col-span-2">
            <label class="form-label">{{ form.type === 'business' ? 'Business Name' : 'Full Name' }} *</label>
            <input v-model="form.name" type="text" class="form-input" required :placeholder="form.type === 'business' ? 'e.g. Sharma Traders' : 'e.g. Rajesh Kumar'" />
          </div>
          <div v-if="form.type === 'business'">
            <label class="form-label">Owner / Contact Person</label>
            <input v-model="form.company" type="text" class="form-input" placeholder="Owner or manager name" />
          </div>
          <div>
            <label class="form-label">Mobile Number *</label>
            <input v-model="form.mobile" type="tel" class="form-input" placeholder="9876543210" maxlength="10" />
          </div>
          <div>
            <label class="form-label">Email Address</label>
            <input v-model="form.email" type="email" class="form-input" placeholder="email@example.com" />
          </div>
          <div>
            <label class="form-label">GST Number <span class="text-gray-400 font-normal">(if registered)</span></label>
            <input v-model="form.gstin" type="text" class="form-input font-mono uppercase" placeholder="e.g. 27AABCU9603R1Z6" maxlength="15" />
          </div>
          <div>
            <label class="form-label">PAN Number</label>
            <input v-model="form.pan" type="text" class="form-input font-mono uppercase" placeholder="e.g. AABCU9603R" maxlength="10" />
          </div>
          <div>
            <label class="form-label">Payment Terms <span class="text-gray-400 font-normal text-xs">(days to pay)</span></label>
            <div class="flex items-center gap-2">
              <input v-model="form.credit_days" type="number" class="form-input" min="0" max="365" />
              <span class="text-sm text-gray-500 shrink-0">days</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Address -->
      <div class="card card-body space-y-4">
        <h2 class="section-title">Address <span class="text-gray-400 font-normal text-sm">(for bills)</span></h2>
        <div class="grid sm:grid-cols-2 gap-4">
          <div class="sm:col-span-2">
            <label class="form-label">Street / Shop Number</label>
            <input v-model="form.address_line1" type="text" class="form-input" placeholder="e.g. Shop No. 12, Main Market" />
          </div>
          <div class="sm:col-span-2">
            <label class="form-label">Area / Locality</label>
            <input v-model="form.address_line2" type="text" class="form-input" placeholder="e.g. Near Bus Stand, Gandhi Road" />
          </div>
          <div>
            <label class="form-label">City</label>
            <input v-model="form.city" type="text" class="form-input" placeholder="Mumbai" />
          </div>
          <div>
            <label class="form-label">PIN Code</label>
            <input v-model="form.pincode" type="text" class="form-input" placeholder="400001" maxlength="6" />
          </div>
          <div class="sm:col-span-2">
            <label class="form-label">State</label>
            <select v-model="form.state_id" class="form-select">
              <option value="">Select State</option>
              <option v-for="s in states" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Primary contact (optional) -->
      <div class="card card-body space-y-4">
        <div>
          <h2 class="section-title mb-0">Contact Person <span class="text-gray-400 font-normal text-sm">(optional)</span></h2>
          <p class="text-xs text-gray-400 mt-0.5">Add a billing or accounts contact for this customer</p>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
          <div>
            <label class="form-label">Contact Name</label>
            <input v-model="form.contact_name" type="text" class="form-input" placeholder="e.g. Priya Sharma" />
          </div>
          <div>
            <label class="form-label">Role / Designation</label>
            <input v-model="form.contact_designation" type="text" class="form-input" placeholder="e.g. Accounts Manager" />
          </div>
          <div>
            <label class="form-label">Mobile</label>
            <input v-model="form.contact_mobile" type="tel" class="form-input" placeholder="9876543210" />
          </div>
          <div>
            <label class="form-label">Email</label>
            <input v-model="form.contact_email" type="email" class="form-input" placeholder="contact@example.com" />
          </div>
        </div>
      </div>

      <div v-if="error" class="text-sm text-danger-500 bg-danger-50 rounded-lg px-4 py-3">{{ error }}</div>

      <div class="flex gap-3 pb-6">
        <button type="button" @click="router.back()" class="btn-outline flex-1">Cancel</button>
        <button type="submit" class="btn-primary flex-1" :disabled="loading">
          {{ loading ? 'Saving…' : (isEdit ? 'Save Changes' : 'Add Customer') }}
        </button>
      </div>
    </form>
  </div>
</template>
