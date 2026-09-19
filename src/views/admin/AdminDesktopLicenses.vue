<script setup>
import { computed, onMounted, ref } from 'vue'
import { task } from '../../api'

const tab=ref('requests'), requests=ref([]), licenses=ref([]), stats=ref({}), loading=ref(true), busy=ref(null), error=ref('')
const approveModal=ref(null), approveYears=ref(1), expandedLicense=ref(null)
const pending=computed(()=>requests.value.filter(r=>r.status==='pending'))
onMounted(load)
async function load(){ loading.value=true;error.value='';try{const [s,r,l]=await Promise.all([task('Admin','desktopLicenseStats'),task('Admin','desktopActivationRequests'),task('Admin','desktopLicenses')]);stats.value=s.data.data||{};requests.value=r.data.data||[];licenses.value=l.data.data||[]}catch(e){error.value=e.response?.data?.message||'Could not load desktop licences.'}finally{loading.value=false}}
function showApproveModal(row){approveModal.value=row;approveYears.value=1}
async function confirmApprove(){const row=approveModal.value;if(!row)return;busy.value='r'+row.id;error.value='';try{await task('Admin','approveDesktopActivation',{request_id:row.id,edition:'offline-single-pc',years:approveYears.value});approveModal.value=null;await load()}catch(e){error.value=e.response?.data?.message||'Approval failed.'}finally{busy.value=null}}
async function reject(row){const reason=window.prompt('Reason for rejecting this activation request:');if(!reason)return;busy.value='r'+row.id;try{await task('Admin','rejectDesktopActivation',{request_id:row.id,reason});await load()}catch(e){error.value=e.response?.data?.message||'Rejection failed.'}finally{busy.value=null}}
async function setStatus(row,status){let reason='';if(status!=='active'){reason=window.prompt(`Reason for ${status}:`)||'';if(!reason)return}if(!window.confirm(`${status==='active'?'Reactivate':'Set'} licence ${row.license_uuid} ${status}?`))return;busy.value='l'+row.id;try{await task('Admin','setDesktopLicenseStatus',{license_id:row.id,status,reason});await load()}catch(e){error.value=e.response?.data?.message||'Licence update failed.'}finally{busy.value=null}}
function toggleExpand(id){expandedLicense.value=expandedLicense.value===id?null:id}
function date(value){return value?new Date(value).toLocaleString('en-IN'):'—'}
function dateShort(value){return value?new Date(value).toLocaleDateString('en-IN',{day:'numeric',month:'short',year:'numeric'}):'—'}
function licenseYears(row){if(!row.issued_at||!row.expires_at)return null;const ms=new Date(row.expires_at)-new Date(row.issued_at);return Math.round(ms/(365.25*24*60*60*1000))}
function daysLeft(row){if(!row.expires_at)return null;const ms=new Date(row.expires_at)-new Date();return Math.ceil(ms/(24*60*60*1000))}
const badge={pending:'bg-amber-100 text-amber-700',approved:'bg-blue-100 text-blue-700',consumed:'bg-green-100 text-green-700',active:'bg-green-100 text-green-700',suspended:'bg-amber-100 text-amber-700',revoked:'bg-red-100 text-red-700',expired:'bg-gray-100 text-gray-600',rejected:'bg-red-100 text-red-700'}
</script>

