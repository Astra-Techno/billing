<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { item, task, all } from '../../api'
import HelpIcon from '../../components/HelpIcon.vue'

const router = useRouter()
const route  = useRoute()

const taxRates = ref([])
const loading  = ref(false)
const saving   = ref(false)
const error    = ref('')

const isEdit = route.params.id && route.params.id !== 'new'
const productId = isEdit ? route.params.id : null

const units = ['Nos', 'Kg', 'Ltr', 'Hrs', 'Pcs', 'Mtr', 'Box', 'Set', 'Pair', 'Month', 'Year']

const form = ref({
  type: 'service',
  name: '',
  description: '',
  hsn_sac: '',
  unit: 'Nos',
  price: '',
  tax_rate_id: '',
  is_active: true,
})

async function load() {
  loading.value = true
  try {
    const tRes = await all('TaxRate')
    taxRates.value = tRes.data?.data || []

    if (isEdit) {
      const pRes = await item('Product', { id: productId })
      const p = pRes.data?.data
      if (p) {
        form.value = {
          type:        p.type        || 'service',
          name:        p.name,
          description: p.description || '',
          hsn_sac:     p.hsn_sac     || '',
          unit:        p.unit        || 'Nos',
          price:       p.price,
          tax_rate_id: p.tax_rate_id || '',
          is_active:   p.is_active !== false,
        }
      }
    }
  } catch (err) {
    error.value = 'Failed to load details.'
  }
  loading.value = false
}

async function save() {
  error.value = ''
  if (!form.value.name) return (error.value = 'Product name is required.')
  
  saving.value = true
  try {
    if (isEdit) {
      await task('Product', 'update', { ...form.value, id: productId })
    } else {
      await task('Product', 'create', form.value)
    }
    router.push('/products')
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save. Please try again.'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="max-w-3xl mx-auto space-y-6 pb-20">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <button @click="router.push('/products')" class="w-10 h-10 rounded-full bg-white shadow-soft flex items-center justify-center text-gray-500 hover:text-gray-900 transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <h1 class="page-title flex items-center gap-2">{{ isEdit ? 'Edit Item' : 'Add Item' }} <HelpIcon section="products" /></h1>
      </div>
      <div class="flex gap-3">
        <button @click="router.push('/products')" class="btn-outline hidden sm:flex">Cancel</button>
        <button @click="save" :disabled="saving || loading" class="btn-primary">
          <svg v-if="saving" class="w-4 h-4 animate-spin inline mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
          {{ saving ? 'Saving…' : 'Save Item' }}
        </button>
      </div>
    </div>

    <div v-if="loading" class="card p-12 text-center text-gray-400">Loading...</div>
    
    <div v-else class="card p-6 sm:p-8 space-y-6 animate-fade-in-up">
      <div v-if="error" class="text-sm text-danger-500 bg-danger-50 rounded-lg px-4 py-3">{{ error }}</div>

      <!-- Type toggle -->
      <div class="flex gap-2 p-1 bg-gray-50 rounded-2xl w-full sm:w-64 border border-gray-100">
        <button type="button" @click="form.type = 'service'"
          class="flex-1 py-2.5 rounded-xl text-sm font-bold transition-all shadow-sm"
          :class="form.type === 'service' ? 'bg-white text-primary-700' : 'bg-transparent text-gray-500 hover:text-gray-700 shadow-none'">
          Service
        </button>
        <button type="button" @click="form.type = 'product'"
          class="flex-1 py-2.5 rounded-xl text-sm font-bold transition-all shadow-sm"
          :class="form.type === 'product' ? 'bg-white text-primary-700' : 'bg-transparent text-gray-500 hover:text-gray-700 shadow-none'">
          Product
        </button>
      </div>

      <div>
        <label class="form-label">Item Name *</label>
        <input v-model="form.name" type="text" class="form-input text-lg font-semibold" placeholder="e.g. Web Development, Cotton Fabric" />
      </div>

      <div>
        <label class="form-label">Description <span class="text-gray-400 font-normal">(optional)</span></label>
        <input v-model="form.description" type="text" class="form-input" placeholder="Short note about this item" />
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div>
          <label class="form-label">Selling Price (₹) *</label>
          <input v-model="form.price" type="number" min="0" step="0.01" class="form-input text-lg font-bold text-gray-900" placeholder="0.00" />
        </div>
        <div>
          <label class="form-label">GST Rate</label>
          <select v-model="form.tax_rate_id" class="form-select">
            <option value="">No GST</option>
            <option v-for="t in taxRates" :key="t.id" :value="t.id">{{ t.name }} ({{ t.rate }}%)</option>
          </select>
        </div>
      </div>

      <hr class="border-gray-100" />

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div>
          <label class="form-label">HSN / SAC Code</label>
          <input v-model="form.hsn_sac" type="text" class="form-input" placeholder="e.g. 998313" />
        </div>
        <div>
          <label class="form-label">Unit</label>
          <select v-model="form.unit" class="form-select">
            <option v-for="u in units" :key="u">{{ u }}</option>
          </select>
        </div>
      </div>

      <div class="flex items-center gap-3 pt-2">
        <button type="button" @click="form.is_active = !form.is_active"
          class="relative inline-flex h-7 w-12 items-center rounded-full transition-colors"
          :class="form.is_active ? 'bg-primary-600' : 'bg-gray-200'">
          <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform"
            :class="form.is_active ? 'translate-x-6' : 'translate-x-1'"></span>
        </button>
        <span class="text-sm font-medium text-gray-700">{{ form.is_active ? 'Active — shows in bill items' : 'Hidden from bill items' }}</span>
      </div>

    </div>
  </div>
</template>
