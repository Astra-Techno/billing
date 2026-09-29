<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { task } from '../../api'
import AppLogo from '../../components/AppLogo.vue'

const router = useRouter()
const status = ref('checking')      // checking | choose | pending | active | expired | clock_tampered | trial_used
const message = ref('Checking this PC...')
const working = ref(false)
const expiresAt = ref(null)
const selectedYears = ref(1)
let timer

async function check() {
  try {
    const current = await task('DesktopLicense', 'localStatus')
    const d = current.data.data
    if (d.active) {
      status.value = 'active'
      message.value = 'This PC is activated.'
      if (d.expires_at_human) expiresAt.value = d.expires_at_human
      clearInterval(timer)
      setTimeout(() => router.replace('/'), 800)
      return
    }
    if (d.reason === 'expired') {
      status.value = 'expired'
      message.value = 'Your licence has expired. Please request re-activation.'
      clearInterval(timer)
      return
    }
    if (d.reason === 'clock_tampered') {
      status.value = 'clock_tampered'
      message.value = 'System clock change detected. Licence verification failed.'
      clearInterval(timer)
      return
    }

    // Check if there's a pending activation request
    const poll = await task('DesktopLicense', 'localPoll')
    const value = poll.data.data?.status
    if (poll.data.data?.active || value === 'active') {
      status.value = 'active'
      message.value = 'Activation approved. Opening AI Billing...'
      clearInterval(timer)
      setTimeout(() => router.replace('/'), 800)
    } else if (value === 'rejected') {
      status.value = 'choose'
      message.value = 'Previous activation request was rejected.'
    } else if (value === 'expired') {
      status.value = 'choose'
      message.value = ''
    } else if (value === 'pending') {
      status.value = 'pending'
      message.value = 'Waiting for approval from the administrator.'
    } else {
      // No pending request — show plan selection
      status.value = 'choose'
      message.value = ''
    }
  } catch (e) {
    status.value = 'choose'
    message.value = e.response?.data?.message || 'Connect to the internet and try again.'
  }
}

async function startTrial() {
  working.value = true
  message.value = ''
  try {
    const res = await task('DesktopLicense', 'localRequest', { type: 'trial' })
    if (res.data.data?.status === 'trial_started') {
      status.value = 'checking'
      message.value = 'Trial activated! Opening AI Billing...'
      // Trial is auto-approved; poll will pick up the license
      await check()
    }
  } catch (e) {
    const msg = e.response?.data?.message || 'Could not start trial.'
    if (msg.includes('already been used')) {
      status.value = 'trial_used'
    }
    message.value = msg
  } finally {
    working.value = false
  }
}

async function requestActivation() {
  working.value = true
  message.value = ''
  try {
    await task('DesktopLicense', 'localRequest', { type: 'activation', requested_years: selectedYears.value })
    status.value = 'pending'
    message.value = `Activation request sent for ${selectedYears.value} ${selectedYears.value === 1 ? 'year' : 'years'}. Waiting for administrator approval.`
    await check()
  } catch (e) {
    message.value = e.response?.data?.message || 'Could not send activation request.'
  } finally {
    working.value = false
  }
}

onMounted(() => { check(); timer = setInterval(check, 5000) })
onUnmounted(() => clearInterval(timer))
</script>

