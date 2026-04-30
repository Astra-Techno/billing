<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { task, item, all } from '../../api'
import { today, addDays } from '../../utils/date'

const router = useRouter()
const route  = useRoute()
const emit   = defineEmits(['refresh'])

const clients   = ref([])
const products  = ref([])
const loading   = ref(false)
const error     = ref('')
const clientSearch = ref('')

const isEdit = computed(() => !!route.params.id)

const filteredClients = computed(() => {
  const q = clientSearch.value.trim().toLowerCase()
  if (!q) return clients.value
  return clients.value.filter(c => c.name?.toLowerCase().includes(q) || c.mobile?.includes(q))
})

const blankItem = () => ({ description: '', hsn_sac: '', unit: 'Nos', quantity: 1, product_id: null })

const units = ['Nos', 'Kg', 'Ltr', 'Hrs', 'Pcs', 'Mtr', 'Box', 'Set', 'Pair']

const form = ref({
  client_id:    '',
  challan_date: today(),
  vehicle_no:   '',
  driver_name:  '',
  destination:  '',
  notes:        '',
  items:        [blankItem()],
})

function addItem() { form.value.items.push(blankItem()) }
function removeItem(i) { if (form.value.items.length > 1) form.value.items.splice(i, 1) }

function pickProduct(i, productId) {
  const p = products.value.find(p => p.id == productId)
  if (!p) return
  const it = form.value.items[i]
  it.description = p.name
  it.unit        = p.unit || 'Nos'
  it.hsn_sac     = p.hsn_sac || ''
}

onMounted(async () => {
  const [cRes, pRes] = await Promise.all([all('Client'), all('Product')])
  clients.value  = cRes.data?.data || []
  products.value = pRes.data?.data || []

  if (isEdit.value) {
    try {
      const res = await item('DeliveryChallan', { id: route.params.id })
      const dc  = res.data?.data
      if (dc) {
        form.value.client_id    = dc.client_id
        form.value.challan_date = dc.challan_date
        form.value.vehicle_no   = dc.vehicle_no || ''
        form.value.driver_name  = dc.driver_name || ''
        form.value.destination  = dc.destination || ''
        form.value.notes        = dc.notes || ''
        if (dc.items?.length) {
          form.value.items = dc.items.map(it => ({
            description: it.description, hsn_sac: it.hsn_sac || '',
            unit: it.unit || 'Nos', quantity: it.quantity,
            product_id: it.product_id || null,
          }))
        }
      }
    } catch { error.value = 'Could not load challan data.' }
  }
})

async function submit() {
  error.value = ''
  if (!form.value.client_id) return (error.value = 'Please select a customer.')
  if (!form.value.items.some(i => i.description)) return (error.value = 'Please add at least one item.')
  loading.value = true
  try {
    if (isEdit.value) {
      await task('DeliveryChallan', 'update', { ...form.value, id: route.params.id })
    } else {
      await task('DeliveryChallan', 'create', form.value)
    }
    emit('refresh')
    router.push('/delivery-challans')
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to save. Please try again.'
  }
  loading.value = false
}
</script>

<template>
  <div class="max-w-2xl mx-auto space-y-5 pb-10">

    <!-- Header -->
    <div class="flex items-center gap-3 pt-2">
      <button @click="router.push('/delivery-challans')" class="p-2 -ml-2 rounded-full hover:bg-gray-100 transition-colors">
        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
      </button>
      <h1 class="page-title">{{ isEdit ? 'Edit Delivery Challan' : 'New Delivery Challan' }}</h1>
    </div>

    <div v-if="error" class="text-sm text-danger-600 bg-danger-50 rounded-xl px-4 py-3">{{ error }}</div>

    <!-- Customer + Date -->
    <div class="card card-body space-y-4 animate-fade-in-up">
      <h2 class="section-title">Customer & Date</h2>

      <div>
        <label class="form-label">Customer *</label>
        <input v-model="clientSearch" type="text" class="form-input mb-2" placeholder="Search customer…" />
        <select v-model="form.client_id" class="form-select">
          <option value="">Select Customer</option>
          <option v-for="c in filteredClients" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
      </div>

      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="form-label">Challan Date *</label>
          <input v-model="form.challan_date" type="date" class="form-input" />
        </div>
        <div>
          <label class="form-label">Destination</label>
          <input v-model="form.destination" type="text" class="form-input" placeholder="Delivery address / city" />
        </div>
      </div>
    </div>

    <!-- Transport Details -->
    <div class="card card-body space-y-4 animate-fade-in-up anim-delay-75">
      <h2 class="section-title">Transport Details</h2>
      <div class="grid sm:grid-cols-2 gap-4">
        <div>
          <label class="form-label">Vehicle Number</label>
          <input v-model="form.vehicle_no" type="text" class="form-input" placeholder="e.g. TN 01 AB 1234" />
        </div>
        <div>
          <label class="form-label">Driver Name</label>
          <input v-model="form.driver_name" type="text" class="form-input" placeholder="Driver's name" />
        </div>
      </div>
    </div>

    <!-- Items -->
    <div class="card card-body space-y-4 animate-fade-in-up anim-delay-75">
      <div class="flex items-center justify-between">
        <h2 class="section-title mb-0">Items</h2>
        <button @click="addItem" class="text-xs btn bg-primary-50 text-primary-700 hover:bg-primary-100 rounded-full px-3 py-1.5 font-bold">+ Add Row</button>
      </div>

      <div v-for="(it, idx) in form.items" :key="idx" class="bg-gray-50 rounded-2xl p-4 space-y-3">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Item {{ idx + 1 }}</span>
          <button v-if="form.items.length > 1" @click="removeItem(idx)" class="text-gray-400 hover:text-red-500 p-1 rounded-full transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <div>
          <label class="form-label">Product</label>
          <select v-model="it.product_id" class="form-select" @change="pickProduct(idx, it.product_id)">
            <option value="">Type manually or select…</option>
            <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
        <div>
          <label class="form-label">Description *</label>
          <input v-model="it.description" type="text" class="form-input" placeholder="Item description" />
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <div>
            <label class="form-label">Qty</label>
            <input v-model="it.quantity" type="number" min="0.01" step="0.01" class="form-input" />
          </div>
          <div>
            <label class="form-label">Unit</label>
            <select v-model="it.unit" class="form-select">
              <option v-for="u in units" :key="u">{{ u }}</option>
            </select>
          </div>
          <div>
            <label class="form-label">HSN/SAC</label>
            <input v-model="it.hsn_sac" type="text" class="form-input" placeholder="Optional" />
          </div>
        </div>
      </div>
    </div>

    <!-- Notes -->
    <div class="card card-body animate-fade-in-up anim-delay-150">
      <label class="form-label">Notes / Instructions</label>
      <textarea v-model="form.notes" rows="3" class="form-input" placeholder="Any delivery instructions…"></textarea>
    </div>

    <!-- Submit -->
    <div class="flex gap-3 animate-fade-in-up anim-delay-200">
      <button @click="router.push('/delivery-challans')" class="btn-outline flex-1">Cancel</button>
      <button @click="submit" :disabled="loading" class="btn-primary flex-1">
        {{ loading ? 'Saving…' : isEdit ? 'Update DC' : 'Create DC' }}
      </button>
    </div>
  </div>
</template>
