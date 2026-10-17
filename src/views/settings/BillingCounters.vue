<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { task } from '../../api'

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const notice = ref('')
const data = ref({ counters: [], devices: [], locations: [], pairing: null, shifts: [] })
const pairingCode = ref('')
const pairingSeconds = ref(0)
const counterAssignments = reactive({})
const mainPcMode = window.__BILLING_NETWORK_MODE__ === 'MainPc'
const form = reactive({ id: null, name: '', code: '', location_id: '', invoice_prefix: '' })

const activeDevices = computed(() => data.value.devices.filter(d => d.status === 'active').length)
const pendingDevices = computed(() => data.value.devices.filter(d => d.status === 'pending').length)
const pairingTime = computed(() => `${Math.floor(pairingSeconds.value / 60)}:${String(pairingSeconds.value % 60).padStart(2, '0')}`)

async function load() {
  loading.value = true; error.value = ''
  try { const [overview, shifts] = await Promise.all([task('BillingCounter', 'overview'), task('CashierShift', 'history')]); data.value = { ...overview.data.data, shifts: shifts.data.data || [] } }
  catch (e) { error.value = e.response?.data?.message || 'Unable to load billing counters.' }
  finally { loading.value = false }
}

function edit(row) {
  Object.assign(form, { id: row.id, name: row.name, code: row.code, location_id: row.location_id || '', invoice_prefix: row.invoice_prefix || '' })
  document.getElementById('counter-form')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
}
function clearForm() { Object.assign(form, { id: null, name: '', code: '', location_id: '', invoice_prefix: '' }) }

async function save() {
  saving.value = true; error.value = ''; notice.value = ''
  try {
    const response = await task('BillingCounter', 'saveCounter', { ...form })
    notice.value = response.data.message; clearForm(); await load()
  } catch (e) { error.value = e.response?.data?.message || 'Unable to save billing counter.' }
  finally { saving.value = false }
}

async function toggleCounter(row) {
  if (row.active && !window.confirm(`Deactivate ${row.name}? Its connected devices will also be made inactive.`)) return
  try { await task('BillingCounter', 'setCounterStatus', { id: row.id, active: !Number(row.active) }); await load() }
  catch (e) { error.value = e.response?.data?.message || 'Unable to update counter.' }
}

async function startPairing() {
  error.value = ''; notice.value = ''
  try {
    const result = (await task('BillingCounter', 'startPairing')).data.data
    pairingCode.value = result.pairing_code
    pairingSeconds.value = result.expires_in
    const timer = window.setInterval(() => {
      pairingSeconds.value--
      if (pairingSeconds.value <= 0) { window.clearInterval(timer); pairingCode.value = '' }
    }, 1000)
  } catch (e) { error.value = e.response?.data?.message || 'Unable to create pairing code.' }
}

async function changeDevice(device, status) {
  if (status === 'revoked' && !window.confirm(`Remove access for ${device.device_name}?`)) return
  try { await task('BillingCounter', 'setDeviceStatus', { id: device.id, status }); await load() }
  catch (e) { error.value = e.response?.data?.message || 'Unable to update device.' }
}

async function approveDevice(device) {
  const counterId = Number(counterAssignments[device.id] || 0)
  if (!counterId) { error.value = 'Choose a billing counter before approving this device.'; return }
  try { notice.value = (await task('BillingCounter', 'approvePairing', { device_id: device.id, counter_id: counterId })).data.message; await load() }
  catch (e) { error.value = e.response?.data?.message || 'Unable to approve device.' }
}

async function rejectDevice(device) {
  if (!window.confirm(`Reject the connection request from ${device.device_name}?`)) return
  try { notice.value = (await task('BillingCounter', 'rejectPairing', { device_id: device.id })).data.message; await load() }
  catch (e) { error.value = e.response?.data?.message || 'Unable to reject device.' }
}

let refreshTimer
onMounted(() => { load(); refreshTimer = window.setInterval(async () => { try { data.value = { ...data.value, ...(await task('BillingCounter', 'overview')).data.data } } catch {} }, 5000) })
onBeforeUnmount(() => window.clearInterval(refreshTimer))
</script>

