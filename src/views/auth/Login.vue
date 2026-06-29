<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import AppLogo from '../../components/AppLogo.vue'
import { APP_NAME, APP_TAGLINE } from '../../config/brand'

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
    router.push(auth.isSuperAdmin && !auth.businessId ? '/admin' : '/')
  } catch (e) {
    if (!e.response) {
      error.value = 'Cannot reach server. Check your internet connection and try again.'
    } else {
      error.value = e.response?.data?.message || 'Login failed. Please try again.'
    }
  } finally {
    loading.value = false
  }
}

const features = [
  { icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', text: 'GST-ready bills in seconds' },
  { icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z', text: 'Manage customers & quotations' },
  { icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', text: 'Reports & GST filing made easy' },
]
</script>

<template>
  <div class="auth-page flex flex-col md:flex-row w-full min-h-screen min-h-[100dvh] md:min-h-screen">

    <!-- Left: Brand panel — desktop only -->
    <div class="hidden md:flex md:w-[42%] lg:w-5/12 relative overflow-hidden flex-col items-center justify-center px-10 lg:px-14 py-16 bg-hero-premium">
      <div class="absolute -right-16 -top-16 w-48 h-48 bg-white/10 rounded-full blur-3xl pointer-events-none"></div>
      <div class="absolute -left-12 bottom-0 w-40 h-40 bg-accent-teal/30 rounded-full blur-3xl pointer-events-none"></div>
      <div class="relative mb-8 rounded-2xl bg-white/95 p-5 shadow-premium ring-1 ring-white/25">
        <AppLogo size="lg" />
      </div>
      <p class="text-white/85 text-base mt-1 text-center leading-relaxed max-w-sm">{{ APP_TAGLINE }}</p>
      <div class="mt-12 space-y-5 w-full max-w-sm">
        <div v-for="f in features" :key="f.text" class="flex items-center gap-4">
          <div class="w-10 h-10 rounded-xl bg-white/15 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" :d="f.icon"/>
            </svg>
          </div>
          <p class="text-white/85 text-[15px] font-medium">{{ f.text }}</p>
        </div>
      </div>
    </div>

    <!-- Right: form -->
    <div class="auth-form-panel flex-1 flex flex-col min-h-0 md:min-h-screen bg-surface-dim md:justify-center">
      <!-- Scrollable fields (mobile) / centered card (desktop) -->
      <div class="flex-1 overflow-y-auto overscroll-contain md:overflow-visible md:flex md:items-center md:justify-center md:flex-none md:flex-1 px-4 pt-6 pb-4 md:px-10 md:py-12">
        <div class="auth-login-card w-full max-w-sm md:max-w-[440px] mx-auto card-premium px-5 py-7 md:px-10 md:py-10">
          <div class="flex items-center gap-2.5 mb-5 md:hidden">
            <AppLogo size="sm" />
          </div>
          <div class="hidden md:flex justify-center mb-6">
            <AppLogo size="md" />
          </div>

          <h2 class="text-[22px] md:text-[28px] font-bold text-gray-900 tracking-tight mb-1">Welcome back</h2>
          <p class="text-sm md:text-base text-gray-500 mb-6 md:mb-8">Sign in to your {{ APP_NAME }} account</p>

          <form id="login-form" @submit.prevent="submit" class="space-y-4 md:space-y-5">
            <div>
              <label class="form-label md:text-sm">Email address</label>
              <div class="relative">
                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">
                  <svg class="w-4 h-4 md:w-5 md:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                  </svg>
                </span>
                <input v-model="form.email" type="email" class="form-input pl-10 md:py-3 md:text-base" placeholder="you@example.com" required dir="ltr" autocomplete="email" enterkeyhint="next" />
              </div>
            </div>

            <div>
              <label class="form-label md:text-sm">Password</label>
              <div class="relative">
                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">
                  <svg class="w-4 h-4 md:w-5 md:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                  </svg>
                </span>
                <input v-model="form.password" :type="showPass ? 'text' : 'password'" class="form-input pl-10 pr-10 md:py-3 md:text-base" placeholder="Enter your password" required dir="ltr" autocomplete="current-password" enterkeyhint="go" />
                <button type="button" @click="showPass = !showPass" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition p-1">
                  <svg v-if="!showPass" class="w-4 h-4 md:w-5 md:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                  <svg v-else class="w-4 h-4 md:w-5 md:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                </button>
              </div>
            </div>

            <div v-if="error" class="flex items-start gap-2 text-sm text-red-600 bg-red-50 border border-red-100 rounded-xl px-3 py-2.5 md:py-3">
              <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <span>{{ error }}</span>
            </div>

            <!-- Desktop inline button -->
            <button type="submit"
              class="hidden md:flex w-full items-center justify-center gap-2 py-3.5 rounded-2xl bg-primary-600 hover:bg-primary-700 text-white font-semibold text-base shadow-lg shadow-primary-200/50 transition-all"
              :disabled="loading">
              <svg v-if="loading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              {{ loading ? 'Signing in…' : 'Sign In' }}
            </button>
          </form>

          <p class="text-sm md:text-[15px] text-center text-gray-400 mt-6 md:mt-8 hidden md:block">
            New business?
            <RouterLink to="/register" class="text-primary-600 font-semibold hover:underline">Create free account</RouterLink>
          </p>
        </div>
      </div>

      <!-- Mobile sticky Sign In -->
      <div class="auth-sticky-footer md:hidden shrink-0 px-4 pt-2 pb-[max(1rem,env(safe-area-inset-bottom))] bg-surface-dim border-t border-gray-200/80">
        <button type="submit" form="login-form"
          class="w-full flex items-center justify-center gap-2 py-3.5 rounded-2xl bg-primary-600 active:bg-primary-700 text-white font-semibold text-base shadow-lg shadow-primary-200/60"
          :disabled="loading">
          <svg v-if="loading" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
          </svg>
          {{ loading ? 'Signing in…' : 'Sign In' }}
        </button>
        <p class="text-sm text-center text-gray-400 mt-3">
          New business?
          <RouterLink to="/register" class="text-primary-600 font-semibold">Create free account</RouterLink>
        </p>
      </div>
    </div>
  </div>
</template>
