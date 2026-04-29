<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { all } from '../../api'

const router = useRouter()
const auth   = useAuthStore()

const step     = ref(1)
const error    = ref('')
const loading  = ref(false)
const states   = ref([])
const showPass    = ref(false)
const showConfirm = ref(false)

const form = ref({
  name: '', email: '', mobile: '', password: '', password_confirmation: '',
  business_name: '', business_type: 'proprietorship', state_id: '',
  gstin: '', invoice_prefix: 'INV',
})

const businessTypes = [
  { value: 'proprietorship', label: 'Proprietorship / Individual' },
  { value: 'partnership',    label: 'Partnership Firm' },
  { value: 'llp',            label: 'LLP' },
  { value: 'private_ltd',    label: 'Private Limited' },
  { value: 'public_ltd',     label: 'Public Limited' },
  { value: 'trust',          label: 'Trust / NGO' },
  { value: 'other',          label: 'Other' },
]

onMounted(async () => {
  const { data } = await all('IndianState')
  states.value = data.data || []
})

function nextStep() {
  error.value = ''
  if (!form.value.name)     return error.value = 'Please enter your name.'
  if (!form.value.email)    return error.value = 'Please enter your email.'
  if (!form.value.mobile)   return error.value = 'Please enter your mobile number.'
  if (form.value.password.length < 8) return error.value = 'Password must be at least 8 characters.'
  if (form.value.password !== form.value.password_confirmation) return error.value = 'Passwords do not match.'
  step.value = 2
}