<template>
  <div class="page-shell">
    <div class="page-heading">
      <div><p class="eyebrow">SHOP NETWORK</p><h1>Billing Counters</h1><p>Manage the Main PC and every billing system connected inside your shop.</p></div>
      <button class="btn-primary" :disabled="!mainPcMode" :title="mainPcMode ? 'Create a secure pairing code' : 'Configure this installation as the Main PC first'" @click="startPairing">+ Connect a counter</button>
    </div>

    <div v-if="error" class="alert error">{{ error }}</div>
    <div v-if="notice" class="alert success">{{ notice }}</div>
    <div v-if="!mainPcMode" class="alert info">Counter management is ready. To connect other systems, reinstall or launch this installation in <strong>Main PC</strong> mode.</div>

    <section v-if="pairingCode" class="pair-card">
      <div><span>PAIRING CODE</span><strong>{{ pairingCode }}</strong><small>Valid for {{ pairingTime }}. Enter this only on the new Counter PC.</small></div>
      <div class="pair-steps"><b>On the new billing system</b><ol><li>Install AI Billing and choose <strong>Billing Counter</strong>.</li><li>Select this shop and enter the code.</li><li>Return here to approve the device.</li></ol></div>
    </section>

    <div class="stats">
      <article><span>Active counters</span><strong>{{ data.counters.filter(c => Number(c.active)).length }}</strong></article>
      <article><span>Connected devices</span><strong>{{ activeDevices }}</strong></article>
      <article><span>Waiting for approval</span><strong>{{ pendingDevices }}</strong></article>
      <article><span>Network mode</span><strong class="mode">Safe LAN</strong></article>
    </div>

    <section class="panel">
      <div class="panel-title"><div><h2>Your billing counters</h2><p>Create a name customers and staff can easily identify.</p></div></div>
      <div v-if="loading" class="empty">Loading counters…</div>
      <div v-else-if="!data.counters.length" class="empty">No counters added yet. Add Counter 1 below.</div>
      <div v-else class="counter-grid">
        <article v-for="counter in data.counters" :key="counter.id" class="counter-card" :class="{ muted: !Number(counter.active) }">
          <div class="counter-icon">▣</div><div class="grow"><div class="row"><h3>{{ counter.name }}</h3><span :class="Number(counter.active) ? 'on' : 'off'">{{ Number(counter.active) ? 'Active' : 'Inactive' }}</span></div><p>{{ counter.code }} · {{ counter.location_name || 'All shop locations' }}</p><small>{{ counter.device_count }} device(s) · {{ counter.last_seen_at ? `Last connected ${counter.last_seen_at}` : 'Not connected yet' }}</small></div>
          <button class="link" @click="edit(counter)">Edit</button><button class="link danger" @click="toggleCounter(counter)">{{ Number(counter.active) ? 'Deactivate' : 'Activate' }}</button>
        </article>
      </div>
    </section>

    <section id="counter-form" class="panel form-panel">
      <div class="panel-title"><div><h2>{{ form.id ? 'Edit counter' : 'Add a billing counter' }}</h2><p>The invoice prefix is optional. Leave it blank to use your normal invoice series.</p></div></div>
      <form class="form-grid" @submit.prevent="save">
        <label><span>Counter name</span><input v-model.trim="form.name" required maxlength="100" placeholder="Example: Counter 1"><small>Shown to the owner and cashier.</small></label>
        <label><span>Counter code</span><input v-model.trim="form.code" maxlength="20" placeholder="Automatically created"><small>Short code such as C01.</small></label>
        <label><span>Shop location</span><select v-model="form.location_id"><option value="">All locations</option><option v-for="location in data.locations" :key="location.id" :value="location.id">{{ location.name }}</option></select><small>Stock will be taken from this shop.</small></label>
        <label><span>Invoice prefix (optional)</span><input v-model.trim="form.invoice_prefix" maxlength="20" placeholder="Example: C1"><small>Use only if each counter needs a separate bill series.</small></label>
        <div class="form-actions"><button v-if="form.id" type="button" class="btn-light" @click="clearForm">Cancel</button><button class="btn-primary" :disabled="saving">{{ saving ? 'Saving…' : 'Save counter' }}</button></div>
      </form>
    </section>

    <section class="panel">
      <div class="panel-title"><div><h2>Connected systems</h2><p>Deactivate a replaced PC or permanently revoke a lost device.</p></div></div>
      <div v-if="!data.devices.length" class="empty">No Counter PCs have requested access.</div>
      <div v-else class="device-list"><article v-for="device in data.devices" :key="device.id"><span class="device-dot" :class="device.status"></span><div class="grow"><h3>{{ device.device_name }}</h3><p>{{ device.counter_name || 'Counter not assigned' }} · {{ device.platform || 'Windows' }} · {{ device.status }}</p><small>Last connected: {{ device.last_seen_at || 'Never' }}</small></div><template v-if="device.status === 'pending'"><select v-model="counterAssignments[device.id]" class="assign-select"><option value="">Choose counter</option><option v-for="counter in data.counters.filter(c => Number(c.active))" :key="counter.id" :value="counter.id">{{ counter.name }}</option></select><button class="link approve" @click="approveDevice(device)">Approve</button><button class="link danger" @click="rejectDevice(device)">Reject</button></template><button v-if="device.status === 'active'" class="link" @click="changeDevice(device, 'inactive')">Deactivate</button><button v-if="device.status === 'inactive'" class="link" @click="changeDevice(device, 'active')">Activate</button><button v-if="!['pending','revoked'].includes(device.status)" class="link danger" @click="changeDevice(device, 'revoked')">Revoke</button></article></div>
    </section>
    <section class="panel">
      <div class="panel-title"><div><h2>Cashier shift reconciliation</h2><p>Check counter collections and extra or short drawer cash.</p></div></div>
      <div v-if="!data.shifts?.length" class="empty">No cashier shifts recorded yet.</div>
      <div v-else class="shift-list"><article v-for="shift in data.shifts" :key="shift.id"><div class="grow"><div class="row"><h3>{{shift.counter_name}} · {{shift.cashier_name}}</h3><span :class="shift.status==='open'?'on':'off'">{{shift.status}}</span></div><p>{{shift.opened_at}}<template v-if="shift.closed_at"> → {{shift.closed_at}}</template> · {{shift.invoice_count}} bills</p></div><div><small>Cash sales</small><b>₹{{Number(shift.cash_sales||0).toFixed(2)}}</b></div><div><small>Expected</small><b>₹{{Number(shift.expected_cash||0).toFixed(2)}}</b></div><div><small>Difference</small><b :class="Number(shift.cash_variance||0)===0?'ok':'difference'">₹{{Number(shift.cash_variance||0).toFixed(2)}}</b></div></article></div>
    </section>
  </div>
