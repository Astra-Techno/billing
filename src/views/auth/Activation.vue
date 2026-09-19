<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { task } from '../../api'
import AppLogo from '../../components/AppLogo.vue'

const router=useRouter(),status=ref('checking'),message=ref('Checking this PC…'),working=ref(false),expiresAt=ref(null);let timer
async function check(){try{const current=await task('DesktopLicense','localStatus');const d=current.data.data;if(d.active){status.value='active';message.value='This PC is activated.';if(d.expires_at_human)expiresAt.value=d.expires_at_human;clearInterval(timer);setTimeout(()=>router.replace('/'),800);return}
if(d.reason==='expired'){status.value='expired';message.value='Your licence has expired. Please request re-activation from your CloudKart administrator.';clearInterval(timer);return}
if(d.reason==='clock_tampered'){status.value='clock_tampered';message.value='System clock change detected. Licence verification failed. Contact CloudKart support to resolve this.';clearInterval(timer);return}
const poll=await task('DesktopLicense','localPoll');const value=poll.data.data?.status;if(poll.data.data?.active||value==='active'){status.value='active';message.value='Activation approved. Opening AI Billing…';clearInterval(timer);setTimeout(()=>router.replace('/'),800)}else if(value==='rejected'||value==='expired'){status.value=value;message.value=value==='rejected'?'Activation was rejected. Contact CloudKart support.':'Activation request expired. Submit a new request.'}else if(value==='pending'){status.value='pending';message.value='Waiting for approval from the CloudKart administrator.'}else{status.value='ready';message.value='This PC has not been activated.'}}catch(e){status.value='ready';message.value=e.response?.data?.message||'Connect to the internet and try again.'}}
async function requestActivation(){working.value=true;try{await task('DesktopLicense','localRequest');status.value='pending';message.value='Request sent. Waiting for CloudKart administrator approval.';await check()}catch(e){message.value=e.response?.data?.message||'Could not send activation request.'}finally{working.value=false}}
onMounted(()=>{check();timer=setInterval(check,5000)});onUnmounted(()=>clearInterval(timer))

const statusStyle={
  active: 'bg-green-50 text-green-700',
  pending: 'bg-amber-50 text-amber-800',
  checking: 'bg-blue-50 text-blue-700',
  expired: 'bg-red-50 text-red-700',
  clock_tampered: 'bg-red-50 text-red-700',
}
</script>
<template><div class="min-h-screen bg-slate-50 flex items-center justify-center p-5"><div class="w-full max-w-lg bg-white rounded-2xl border border-gray-200 shadow-xl p-8 text-center"><div class="flex justify-center mb-5"><AppLogo size="lg"/></div><h1 class="text-2xl font-bold text-gray-900">{{ status === 'expired' ? 'Licence Expired' : status === 'clock_tampered' ? 'Verification Failed' : 'Activate AI Billing Offline' }}</h1><p class="text-gray-500 mt-2">{{ status === 'expired' ? 'Your licence period has ended. Request a new activation to continue using AI Billing.' : status === 'clock_tampered' ? 'The system clock appears to have been changed. Contact CloudKart support.' : 'This licence is assigned to one PC and managed securely by billing.cloudkart24.com.' }}</p><div class="my-7 rounded-xl p-4" :class="statusStyle[status] || 'bg-blue-50 text-blue-700'"><span v-if="status==='checking'||status==='pending'" class="inline-block w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin mr-2 align-[-2px]"></span>{{message}}</div>

<!-- Expired: show re-activation button -->
<button v-if="status==='expired'" @click="requestActivation" :disabled="working" class="w-full py-3 rounded-xl bg-indigo-600 text-white font-semibold disabled:opacity-60">{{working?'Sending request…':'Request Re-activation'}}</button>

<!-- Clock tampered: only show contact info -->
<p v-else-if="status==='clock_tampered'" class="text-sm text-gray-500">Please ensure the correct date and time is set on this PC, then restart AI Billing.</p>

<!-- Normal: activate button -->
<button v-else-if="!['pending','active','checking'].includes(status)" @click="requestActivation" :disabled="working" class="w-full py-3 rounded-xl bg-indigo-600 text-white font-semibold disabled:opacity-60">{{working?'Sending request…':'Activate this PC'}}</button>
<button v-if="status==='pending'" @click="check" class="w-full py-3 rounded-xl border border-indigo-200 text-indigo-700 font-semibold">Check approval now</button>
<RouterLink to="/offline-backups" class="inline-block mt-5 text-sm text-gray-500 hover:text-indigo-600">Backup & Restore</RouterLink></div></div></template>