async function submit() {
  error.value = ''
  if (!form.value.business_name) return error.value = 'Please enter your business name.'
  if (!form.value.state_id)      return error.value = 'Please select your state.'

  loading.value = true
  try {
    await auth.register(form.value)
    router.push('/')
  } catch (e) {
    error.value = e.response?.data?.message || 'Registration failed. Please try again.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="h-screen flex flex-col md:flex-row overflow-hidden">

    <!-- Left: Brand panel -->
    <div class="md:w-5/12 bg-gradient-to-br from-primary-600 via-primary-700 to-blue-800 flex flex-col items-center justify-center px-10 py-8 md:h-full overflow-y-auto">
      <div class="w-16 h-16 rounded-2xl bg-white/20 backdrop-blur flex items-center justify-center mb-5 shadow-xl">
        <span class="text-white font-black text-3xl">B</span>
      </div>
      <h1 class="text-3xl font-black text-white tracking-tight text-center">BillBook India</h1>
      <p class="text-primary-200 text-sm mt-2 text-center leading-relaxed max-w-xs">
        Free for 30 days · No credit card needed
      </p>

      <!-- Step indicator -->
      <div class="flex items-center gap-3 mt-10">
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all shadow-sm"
            :class="step === 1 ? 'bg-white text-primary-600' : 'bg-white/30 text-white'">1</div>
          <span class="text-xs font-semibold" :class="step === 1 ? 'text-white' : 'text-primary-300'">Your Info</span>
        </div>
        <div class="w-10 h-0.5 rounded-full transition-all" :class="step === 2 ? 'bg-white' : 'bg-white/30'" />
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all shadow-sm"
            :class="step === 2 ? 'bg-white text-primary-600' : 'bg-white/30 text-white'">2</div>
          <span class="text-xs font-semibold" :class="step === 2 ? 'text-white' : 'text-primary-300'">Business</span>
        </div>
      </div>

      <div class="mt-6 space-y-3 w-full max-w-xs hidden md:block">
        <div v-for="f in [
          { icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', text: 'GST-ready bills in seconds' },
          { icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', text: 'Manage customers & quotations' },
          { icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', text: 'Reports & GST filing made easy' },
        ]" :key="f.text" class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-white/15 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" :d="f.icon"/>
            </svg>
          </div>
          <p class="text-white/80 text-sm font-medium">{{ f.text }}</p>
        </div>
      </div>
    </div>

    <!-- Right: Form panel -->
    <div class="flex-1 bg-white flex items-center justify-center px-6 py-4 overflow-y-auto">
      <div class="w-full max-w-sm">

        <!-- Step 1 -->
        <form v-if="step === 1" @submit.prevent="nextStep" class="space-y-3">
          <div class="mb-4">
            <h2 class="text-xl font-bold text-gray-900">Create your account</h2>
            <p class="text-sm text-gray-400 mt-0.5">Enter your personal details to get started</p>
          </div>

          <div>
            <label class="form-label">Full Name</label>
            <div class="relative">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
              </span>
              <input v-model="form.name" type="text" class="form-input pl-10" placeholder="Rajesh Kumar" required autofocus />
            </div>
          </div>

          <div>
            <label class="form-label">Email address</label>
            <div class="relative">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
              </span>
              <input v-model="form.email" type="email" class="form-input pl-10" placeholder="rajesh@example.com" required />
            </div>
          </div>

          <div>
            <label class="form-label">Mobile Number</label>
            <div class="relative">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
              </span>
              <input v-model="form.mobile" type="tel" class="form-input pl-10" placeholder="9876543210" maxlength="10" required />
            </div>
          </div>

          <div>
            <label class="form-label">Password</label>
            <div class="relative">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
              </span>
              <input v-model="form.password" :type="showPass ? 'text' : 'password'" class="form-input pl-10 pr-10" placeholder="Min 8 characters" required />
              <button type="button" @click="showPass = !showPass" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
                <svg v-if="!showPass" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
              </button>
            </div>
          </div>

          <div>
            <label class="form-label">Confirm Password</label>
            <div class="relative">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
              </span>
              <input v-model="form.password_confirmation" :type="showConfirm ? 'text' : 'password'" class="form-input pl-10 pr-10" placeholder="Repeat password" required />
              <button type="button" @click="showConfirm = !showConfirm" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
                <svg v-if="!showConfirm" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
              </button>
            </div>
          </div>

          <div v-if="error" class="flex items-center gap-2 text-sm text-red-600 bg-red-50 border border-red-100 rounded-xl px-4 py-3">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ error }}
          </div>

          <button type="submit" class="w-full flex items-center justify-center gap-2 py-3 rounded-2xl bg-primary-600 hover:bg-primary-700 active:scale-[.98] text-white font-semibold text-sm shadow-md shadow-primary-200 transition-all duration-150">
            Continue
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
          </button>
        </form>

        <!-- Step 2 -->
        <form v-else @submit.prevent="submit" class="space-y-3">
          <div class="mb-4">
            <button type="button" @click="step = 1" class="flex items-center gap-1.5 text-sm text-primary-600 font-semibold mb-2 hover:underline">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
              Back
            </button>
            <h2 class="text-xl font-bold text-gray-900">Your Business</h2>
            <p class="text-sm text-gray-400 mt-0.5">Tell us about your business</p>
          </div>

          <div>
            <label class="form-label">Business / Shop Name</label>
            <div class="relative">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
              </span>
              <input v-model="form.business_name" type="text" class="form-input pl-10" placeholder="e.g. Sharma Electronics" required />
            </div>
          </div>

          <div>
            <label class="form-label">Business Type</label>
            <select v-model="form.business_type" class="form-select">
              <option v-for="t in businessTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
            </select>
          </div>

          <div>
            <label class="form-label">State</label>
            <select v-model="form.state_id" class="form-select" required>
              <option value="">Select State</option>
              <option v-for="s in states" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
          </div>

          <div>
            <label class="form-label">GSTIN <span class="text-gray-400 font-normal">(optional)</span></label>
            <div class="relative">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
              </span>
              <input v-model="form.gstin" type="text" class="form-input pl-10" placeholder="e.g. 29AABCU9603R1Z6" maxlength="15" />
            </div>
          </div>

          <div v-if="error" class="flex items-center gap-2 text-sm text-red-600 bg-red-50 border border-red-100 rounded-xl px-4 py-3">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ error }}
          </div>

          <button type="submit"
            class="w-full flex items-center justify-center gap-2 py-3 rounded-2xl bg-primary-600 hover:bg-primary-700 active:scale-[.98] text-white font-semibold text-sm shadow-md shadow-primary-200 transition-all duration-150"
            :disabled="loading">
            <svg v-if="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            {{ loading ? 'Creating account…' : 'Create Free Account' }}
          </button>
        </form>

        <p class="text-sm text-center text-gray-400 mt-4">
          Already have an account?
          <RouterLink to="/login" class="text-primary-600 font-semibold hover:underline">Sign in</RouterLink>
        </p>

      </div>
    </div>

  </div>
</template>