</template>

<style scoped>
.assign-select{max-width:160px;border:1px solid #d1d5db;border-radius:8px;padding:7px 9px;background:white;font-size:12px}.link.approve{color:#047857}.alert.info{background:#eff6ff;color:#1d4ed8}
.shift-list{display:grid;gap:8px}.shift-list article{display:grid;grid-template-columns:minmax(260px,1fr) repeat(3,120px);gap:12px;align-items:center;padding:12px;border:1px solid #e5e7eb;border-radius:11px}.shift-list h3{font-size:13px;color:#111827}.shift-list p{font-size:11px;color:#64748b}.shift-list small{display:block;color:#94a3b8;font-size:10px}.shift-list b{font-size:12px;color:#111827}.shift-list .ok{color:#047857}.shift-list .difference{color:#dc2626}
.page-shell{max-width:1180px;margin:auto;padding:28px}.page-heading{display:flex;align-items:center;justify-content:space-between;gap:24px;margin-bottom:24px}.page-heading h1{font-size:30px;line-height:1.15;margin:4px 0 7px;color:#111827}.page-heading p{color:#64748b;margin:0}.eyebrow{font-size:11px!important;font-weight:800;letter-spacing:.15em;color:#4f46e5!important}.btn-primary,.btn-light{border:0;border-radius:10px;padding:11px 17px;font-weight:700;cursor:pointer}.btn-primary{background:#4f46e5;color:white;box-shadow:0 5px 14px #4f46e52d}.btn-primary:disabled{opacity:.55}.btn-light{background:#eef2ff;color:#4338ca}.alert{padding:12px 15px;border-radius:10px;margin-bottom:16px;font-size:14px}.alert.error{background:#fff1f2;color:#be123c}.alert.success{background:#ecfdf5;color:#047857}.pair-card{display:grid;grid-template-columns:.8fr 1.2fr;gap:30px;padding:24px;border-radius:18px;background:linear-gradient(135deg,#111827,#1e1b4b);color:white;margin-bottom:20px}.pair-card span,.pair-card small{display:block;color:#c7d2fe}.pair-card span{font-size:11px;letter-spacing:.15em;font-weight:800}.pair-card strong{display:block;font-size:44px;letter-spacing:.2em;margin:5px 0}.pair-steps{border-left:1px solid #ffffff2d;padding-left:28px}.pair-steps ol{margin:8px 0 0;padding-left:18px;color:#dbeafe}.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}.stats article,.panel{background:white;border:1px solid #e5e7eb;border-radius:15px;box-shadow:0 4px 18px #0f172a0a}.stats article{padding:17px}.stats span{display:block;color:#64748b;font-size:12px}.stats strong{font-size:25px;color:#111827}.stats .mode{font-size:18px;color:#047857}.panel{padding:22px;margin-bottom:18px}.panel-title{display:flex;justify-content:space-between;margin-bottom:18px}.panel-title h2{font-size:18px;margin:0;color:#111827}.panel-title p{font-size:13px;color:#64748b;margin:4px 0 0}.counter-grid,.device-list{display:grid;gap:10px}.counter-card,.device-list article{display:flex;align-items:center;gap:12px;border:1px solid #e5e7eb;border-radius:12px;padding:14px}.counter-card.muted{opacity:.6}.counter-icon{width:42px;height:42px;border-radius:11px;background:#eef2ff;color:#4f46e5;display:grid;place-items:center;font-size:21px}.grow{flex:1;min-width:0}.row{display:flex;align-items:center;gap:9px}.counter-card h3,.device-list h3{font-size:14px;margin:0;color:#111827}.counter-card p,.device-list p{font-size:12px;color:#64748b;margin:3px 0}.counter-card small,.device-list small{font-size:11px;color:#94a3b8}.on,.off{font-size:10px;font-weight:800;padding:3px 7px;border-radius:20px}.on{background:#dcfce7;color:#15803d}.off{background:#f1f5f9;color:#64748b}.link{border:0;background:none;color:#4f46e5;font-size:12px;font-weight:700;cursor:pointer;padding:6px}.link.danger{color:#dc2626}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}.form-grid label span{display:block;font-size:12px;font-weight:700;color:#374151;margin-bottom:6px}.form-grid input,.form-grid select{width:100%;border:1px solid #d1d5db;border-radius:9px;padding:10px 11px;background:white}.form-grid label small{display:block;color:#94a3b8;font-size:11px;margin-top:5px}.form-actions{grid-column:1/-1;display:flex;justify-content:flex-end;gap:8px}.empty{text-align:center;padding:28px;color:#94a3b8;font-size:13px}.device-dot{width:10px;height:10px;border-radius:50%;background:#94a3b8}.device-dot.active{background:#22c55e}.device-dot.pending{background:#f59e0b}.device-dot.revoked{background:#ef4444}@media(max-width:760px){.page-shell{padding:16px}.page-heading{align-items:flex-start;flex-direction:column}.stats{grid-template-columns:1fr 1fr}.pair-card,.form-grid{grid-template-columns:1fr}.pair-steps{border-left:0;border-top:1px solid #ffffff2d;padding:18px 0 0}.counter-card{flex-wrap:wrap}}
</style>
