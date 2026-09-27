<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { item, task, all } from '../../api'
import HelpIcon from '../../components/HelpIcon.vue'
import InfoTip from '../../components/InfoTip.vue'
import { useToast } from '../../composables/useToast'
import { useFormKeys } from '../../composables/useFormKeys'

const router = useRouter()
const route  = useRoute()
const emit   = defineEmits(['refresh'])
const toast  = useToast()
useFormKeys({ formId: 'product-form' })

const taxRates = ref([])
const loading  = ref(false)
const saving   = ref(false)
const error    = ref('')
const saved    = ref(false)

const isEdit = route.params.id && route.params.id !== 'new'
const productId = isEdit ? route.params.id : null

const units = ['Nos', 'Pcs', 'Packet', 'Box', 'Bag', 'Dozen', 'Kg', 'Gram', 'Ltr', 'Ml', 'Mtr', 'Feet', 'Set', 'Pair', 'Hrs', 'Month', 'Year']

const form = ref({
  type: 'service',
  name: '',
  description: '',
  hsn_sac: '',
  unit: 'Nos',
  price: '',
  purchase_price: '', mrp: '', reorder_level: 0, track_stock: false,
  sku: '', barcode: '', base_unit: 'Nos', conversion_factor: 1,
  batch_tracking: false, expiry_tracking: false, serial_tracking: false,
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
          purchase_price: p.purchase_price || '', mrp: p.mrp || '', reorder_level: p.reorder_level || 0,
          track_stock: !!+p.track_stock, sku: p.sku || '', barcode: p.barcode || '',
          base_unit: p.base_unit || p.unit || 'Nos', conversion_factor: p.conversion_factor || 1,
          batch_tracking: !!+p.batch_tracking, expiry_tracking: !!+p.expiry_tracking, serial_tracking: !!+p.serial_tracking,
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
      emit('refresh')
      toast.success('Item updated successfully')
      saved.value = true
    } else {
      const res = await task('Product', 'create', form.value)
      emit('refresh')
      toast.success('Item added successfully')
      const newId = res.data?.data?.product_id
      router.push(newId ? `/products/${newId}/edit` : '/products')
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save. Please try again.'
  } finally {
    saving.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="inv-shell">
    <!-- Toolbar -->
    <div class="inv-toolbar">
      <div class="flex items-center gap-3 min-w-0">
        <button type="button" @click="router.back()" class="inv-back-btn">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <h1 class="inv-page-title">{{ isEdit ? 'Edit Item' : 'Add Item' }}</h1>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" @click="router.back()" class="inv-btn-secondary hidden sm:inline-flex">Cancel</button>
        <button type="submit" form="product-form" class="inv-btn-primary" :disabled="saving" title="Ctrl+Enter">
          {{ saving ? 'Saving…' : saved ? 'Saved ✓' : 'Save Item' }} <kbd v-if="!saving" class="ml-1 opacity-60 text-[10px] font-mono">⌃↵</kbd>
        </button>
      </div>
    </div>

    <!-- Body -->
    <div class="inv-body">
      <div v-if="loading" class="flex items-center justify-center p-12 text-gray-400">
        Loading item details...
      </div>
      
      <form v-else id="product-form" @submit.prevent="save" @input="saved = false" class="inv-layout">
        <!-- Main Column -->
        <div class="inv-main">
          <div v-if="error" class="text-sm text-danger-600 bg-danger-50 border border-danger-100 rounded-xl px-4 py-3 font-medium">
            {{ error }}
          </div>

          <!-- Basic Details Card -->
          <div class="inv-card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wider mb-2">Basic Details</h2>
            
            <div>
              <label class="inv-label">Item Type</label>
              <div class="flex gap-2 p-1 bg-gray-50 rounded-2xl w-full sm:w-64 border border-gray-100/60 mt-1">
                <button type="button" @click="form.type = 'service'"
                  class="flex-1 py-2.5 rounded-xl text-sm font-bold transition-all shadow-sm"
                  :class="form.type === 'service' ? 'bg-white text-primary-700 ring-1 ring-gray-200/50' : 'bg-transparent text-gray-500 hover:text-gray-700 shadow-none'">
                  Service
                </button>
                <button type="button" @click="form.type = 'product'"
                  class="flex-1 py-2.5 rounded-xl text-sm font-bold transition-all shadow-sm"
                  :class="form.type === 'product' ? 'bg-white text-primary-700 ring-1 ring-gray-200/50' : 'bg-transparent text-gray-500 hover:text-gray-700 shadow-none'">
                  Product
                </button>
              </div>
            </div>

            <div>
              <label class="inv-label">Item Name *</label>
              <input v-model="form.name" type="text" class="inv-input font-medium !bg-white" required placeholder="e.g. Web Development, Cotton Fabric" />
            </div>

            <div>
              <label class="inv-label">Description <span class="text-gray-400 font-normal">(optional)</span></label>
              <input v-model="form.description" type="text" class="inv-input !bg-white" placeholder="Short note about this item" />
            </div>
          </div>

          <!-- Pricing & Tax Card -->
          <div class="inv-card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wider mb-2">Pricing & Tax</h2>
            
            <div class="grid sm:grid-cols-2 gap-4">
              <div>
                <label class="inv-label">Selling Price (₹) *</label>
                <div class="relative mt-1">
                  <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₹</span>
                  <input v-model="form.price" type="number" min="0" step="0.01" required class="inv-input pl-7 font-semibold !bg-white" placeholder="0.00" />
                </div>
              </div>
              
              <div>
                <label class="inv-label">GST Rate</label>
                <select v-model="form.tax_rate_id" class="inv-select mt-1 !bg-white">
                  <option value="">No GST (Exempt/Nil)</option>
                  <option v-for="t in taxRates" :key="t.id" :value="t.id">{{ t.name }} ({{ t.rate }}%)</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Sidebar Column -->
        <div class="inv-sidebar">
          <!-- Inventory details -->
          <div class="inv-card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wider mb-2">Inventory Details</h2>
            <label v-if="form.type === 'product'" class="flex items-start gap-3 rounded-xl border border-blue-100 bg-blue-50 p-3">
              <input v-model="form.track_stock" type="checkbox" class="mt-1" />
              <span><span class="flex items-center gap-2 text-sm font-semibold">Maintain stock for this item? <InfoTip text="Turn this on for physical goods. Leave it off for services, labour, delivery charges and items whose stock you do not want to maintain." /></span><span class="text-xs text-gray-500">Shows available quantity during billing.</span></span>
            </label>
            
            <div>
              <label class="inv-label">Unit of Measure</label>
              <select v-model="form.unit" class="inv-select mt-1 !bg-white">
                <option v-for="u in units" :key="u">{{ u }}</option>
              </select>
            </div>

            <div>
              <label class="inv-label">HSN / SAC Code</label>
              <input v-model="form.hsn_sac" type="text" class="inv-input mt-1 !bg-white" placeholder="e.g. 998313" />
            </div>
            <template v-if="form.type === 'product' && form.track_stock">
              <div class="grid grid-cols-2 gap-3"><div><label class="inv-label">Purchase Price (₹)</label><input v-model="form.purchase_price" type="number" step="0.01" class="inv-input mt-1 !bg-white" /></div><div><label class="inv-label">MRP (₹)</label><input v-model="form.mrp" type="number" step="0.01" class="inv-input mt-1 !bg-white" /></div></div>
              <div><label class="inv-label flex items-center gap-2">Low Stock Alert <InfoTip text="The app highlights this item when current stock reaches this quantity." /></label><input v-model="form.reorder_level" type="number" step="0.001" class="inv-input mt-1 !bg-white" /></div>
              <div class="grid grid-cols-2 gap-3"><div><label class="inv-label">SKU / Item Code</label><input v-model="form.sku" class="inv-input mt-1 !bg-white" /></div><div><label class="inv-label">Barcode</label><input v-model="form.barcode" class="inv-input mt-1 !bg-white" /></div></div>
              <div class="rounded-xl border p-3"><div class="flex items-center gap-2 text-xs font-bold uppercase text-gray-600">Purchase unit conversion <InfoTip text="Example: if you purchase one Box containing 12 Pieces, keep billing unit as Pcs, choose Box here and enter 12." /></div><div class="mt-2 grid grid-cols-2 gap-3"><select v-model="form.base_unit" class="inv-select !bg-white"><option v-for="u in units" :key="u">{{u}}</option></select><input v-model="form.conversion_factor" type="number" min="0.0001" step="0.0001" class="inv-input !bg-white" placeholder="1 purchase unit = ?" /></div></div>
              <div class="rounded-xl border p-3 space-y-2"><p class="flex items-center gap-2 text-xs font-bold uppercase text-gray-600">Optional trade controls <InfoTip text="Use these only when your trade needs them: batch and expiry for pharmacy/food; serial or IMEI for electronics." /></p><label class="flex gap-2 text-sm"><input v-model="form.batch_tracking" type="checkbox"/> Batch / lot number</label><label class="flex gap-2 text-sm"><input v-model="form.expiry_tracking" type="checkbox"/> Expiry date</label><label class="flex gap-2 text-sm"><input v-model="form.serial_tracking" type="checkbox"/> Serial / IMEI number</label></div>
            </template>
          </div>

          <!-- Status toggle -->
          <div class="inv-card p-5 flex items-center justify-between">
            <div>
              <h2 class="text-sm font-semibold text-gray-800 uppercase tracking-wider">Item Status</h2>
              <p class="text-xs text-gray-500 mt-1">{{ form.is_active ? 'Active — shown in billing' : 'Inactive — hidden' }}</p>
            </div>
            <button type="button" @click="form.is_active = !form.is_active"
              class="relative inline-flex h-7 w-12 items-center rounded-full transition-colors focus:outline-none"
              :class="form.is_active ? 'bg-primary-600' : 'bg-gray-200'">
              <span class="inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform"
                :class="form.is_active ? 'translate-x-6' : 'translate-x-1'"></span>
            </button>
          </div>
        </div>
      </form>
    </div>

    <!-- Mobile Footer Actions -->
    <div class="form-footer-mobile lg:hidden">
      <button type="button" @click="router.back()" class="inv-btn-secondary flex-1 justify-center">Cancel</button>
      <button type="submit" form="product-form" class="inv-btn-primary flex-1 justify-center" :disabled="saving">
        {{ saving ? 'Saving…' : saved ? 'Saved ✓' : 'Save Item' }}
      </button>
    </div>
  </div>
</template>