<template>
<div class="min-h-screen bg-gradient-to-br from-slate-50 to-indigo-50/30 flex items-center justify-center p-5">
  <div class="w-full max-w-2xl">
    <!-- Logo + title -->
    <div class="text-center mb-8">
      <div class="flex justify-center mb-4"><AppLogo size="lg"/></div>
      <h1 class="text-2xl font-bold text-gray-900">
        {{ status === 'expired' ? 'Licence Expired' : status === 'clock_tampered' ? 'Verification Failed' : 'Welcome to AI Billing Offline' }}
      </h1>
      <p class="text-gray-500 mt-2">
        {{ status === 'expired' ? 'Your licence period has ended. Request a new activation to continue.'
         : status === 'clock_tampered' ? 'The system clock appears to have been changed. Please fix it and restart.'
         : status === 'choose' || status === 'trial_used' ? 'Choose how you want to get started'
         : '' }}
      </p>
    </div>

    <!-- Status messages -->
    <div v-if="message && !['choose','trial_used'].includes(status)" class="mb-6 rounded-xl p-4 text-center text-sm font-medium"
      :class="status === 'active' ? 'bg-green-50 text-green-700'
            : status === 'pending' ? 'bg-amber-50 text-amber-800'
            : status === 'checking' ? 'bg-blue-50 text-blue-700'
            : 'bg-red-50 text-red-700'">
      <span v-if="status === 'checking' || status === 'pending'" class="inline-block w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin mr-2 align-[-2px]"></span>
      {{ message }}
    </div>

    <!-- Plan Selection (choose / trial_used) -->
    <div v-if="status === 'choose' || status === 'trial_used'" class="grid grid-cols-1 md:grid-cols-2 gap-5">
      <!-- Trial Card -->
      <div class="bg-white rounded-2xl border-2 shadow-sm p-6 flex flex-col"
        :class="status === 'trial_used' ? 'border-gray-200 opacity-60' : 'border-indigo-100 hover:border-indigo-300 transition-colors'">
        <div class="flex items-center gap-2 mb-1">
          <span class="text-2xl">&#9201;</span>
          <h2 class="text-lg font-bold text-gray-900">Free Trial</h2>
        </div>
        <p class="text-gray-500 text-sm mb-4">Try all features for 30 days, no payment required.</p>
        <ul class="space-y-2 text-sm text-gray-600 mb-6 flex-1">
          <li class="flex items-start gap-2"><svg class="w-4 h-4 text-green-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> All features unlocked</li>
          <li class="flex items-start gap-2"><svg class="w-4 h-4 text-green-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> 30 days from first use</li>
          <li class="flex items-start gap-2"><svg class="w-4 h-4 text-green-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> No credit card needed</li>
          <li class="flex items-start gap-2"><svg class="w-4 h-4 text-green-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> One trial per device</li>
        </ul>
        <button v-if="status !== 'trial_used'" @click="startTrial" :disabled="working"
          class="w-full py-3 rounded-xl bg-indigo-600 text-white font-semibold hover:bg-indigo-700 disabled:opacity-60 transition-colors">
          {{ working ? 'Starting trial...' : 'Start 30-Day Free Trial' }}
        </button>
        <p v-else class="text-center text-sm text-red-600 font-medium py-3">Trial already used on this PC</p>
      </div>

      <!-- Full Licence Card -->
      <div class="bg-white rounded-2xl border-2 border-emerald-100 hover:border-emerald-300 shadow-sm p-6 flex flex-col transition-colors">
        <div class="flex items-center gap-2 mb-1">
          <span class="text-2xl">&#9989;</span>
          <h2 class="text-lg font-bold text-gray-900">Full Licence</h2>
        </div>
        <p class="text-gray-500 text-sm mb-4">Activate this PC with a permanent licence.</p>
        <ul class="space-y-2 text-sm text-gray-600 mb-5 flex-1">
          <li class="flex items-start gap-2"><svg class="w-4 h-4 text-green-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> All features unlocked</li>
          <li class="flex items-start gap-2"><svg class="w-4 h-4 text-green-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Valid for 1 to 5 years</li>
          <li class="flex items-start gap-2"><svg class="w-4 h-4 text-green-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Priority support</li>
          <li class="flex items-start gap-2"><svg class="w-4 h-4 text-green-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg> Admin approval required</li>
        </ul>
        <label class="block text-sm font-semibold text-gray-700 mb-2">Select licence period</label>
        <div class="flex gap-2 mb-4">
          <button v-for="y in [1, 2, 3, 5]" :key="y" @click="selectedYears = y"
            class="flex-1 py-2 rounded-lg text-sm font-semibold border transition-all"
            :class="selectedYears === y ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-gray-700 border-gray-200 hover:border-gray-300'">
            {{ y }}{{ y === 1 ? ' yr' : ' yrs' }}
          </button>
        </div>
        <button @click="requestActivation" :disabled="working"
          class="w-full py-3 rounded-xl bg-emerald-600 text-white font-semibold hover:bg-emerald-700 disabled:opacity-60 transition-colors">
          {{ working ? 'Sending request...' : `Request ${selectedYears} ${selectedYears === 1 ? 'Year' : 'Years'} Licence` }}
        </button>
      </div>
    </div>

    <!-- Error message below cards -->
    <p v-if="message && ['choose','trial_used'].includes(status)" class="mt-4 text-center text-sm text-red-600 font-medium">{{ message }}</p>

    <!-- Pending state -->
    <div v-if="status === 'pending'" class="bg-white rounded-2xl border shadow-sm p-8 text-center">
      <div class="w-16 h-16 rounded-full bg-amber-50 flex items-center justify-center mx-auto mb-4">
        <span class="text-3xl">&#9203;</span>
      </div>
      <h2 class="text-lg font-bold text-gray-900 mb-2">Activation Request Sent</h2>
      <p class="text-gray-500 text-sm mb-4">{{ message }}</p>
      <p class="text-gray-400 text-xs">This page will automatically activate once the administrator approves your request.</p>
      <button @click="check" class="mt-5 px-6 py-2.5 rounded-xl border border-indigo-200 text-indigo-700 font-semibold text-sm">Check approval now</button>
    </div>

    <!-- Expired state -->
    <div v-if="status === 'expired'" class="bg-white rounded-2xl border shadow-sm p-8 text-center max-w-lg mx-auto">
      <div class="w-16 h-16 rounded-full bg-red-50 flex items-center justify-center mx-auto mb-4">
        <span class="text-3xl">&#128274;</span>
      </div>
      <p class="text-gray-500 text-sm mb-5">{{ message }}</p>
      <label class="block text-sm font-semibold text-gray-700 mb-2">Select licence period</label>
      <div class="flex gap-2 mb-4 max-w-xs mx-auto">
        <button v-for="y in [1, 2, 3, 5]" :key="y" @click="selectedYears = y"
          class="flex-1 py-2 rounded-lg text-sm font-semibold border transition-all"
          :class="selectedYears === y ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-200'">
          {{ y }}{{ y === 1 ? ' yr' : ' yrs' }}
        </button>
      </div>
      <button @click="requestActivation" :disabled="working"
        class="w-full max-w-xs mx-auto py-3 rounded-xl bg-indigo-600 text-white font-semibold disabled:opacity-60">
        {{ working ? 'Sending...' : `Request ${selectedYears} ${selectedYears === 1 ? 'Year' : 'Years'} Licence` }}
      </button>
    </div>

    <!-- Clock tampered -->
    <div v-if="status === 'clock_tampered'" class="bg-white rounded-2xl border shadow-sm p-8 text-center max-w-lg mx-auto">
      <p class="text-sm text-gray-500">Please ensure the correct date and time is set on this PC, then restart AI Billing.</p>
    </div>

    <!-- Backup link -->
    <div class="text-center mt-6">
      <RouterLink to="/offline-backups" class="text-sm text-gray-400 hover:text-indigo-600">Backup & Restore</RouterLink>
    </div>
  </div>
</div>
</template>
