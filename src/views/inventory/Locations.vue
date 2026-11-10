<script setup>
import { computed, onMounted, ref } from 'vue'
import { all, task } from '../../api'
import HelpIcon from '../../components/HelpIcon.vue'

const loading = ref(true)
const busy = ref(false)
const error = ref('')
const message = ref('')
const locations = ref([])
const states = ref([])
const place = ref(blankPlace())
const filter = ref('all') // all | shop | godown

function blankPlace() {
  return { name: '', type: 'shop', address: '', gstin: '', state_id: '', city: '', pincode: '', is_default: false, active: true }
}

const filtered = computed(() => {
  if (filter.value === 'all') return locations.value
  return locations.value.filter(l => l.type === filter.value)
})

const shopCount = computed(() => locations.value.filter(l => l.type === 'shop').length)
const godownCount = computed(() => locations.value.filter(l => l.type === 'godown' || l.type === 'damaged').length)

async function load() {
  loading.value = true
  try {
    const r = await task('Inventory', 'overview')
    locations.value = r.data?.data?.locations || []
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not load locations.'
  }
  loading.value = false
}

async function save() {
  busy.value = true
  error.value = ''
  message.value = ''
  try {
    await task('Inventory', 'saveLocation', place.value)
    message.value = place.value.id ? 'Location updated.' : 'Location created.'
    place.value = blankPlace()
    await load()
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save.'
  }
  busy.value = false
}

