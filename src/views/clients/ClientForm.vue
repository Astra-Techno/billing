<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { task, item, all } from '../../api'
import { useToast } from '../../composables/useToast'
import { useBusinessStore } from '../../stores/business'

const showMore = ref(false)

const router        = useRouter()
const route         = useRoute()
const emit          = defineEmits(['refresh'])
const toast         = useToast()
const businessStore = useBusinessStore()

const states     = ref([])
const loading    = ref(false)
const error      = ref('')
const gstinError = ref('')

const GSTIN_RE = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/

function validateGstin() {
  const v = (form.value.gstin || '').trim().toUpperCase()
  form.value.gstin = v
  if (!v) { gstinError.value = ''; return }
  gstinError.value = GSTIN_RE.test(v) ? '' : 'Invalid GSTIN format. Example: 27AABCU9603R1Z6'
}
const isEdit  = computed(() => !!route.params.id)

const form = ref({
  type: 'business', name: '', company: '', gstin: '', pan: '',
  email: '', mobile: '', phone: '',
  address_line1: '', address_line2: '', city: '', state_id: '', pincode: '',
  credit_days: 30, notes: '',
  contact_name: '', contact_designation: '', contact_mobile: '', contact_email: '',
})

onMounted(async () => {
  const [statesRes, bizRes] = await Promise.all([all('IndianState'), item('Business')])
  states.value = statesRes.data.data || []

  if (!isEdit.value) {
    const sid = bizRes.data?.data?.state_id
    if (sid) businessStore.setStateId(sid)
    form.value.state_id = businessStore.stateId || ''
  }

  if (isEdit.value) {
    try {
      const res = await item('Client', { id: route.params.id })
      const c = res.data?.data
      if (c) {
        Object.keys(form.value).forEach(k => { if (c[k] !== undefined) form.value[k] = c[k] })
        // Show more details if any optional fields are filled
        if (c.email || c.gstin || c.pan || c.address_line1 || c.city || c.contact_name) showMore.value = true
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
      toast.success('Customer updated.')
      router.push(`/clients/${route.params.id}`)
    } else {
      await task('Client', 'create', form.value)
      emit('refresh')
      toast.success('Customer added.')
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
  <div class="inv-shell">
    <!-- Toolbar -->
    <div class="inv-toolbar">
      <div class="flex items-center gap-3 min-w-0">
        <button type="button" @click="router.back()" class="inv-back-btn">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <h1 class="inv-page-title">{{ isEdit ? 'Edit Customer' : 'Add New Customer' }}</h1>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" @click="router.back()" class="inv-btn-secondary hidden sm:inline-flex">Cancel</button>
        <button type="submit" form="client-form" class="inv-btn-primary" :disabled="loading">
          {{ loading ? 'Saving…' : (isEdit ? 'Save Changes' : 'Add Customer') }}
        </button>
      </div>
    </div>

    <!-- Body -->
    <div class="inv-body">
      <form id="client-form" @submit.prevent="submit" class="inv-layout">
        <!-- Main Column -->
        <div class="inv-main">
          <div v-if="error" class="text-sm text-danger-600 bg-danger-50 border border-danger-100 rounded-xl px-4 py-3 font-medium">
            {{ error }}
          </div>

          <!-- Basic Details Card -->
          <div class="inv-card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wider mb-2">Basic Details</h2>
            
            <div class="grid sm:grid-cols-2 gap-4">
              <div class="sm:col-span-2">
                <label class="inv-label">{{ form.type === 'business' ? 'Business Name' : 'Full Name' }} *</label>
                <input v-model="form.name" type="text" class="inv-input !bg-white" required :placeholder="form.type === 'business' ? 'e.g. Sharma Traders' : 'e.g. Rajesh Kumar'" />
              </div>
              
              <div v-if="form.type === 'business'">
                <label class="inv-label">Owner / Contact Person</label>
                <input v-model="form.company" type="text" class="inv-input !bg-white" placeholder="Owner or manager name" />
              </div>
              
              <div>
                <label class="inv-label">Mobile Number *</label>
                <input v-model="form.mobile" type="tel" class="inv-input !bg-white" placeholder="9876543210" maxlength="10" required />
              </div>
            </div>

            <!-- "More Details" Toggle -->
            <div class="pt-2">
              <button type="button" @click="showMore = !showMore" class="flex items-center gap-2 text-sm font-semibold text-primary-600 hover:text-primary-700 transition-colors">
                <svg class="w-4 h-4 transition-transform" :class="showMore ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
                {{ showMore ? 'Hide Contact & Tax Details' : 'Add email, GST, PAN & Address details…' }}
              </button>
            </div>
          </div>

          <!-- Optional contact & tax details (expandable) -->
          <div v-if="showMore" class="space-y-4 animate-fade-in-up">
            <!-- Contact & Tax -->
            <div class="inv-card p-5 space-y-4">
              <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wider mb-2">Tax & Contact Details</h2>
              <div class="grid sm:grid-cols-2 gap-4">
                <div>
                  <label class="inv-label">Email Address</label>
                  <input v-model="form.email" type="email" class="inv-input !bg-white" placeholder="email@example.com" />
                </div>
                
                <div>
                  <label class="inv-label">GST Number <span class="text-gray-400 font-normal">(if registered)</span></label>
                  <input v-model="form.gstin" type="text" @blur="validateGstin"
                    class="inv-input font-mono uppercase !bg-white" :class="gstinError ? 'border-red-400 focus:ring-red-200' : ''"
                    placeholder="e.g. 27AABCU9603R1Z6" maxlength="15" />
                  <p v-if="gstinError" class="text-xs text-red-500 mt-1">{{ gstinError }}</p>
                </div>
                
                <div>
                  <label class="inv-label">PAN Number</label>
                  <input v-model="form.pan" type="text" class="inv-input font-mono uppercase !bg-white" placeholder="e.g. AABCU9603R" maxlength="10" />
                </div>
              </div>
            </div>

            <!-- Address -->
            <div class="inv-card p-5 space-y-4">
              <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wider mb-2">Billing Address</h2>
              <div class="grid sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                  <label class="inv-label">Street / Shop Number</label>
                  <input v-model="form.address_line1" type="text" class="inv-input !bg-white" placeholder="e.g. Shop No. 12, Main Market" />
                </div>
                
                <div class="sm:col-span-2">
                  <label class="inv-label">Area / Locality</label>
                  <input v-model="form.address_line2" type="text" class="inv-input !bg-white" placeholder="e.g. Near Bus Stand, Gandhi Road" />
                </div>
                
                <div>
                  <label class="inv-label">City</label>
                  <input v-model="form.city" type="text" class="inv-input !bg-white" placeholder="Mumbai" />
                </div>
                
                <div>
                  <label class="inv-label">PIN Code</label>
                  <input v-model="form.pincode" type="text" class="inv-input !bg-white" placeholder="400001" maxlength="6" />
                </div>
                
                <div class="sm:col-span-2">
                  <label class="inv-label">State</label>
                  <select v-model="form.state_id" class="inv-select !bg-white">
                    <option value="">Select State</option>
                    <option v-for="s in states" :key="s.id" :value="s.id">{{ s.name }}</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Primary contact (optional) -->
            <div class="inv-card p-5 space-y-4">
              <div>
                <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wider">Additional Contact Person</h2>
                <p class="text-xs text-gray-400 mt-0.5">Add a billing or accounts contact for this customer</p>
              </div>
              <div class="grid sm:grid-cols-2 gap-4">
                <div>
                  <label class="inv-label">Contact Name</label>
                  <input v-model="form.contact_name" type="text" class="inv-input !bg-white" placeholder="e.g. Priya Sharma" />
                </div>
                
                <div>
                  <label class="inv-label">Role / Designation</label>
                  <input v-model="form.contact_designation" type="text" class="inv-input !bg-white" placeholder="e.g. Accounts Manager" />
                </div>
                
                <div>
                  <label class="inv-label">Mobile</label>
                  <input v-model="form.contact_mobile" type="tel" class="inv-input !bg-white" placeholder="9876543210" />
                </div>
                
                <div>
                  <label class="inv-label">Email</label>
                  <input v-model="form.contact_email" type="email" class="inv-input !bg-white" placeholder="contact@example.com" />
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Sidebar Column -->
        <div class="inv-sidebar">
          <!-- Customer Type / Settings -->
          <div class="inv-card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wider mb-2">Customer Profile</h2>
            
            <div>
              <label class="inv-label">Customer Type</label>
              <div class="flex gap-2 p-1 bg-gray-50 rounded-2xl w-full border border-gray-100/60 mt-1">
                <button type="button" @click="form.type='business'"
                  class="flex-1 py-2.5 rounded-xl text-sm font-bold transition-all shadow-sm"
                  :class="form.type==='business' ? 'bg-white text-primary-700 ring-1 ring-gray-200/50' : 'bg-transparent text-gray-500 hover:text-gray-700 shadow-none'">
                  Business / Shop
                </button>
                <button type="button" @click="form.type='individual'"
                  class="flex-1 py-2.5 rounded-xl text-sm font-bold transition-all shadow-sm"
                  :class="form.type==='individual' ? 'bg-white text-primary-700 ring-1 ring-gray-200/50' : 'bg-transparent text-gray-500 hover:text-gray-700 shadow-none'">
                  Individual
                </button>
              </div>
            </div>

            <div>
              <label class="inv-label">Payment Terms</label>
              <div class="flex items-center gap-2 mt-1">
                <input v-model="form.credit_days" type="number" class="inv-input !bg-white text-right pr-4" min="0" max="365" />
                <span class="text-sm text-gray-500 shrink-0">days</span>
              </div>
            </div>
          </div>

          <!-- Notes -->
          <div class="inv-card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wider mb-2">Internal Notes</h2>
            <div>
              <textarea v-model="form.notes" class="inv-textarea !bg-white" rows="4" placeholder="Add any private notes about this customer…"></textarea>
            </div>
          </div>
        </div>
      </form>
    </div>

    <!-- Mobile Footer Actions -->
    <div class="form-footer-mobile lg:hidden">
      <button type="button" @click="router.back()" class="inv-btn-secondary flex-1 justify-center">Cancel</button>
      <button type="submit" form="client-form" class="inv-btn-primary flex-1 justify-center" :disabled="loading">
        {{ loading ? 'Saving…' : (isEdit ? 'Save Changes' : 'Add Customer') }}
      </button>
    </div>
  </div>
</template>
