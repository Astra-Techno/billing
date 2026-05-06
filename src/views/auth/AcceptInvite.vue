<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

const route  = useRoute()
const router = useRouter()
const auth   = useAuthStore()

const apiBase = import.meta.env.VITE_API_URL || 'http://localhost/billing/api'

const loading      = ref(true)
const submitting   = ref(false)
const error        = ref('')
const invite       = ref(null)   // { email, role, business_name, user_exists }

const form = ref({ name: '', mobile: '', password: '', password_confirmation: '' })

async function loadInvite() {
  loading.value = true
  error.value   = ''
  try {
    const res = await fetch(`${apiBase}/guest-task/Invite/check`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ token: route.params.token }),
    })
    const json = await res.json()
    if (!json.success) { error.value = json.message || 'Invalid or expired invite link.'; loading.value = false; return }
    invite.value = json.data
  } catch {
    error.value = 'Failed to load invite. Check your connection.'
  }
  loading.value = false
}

async function accept() {
  error.value   = ''
  submitting.value = true
  try {
    const payload = { token: route.params.token }
    if (!invite.value.user_exists) {
      if (!form.value.name || !form.value.password) { error.value = 'Name and password are required.'; submitting.value = false; return }
      if (form.value.password !== form.value.password_confirmation) { error.value = 'Passwords do not match.'; submitting.value = false; return }
      Object.assign(payload, form.value)
    }

    const res = await fetch(`${apiBase}/guest-task/Invite/accept`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload),
    })
    const json = await res.json()
    if (!json.success) { error.value = json.message || 'Failed to accept invite.'; submitting.value = false; return }

    // Log them in via setSession
    auth.setSession(json.data)
    router.push('/')
  } catch {
    error.value = 'Something went wrong. Please try again.'
    submitting.value = false
  }
}

const ROLE_LABELS = { admin: 'Admin', accountant: 'Accountant', staff: 'Staff' }

onMounted(loadInvite)
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 flex items-center justify-center p-4">
    <div class="w-full max-w-md">

      <!-- Logo -->
      <div class="text-center mb-8">
        <div class="w-16 h-16 rounded-2xl bg-primary-600 flex items-center justify-center mx-auto mb-4 shadow-lg shadow-primary-500/30">
          <svg class="w-9 h-9 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
        </div>
        <h1 class="text-2xl font-extrabold text-gray-900">CloudBill</h1>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="bg-white rounded-[2rem] shadow-soft p-8 text-center">
        <div class="w-8 h-8 border-4 border-primary-100 border-t-primary-600 rounded-full animate-spin mx-auto mb-3"></div>
        <p class="text-sm text-gray-500">Checking your invite…</p>
      </div>

      <!-- Error (invalid/expired) -->
      <div v-else-if="error && !invite" class="bg-white rounded-[2rem] shadow-soft p-8 text-center space-y-4">
        <div class="w-16 h-16 rounded-full bg-red-50 flex items-center justify-center mx-auto text-3xl">❌</div>
        <h2 class="text-xl font-extrabold text-gray-900">Invite Invalid</h2>
        <p class="text-sm text-gray-500">{{ error }}</p>
        <p class="text-sm text-gray-400">Ask the business owner to send you a new invite link.</p>
      </div>

      <!-- Accept form -->
      <div v-else-if="invite" class="bg-white rounded-[2rem] shadow-soft p-8 space-y-5">

        <!-- Invite info -->
        <div class="text-center space-y-1">
          <div class="w-14 h-14 rounded-full bg-primary-50 flex items-center justify-center mx-auto mb-3 text-2xl">🏢</div>
          <h2 class="text-xl font-extrabold text-gray-900">You're Invited!</h2>
          <p class="text-sm text-gray-600">
            Join <strong>{{ invite.business_name }}</strong> as
            <span class="font-bold text-primary-700">{{ ROLE_LABELS[invite.role] || invite.role }}</span>
          </p>
          <p class="text-xs text-gray-400">{{ invite.email }}</p>
        </div>

        <!-- If user already has an account, just show accept button -->
        <template v-if="invite.user_exists">
          <div class="bg-emerald-50 rounded-xl px-4 py-3 text-sm text-emerald-700 font-medium text-center">
            You already have a CloudBill account. Click below to join the business.
          </div>
        </template>

        <!-- New user — collect name + password -->
        <template v-else>
          <div class="space-y-3">
            <div>
              <label class="form-label">Your Full Name</label>
              <input v-model="form.name" type="text" class="form-input" placeholder="Enter your name" />
            </div>
            <div>
              <label class="form-label">Mobile (optional)</label>
              <input v-model="form.mobile" type="tel" class="form-input" placeholder="10-digit mobile" />
            </div>
            <div>
              <label class="form-label">Create Password</label>
              <input v-model="form.password" type="password" class="form-input" placeholder="At least 8 characters" />
            </div>
            <div>
              <label class="form-label">Confirm Password</label>
              <input v-model="form.password_confirmation" type="password" class="form-input" placeholder="Repeat password" />
            </div>
          </div>
        </template>

        <div v-if="error" class="text-sm text-danger-600 bg-danger-50 rounded-lg px-3 py-2">{{ error }}</div>

        <button @click="accept" :disabled="submitting" class="btn btn-primary w-full text-base py-3">
          {{ submitting ? 'Joining…' : 'Accept & Join Business' }}
        </button>

        <p class="text-center text-xs text-gray-400">By accepting you agree to the Terms of Service.</p>
      </div>

    </div>
  </div>
</template>