<template>
<div class="flex flex-col h-full bg-gray-50">
  <div class="px-4 lg:px-6 py-4 border-b bg-white flex items-center gap-3">
    <RouterLink to="/admin" class="text-gray-500 hover:text-gray-900">←</RouterLink>
    <div><h1 class="text-base font-bold text-gray-900">Desktop Licences</h1><p class="text-xs text-gray-400">Cloud approval and offline PC control</p></div>
    <button class="ml-auto px-3 py-2 rounded-lg border text-xs font-semibold bg-white" @click="load">Refresh</button>
  </div>
  <div class="p-4 lg:p-6 overflow-y-auto flex-1">
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
      <div v-for="(label,key) in {pending:'Pending',active:'Active',expiring:'Expiring',suspended:'Suspended',revoked:'Revoked'}" :key="key" class="bg-white rounded-xl border p-4"><p class="text-xs text-gray-500">{{label}}</p><p class="text-2xl font-bold mt-1">{{stats[key]||0}}</p></div>
    </div>
    <p v-if="error" role="alert" class="mb-4 bg-red-50 border border-red-100 text-red-700 rounded-xl px-4 py-3 text-sm">{{error}}</p>
    <div class="flex gap-2 mb-4"><button v-for="item in [{k:'requests',n:`Requests (${pending.length})`},{k:'licenses',n:`Licences (${licenses.length})`}]" :key="item.k" @click="tab=item.k" class="px-4 py-2 rounded-lg text-sm font-semibold" :class="tab===item.k?'bg-indigo-600 text-white':'bg-white border text-gray-600'">{{item.n}}</button></div>
    <div v-if="loading" class="bg-white rounded-xl border p-8 text-center text-gray-400">Loading…</div>

    <!-- Requests tab -->
    <div v-else-if="tab==='requests'" class="space-y-3">
      <div v-if="!requests.length" class="bg-white rounded-xl border p-8 text-center text-gray-400">No activation requests</div>
      <div v-for="row in requests" :key="row.id" class="bg-white rounded-xl border p-4 flex flex-col lg:flex-row lg:items-center gap-4">
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2"><p class="font-semibold text-gray-900">{{row.company?.name||'Unnamed company'}}</p><span class="text-[11px] px-2 py-0.5 rounded-full font-semibold" :class="badge[row.status]">{{row.status}}</span></div>
          <p class="text-sm text-gray-600">{{row.user?.name}} · {{row.user?.email}}<template v-if="row.user?.mobile"> · {{row.user.mobile}}</template></p>
          <p v-if="row.company?.mobile || row.company?.gstin" class="text-sm text-gray-500"><template v-if="row.company?.mobile">Mobile: {{row.company.mobile}}</template><template v-if="row.company?.gstin"> · GSTIN: {{row.company.gstin}}</template></p>
          <p class="text-xs text-gray-400 mt-1">{{row.device?.pc_name}} · {{row.device?.windows_version}} · {{row.device_id}} · {{date(row.created_at)}}</p>
        </div>
        <div v-if="row.status==='pending'" class="flex gap-2"><button :disabled="busy==='r'+row.id" @click="reject(row)" class="px-3 py-2 rounded-lg bg-red-50 text-red-700 text-sm font-semibold">Reject</button><button :disabled="busy==='r'+row.id" @click="showApproveModal(row)" class="px-3 py-2 rounded-lg bg-green-600 text-white text-sm font-semibold">Approve PC</button></div>
      </div>
    </div>

    <!-- Licences tab -->
    <div v-else class="space-y-3">
      <div v-if="!licenses.length" class="bg-white rounded-xl border p-8 text-center text-gray-400">No desktop licences</div>
      <div v-for="row in licenses" :key="row.id" class="bg-white rounded-xl border overflow-hidden">
        <!-- Summary row -->
        <div class="p-4 flex flex-col lg:flex-row lg:items-center gap-3 cursor-pointer hover:bg-gray-50/50 transition" @click="toggleExpand(row.id)">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <p class="font-semibold text-gray-900">{{row.customer?.company?.name||'Unnamed company'}}</p>
              <span class="text-[11px] px-2 py-0.5 rounded-full font-semibold" :class="badge[row.status]">{{row.status}}</span>
              <template v-if="row.expires_at">
                <span v-if="daysLeft(row)!==null && daysLeft(row)<=0" class="text-[11px] px-2 py-0.5 rounded-full font-semibold bg-red-100 text-red-700">Expired</span>
                <span v-else-if="daysLeft(row)!==null && daysLeft(row)<=90" class="text-[11px] px-2 py-0.5 rounded-full font-semibold bg-amber-100 text-amber-700">{{daysLeft(row)}} days left</span>
              </template>
              <template v-if="licenseYears(row)">
                <span class="text-[11px] px-2 py-0.5 rounded-full font-semibold bg-indigo-50 text-indigo-600">{{licenseYears(row)}} {{licenseYears(row)===1?'year':'years'}}</span>
              </template>
            </div>
            <p class="text-sm text-gray-600 mt-0.5">{{row.customer?.user?.name}} · {{row.customer?.user?.email}}<template v-if="row.customer?.user?.mobile"> · {{row.customer.user.mobile}}</template></p>
            <div class="flex flex-wrap gap-x-4 gap-y-0.5 mt-1 text-xs text-gray-400">
              <span>Installed: {{dateShort(row.issued_at)}}</span>
              <span v-if="row.expires_at">Expires: {{dateShort(row.expires_at)}}</span>
              <span v-else class="text-green-600">No expiry</span>
              <span>{{row.device?.device?.pc_name}}</span>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <div class="flex gap-2">
              <button v-if="row.status!=='active'" @click.stop="setStatus(row,'active')" class="px-3 py-2 rounded-lg bg-green-50 text-green-700 text-sm font-semibold">Reactivate</button>
              <button v-if="row.status==='active'" @click.stop="setStatus(row,'suspended')" class="px-3 py-2 rounded-lg bg-amber-50 text-amber-700 text-sm font-semibold">Suspend</button>
              <button v-if="row.status!=='revoked'" @click.stop="setStatus(row,'revoked')" class="px-3 py-2 rounded-lg bg-red-50 text-red-700 text-sm font-semibold">Revoke</button>
            </div>
            <svg class="w-5 h-5 text-gray-400 transition-transform shrink-0" :class="expandedLicense===row.id?'rotate-180':''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
          </div>
        </div>

        <!-- Expanded detail panel -->
        <div v-if="expandedLicense===row.id" class="border-t bg-gray-50/70 px-4 py-4">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Customer info -->
            <div>
              <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Customer</h4>
              <div class="space-y-1.5 text-sm">
                <p><span class="text-gray-400">Name:</span> <span class="text-gray-800 font-medium">{{row.customer?.user?.name||'—'}}</span></p>
                <p><span class="text-gray-400">Email:</span> <span class="text-gray-800">{{row.customer?.user?.email||'—'}}</span></p>
                <p><span class="text-gray-400">Mobile:</span> <span class="text-gray-800">{{row.customer?.user?.mobile||'—'}}</span></p>
              </div>
            </div>
            <!-- Company info -->
            <div>
              <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Company</h4>
              <div class="space-y-1.5 text-sm">
                <p><span class="text-gray-400">Name:</span> <span class="text-gray-800 font-medium">{{row.customer?.company?.name||'—'}}</span></p>
                <p><span class="text-gray-400">Mobile:</span> <span class="text-gray-800">{{row.customer?.company?.mobile||'—'}}</span></p>
                <p><span class="text-gray-400">Email:</span> <span class="text-gray-800">{{row.customer?.company?.email||'—'}}</span></p>
                <p><span class="text-gray-400">GSTIN:</span> <span class="text-gray-800">{{row.customer?.company?.gstin||'—'}}</span></p>
                <p><span class="text-gray-400">Type:</span> <span class="text-gray-800">{{row.customer?.company?.business_type||'—'}}</span></p>
              </div>
            </div>
            <!-- Licence & device info -->
            <div>
              <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Licence & Device</h4>
              <div class="space-y-1.5 text-sm">
                <p><span class="text-gray-400">Licence ID:</span> <span class="text-gray-800 font-mono text-xs">{{row.license_uuid}}</span></p>
                <p><span class="text-gray-400">Edition:</span> <span class="text-gray-800">{{row.edition||'—'}}</span></p>
                <p><span class="text-gray-400">Issued:</span> <span class="text-gray-800">{{date(row.issued_at)}}</span></p>
                <p><span class="text-gray-400">Expires:</span> <span class="font-medium" :class="daysLeft(row)!==null&&daysLeft(row)<=0?'text-red-600':daysLeft(row)!==null&&daysLeft(row)<=90?'text-amber-600':'text-gray-800'">{{row.expires_at?date(row.expires_at):'Never'}}</span>
                  <template v-if="licenseYears(row)"> <span class="text-gray-400">({{licenseYears(row)}} {{licenseYears(row)===1?'year':'years'}})</span></template>
                </p>
                <p v-if="daysLeft(row)!==null && daysLeft(row)>0"><span class="text-gray-400">Days left:</span> <span class="text-gray-800 font-medium">{{daysLeft(row)}}</span></p>
                <p><span class="text-gray-400">PC:</span> <span class="text-gray-800">{{row.device?.device?.pc_name||'—'}}</span></p>
                <p><span class="text-gray-400">Windows:</span> <span class="text-gray-800">{{row.device?.device?.windows_version||'—'}}</span></p>
                <p><span class="text-gray-400">User:</span> <span class="text-gray-800">{{row.device?.device?.windows_user||'—'}}</span></p>
                <p><span class="text-gray-400">Device ID:</span> <span class="text-gray-800 font-mono text-xs">{{row.device?.device_id||'—'}}</span></p>
                <p v-if="row.approved_by_name"><span class="text-gray-400">Approved by:</span> <span class="text-gray-800">{{row.approved_by_name}}</span></p>
                <p v-if="row.revoked_by_name"><span class="text-gray-400">Revoked by:</span> <span class="text-red-600">{{row.revoked_by_name}}</span></p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Approve modal with years selector -->
  <teleport to="body">
    <div v-if="approveModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="approveModal=null">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 p-6">
        <h2 class="text-lg font-bold text-gray-900 mb-1">Approve Activation</h2>
        <p class="text-sm text-gray-500 mb-5">{{ approveModal.company?.name || 'Unnamed company' }} — {{ approveModal.user?.name }}</p>

        <label class="block text-sm font-semibold text-gray-700 mb-2">Licence validity (years)</label>
        <div class="flex gap-2 mb-6">
          <button v-for="y in [1, 2, 3, 5]" :key="y" @click="approveYears=y"
            class="flex-1 py-2.5 rounded-xl text-sm font-semibold border transition-all"
            :class="approveYears===y ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-200 hover:border-gray-300'">
            {{ y }} {{ y === 1 ? 'year' : 'years' }}
          </button>
        </div>

        <div class="flex gap-3">
          <button @click="approveModal=null" class="flex-1 py-2.5 rounded-xl border text-sm font-semibold text-gray-600">Cancel</button>
          <button @click="confirmApprove" :disabled="busy" class="flex-1 py-2.5 rounded-xl bg-green-600 text-white text-sm font-semibold disabled:opacity-60">
            {{ busy ? 'Approving…' : `Approve for ${approveYears} ${approveYears===1?'year':'years'}` }}
          </button>
        </div>
      </div>
    </div>
  </teleport>
</div>
</template>