function edit(l) {
  place.value = { id: l.id, name: l.name, type: l.type, address: l.address || '', gstin: l.gstin || '', state_id: l.state_id || '', city: l.city || '', pincode: l.pincode || '', is_default: !!+l.is_default, active: !!+l.active }
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

function cancelEdit() { place.value = blankPlace() }

async function remove(l) {
  if (!confirm(`Delete "${l.name}"? This cannot be undone.`)) return
  busy.value = true
  error.value = ''
  try {
    await task('Inventory', 'deleteLocation', { id: l.id })
    message.value = 'Location deleted.'
    await load()
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not delete.'
  }
  busy.value = false
}

onMounted(async () => {
  load()
  try { const r = await all('IndianState'); states.value = r.data?.data || [] } catch {}
})
</script>

<template>
<div class="gpay-screen">
<div class="max-w-4xl mx-auto w-full px-4 py-4 lg:px-6 lg:py-6 pb-24 space-y-5">

  <!-- Header -->
  <div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="page-title flex items-center gap-2">
      Shops & Godowns <HelpIcon section="locations" class="w-3.5 h-3.5" />
    </h1>
    <div class="flex gap-2 text-xs font-bold">
      <span class="badge badge-blue">{{ shopCount }} Shop{{ shopCount !== 1 ? 's' : '' }}</span>
      <span class="badge badge-purple">{{ godownCount }} Godown{{ godownCount !== 1 ? 's' : '' }}</span>
    </div>
  </div>

  <!-- Alerts -->
  <div v-if="error" class="card card-body !py-3 border-red-200 bg-red-50 text-sm text-red-700">{{ error }}</div>
  <div v-if="message" class="card card-body !py-3 border-green-200 bg-green-50 text-sm text-green-700">{{ message }}</div>

  <!-- Add / Edit Form -->
  <div class="card card-body space-y-4">
    <h2 class="text-base font-bold text-gray-900">{{ place.id ? 'Edit Location' : 'Add New Location' }}</h2>

    <input v-model="place.name" class="form-input" placeholder="Example: Main Shop, Central Godown" />

    <div class="grid gap-3 sm:grid-cols-2">
      <select v-model="place.type" class="form-input">
        <option value="shop">Shop / Branch</option>
        <option value="godown">Godown / Warehouse</option>
        <option value="damaged">Damaged / Returns Store</option>
      </select>
      <textarea v-model="place.address" class="form-input" rows="1" placeholder="Address (optional)"></textarea>
    </div>

    <!-- GST Settings -->
    <details class="border border-gray-200 rounded-lg">
      <summary class="px-3 py-2 text-xs font-bold text-gray-500 cursor-pointer select-none hover:bg-gray-50">GST Settings (optional — for multi-state shops)</summary>
      <div class="px-3 pb-3 pt-1 space-y-3">
        <div>
          <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">GSTIN</label>
          <input v-model="place.gstin" class="form-input" placeholder="e.g. 27AABCU9603R1ZM" maxlength="15" />
        </div>
        <div>
          <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">State</label>
          <select v-model="place.state_id" class="form-input">
            <option value="">— Use business state —</option>
            <option v-for="s in states" :key="s.id" :value="s.id">{{ s.name }}</option>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">City</label>
            <input v-model="place.city" class="form-input" placeholder="City" />
          </div>
          <div>
            <label class="block text-[11px] font-bold text-gray-400 uppercase tracking-wider mb-1">Pincode</label>
            <input v-model="place.pincode" class="form-input" placeholder="Pincode" maxlength="10" />
          </div>
        </div>
        <p class="text-[10px] text-gray-400">Leave blank to use the business-level GST identity on invoices from this shop.</p>
      </div>
    </details>

    <div class="flex flex-wrap items-center gap-4">
      <label v-if="!place.id" class="flex items-center gap-2 text-sm text-gray-700">
        <input v-model="place.is_default" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
        Use as default billing location
      </label>
      <label v-if="place.id" class="flex items-center gap-2 text-sm text-gray-700">
        <input v-model="place.active" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
        Active
      </label>
    </div>

    <div class="flex gap-2">
      <button @click="save" :disabled="busy || !place.name.trim()" class="btn-primary">{{ place.id ? 'Update' : 'Add' }} Location</button>
      <button v-if="place.id" @click="cancelEdit" class="btn-secondary">Cancel</button>
    </div>
  </div>

  <!-- Filter tabs -->
  <div class="flex gap-1.5">
    <button v-for="t in [{key:'all',label:'All'},{key:'shop',label:'Shops'},{key:'godown',label:'Godowns'}]" :key="t.key"
      @click="filter = t.key"
      class="px-4 py-2 rounded-xl text-sm font-semibold whitespace-nowrap transition-all duration-150 shrink-0"
      :class="filter === t.key
        ? 'bg-primary-600 text-white shadow-sm shadow-primary-200'
        : 'bg-white text-gray-500 border border-gray-200 hover:border-gray-300 hover:text-gray-700'">
      {{ t.label }}
    </button>
  </div>

  <!-- Loading -->
  <div v-if="loading" class="card p-10 text-center text-gray-400 text-sm">Loading locations...</div>

  <!-- Location Cards -->
  <div v-else-if="!filtered.length" class="card p-10 text-center text-sm text-gray-400">
    No {{ filter === 'all' ? 'locations' : filter === 'shop' ? 'shops' : 'godowns' }} yet. Add one above.
  </div>

  <div v-else class="grid gap-3 sm:grid-cols-2">
    <div v-for="l in filtered" :key="l.id"
      class="card card-body relative overflow-hidden transition-all hover:shadow-md"
      :class="!+l.active ? 'opacity-60' : ''">

      <!-- Type indicator stripe -->
      <div class="absolute top-0 left-0 w-1 h-full"
        :class="l.type === 'shop' ? 'bg-blue-500' : l.type === 'godown' ? 'bg-purple-500' : 'bg-orange-500'"></div>

      <div class="pl-3">
        <div class="flex items-start justify-between gap-2">
          <div>
            <p class="text-[15px] font-bold text-gray-900">{{ l.name }}</p>
            <p class="text-[11px] text-gray-500 capitalize mt-0.5">
              {{ l.type === 'damaged' ? 'Damaged / Returns' : l.type }}
              <span v-if="+l.is_default" class="text-primary-600 font-bold"> · Default</span>
            </p>
          </div>
          <span class="badge shrink-0" :class="+l.active ? 'badge-green' : 'badge-gray'">
            {{ +l.active ? 'Active' : 'Inactive' }}
          </span>
        </div>

        <div v-if="l.address || l.gstin || l.state_name || l.city" class="mt-2 space-y-0.5 text-[11px] text-gray-500">
          <p v-if="l.address">{{ l.address }}</p>
          <p v-if="l.city || l.pincode">{{ [l.city, l.pincode].filter(Boolean).join(' - ') }}</p>
          <p v-if="l.gstin" class="font-mono">GSTIN: {{ l.gstin }}</p>
          <p v-if="l.state_name">State: {{ l.state_name }}</p>
        </div>

        <div class="flex gap-3 mt-3 pt-2 border-t border-gray-100">
          <button @click="edit(l)" class="text-primary-600 hover:text-primary-800 text-xs font-bold">Edit</button>
          <button v-if="!+l.is_default" @click="remove(l)" class="text-red-500 hover:text-red-700 text-xs font-bold">Delete</button>
        </div>
      </div>
    </div>
  </div>

</div>
</div>
</template>
