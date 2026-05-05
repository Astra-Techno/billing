<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

const router = useRouter()
const auth   = useAuthStore()

const form     = ref({ email: '', password: '' })
const error    = ref('')
const loading  = ref(false)
const showPass = ref(false)

async function submit() {
  error.value   = ''
  loading.value = true
  try {
    await auth.login(form.value.email, form.value.password)
    router.push('/')
  } catch (e) {
    error.value = e.response?.data?.message || 'Login failed. Please try again.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex flex-col md:flex-row">

    <!-- Left: Brand panel -->
    <div class="md:w-5/12 bg-gradient-to-br from-primary-600 via-primary-700 to-blue-800 flex flex-col items-center justify-center px-10 py-16 md:min-h-screen">
      <div class="w-16 h-16 rounded-2xl bg-white/20 backdrop-blur flex items-center justify-center mb-5 shadow-xl">
        <span class="text-white font-black text-3xl">B</span>
      </div>
      <h1 class="text-3xl font-black text-white tracking-tight text-center">CloudBill</h1>
      <p class="text-primary-200 text-sm mt-2 text-center leading-relaxed max-w-xs">
        Simple GST billing, invoicing &amp; expense tracking for Indian businesses
      </p>

      <div class="mt-10 space-y-4 w-full max-w-xs hidden md:block">
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
    <div class="flex-1 bg-white flex items-center justify-center px-6 py-12">
      <div class="w-full max-w-sm">

        <h2 class="text-2xl font-bold text-gray-900 mb-1">Welcome back</h2>
        <p class="text-sm text-gray-400 mb-8">Sign in to your CloudBill account</p>

        <form @submit.prevent="submit" class="space-y-5">

          <div>
            <label class="form-label">Email address</label>
            <div class="relative">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
              </span>
              <input v-model="form.email" type="email" class="form-input pl-10" placeholder="you@example.com" required autofocus />
            </div>
          </div>

          <div>
            <label class="form-label">Password</label>
            <div class="relative">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
              </span>
              <input v-model="form.password" :type="showPass ? 'text' : 'password'" class="form-input pl-10 pr-10" placeholder="Enter your password" required />
              <button type="button" @click="showPass = !showPass" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
                <svg v-if="!showPass" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
              </button>
            </div>
          </div>

          <div v-if="error" class="flex items-center gap-2 text-sm text-red-600 bg-red-50 border border-red-100 rounded-xl px-4 py-3">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            {{ error }}
          </div>

          <button type="submit"
            class="w-full flex items-center justify-center gap-2 py-3.5 rounded-2xl bg-primary-600 hover:bg-primary-700 active:scale-[.98] text-white font-semibold text-sm shadow-md shadow-primary-200 transition-all duration-150"
            :disabled="loading">
            <svg v-if="loading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            {{ loading ? 'Signing in…' : 'Sign In' }}
          </button>

        </form>

        <p class="text-sm text-center text-gray-400 mt-8">
          New business?
          <RouterLink to="/register" class="text-primary-600 font-semibold hover:underline">Create free account</RouterLink>
        </p>

      </div>
    </div>

  </div>
</template>
