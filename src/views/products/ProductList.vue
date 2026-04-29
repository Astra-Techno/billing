<script setup>
import { ref, onMounted } from 'vue'
import { list, task, all } from '../../api'
import HelpIcon from '../../components/HelpIcon.vue'
import { inr } from '../../utils/currency'

const products    = ref([])
const taxRates    = ref([])
const loading     = ref(true)
const saving      = ref(false)
const error       = ref('')
const selectedId  = ref(null)   // null = none, 'new' = add form, number = edit
const deleteTarget = ref(null)
const deleting    = ref(false)
const searchQ     = ref('')
const typeFilter  = ref('')

const units = ['Nos', 'Kg', 'Ltr', 'Hrs', 'Pcs', 'Mtr', 'Box', 'Set', 'Pair', 'Month', 'Year']

const blankForm = () => ({
  type: 'service', name: '', description: '', hsn_sac: '',
  unit: 'Nos', price: '', tax_rate_id: '', is_active: true,
})
const form = ref(blankForm())

async function load() {
  loading.value = true
  try {
    const [pRes, tRes] = await Promise.all([list('Product'), all('TaxRate')])
    products.value = pRes.data?.data || []
    taxRates.value = tRes.data?.data || []
  } catch {}
  loading.value = false
}

const filteredProducts = () => {
  let p = products.value
  if (typeFilter.value) p = p.filter(x => x.type === typeFilter.value)
  if (searchQ.value) {
    const q = searchQ.value.toLowerCase()
    p = p.filter(x => x.name?.toLowerCase().includes(q) || x.hsn_sac?.includes(q))
  }
  return p
}

function openAdd() {
  form.value = blankForm()
  error.value = ''
  selectedId.value = 'new'
}

function openEdit(p) {
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
  error.value = ''
  if (window.innerWidth >= 1024) selectedId.value = p.id
  else selectedId.value = p.id
}

function onRowClick(p) {
  openEdit(p)
}

async function save() {
  error.value = ''
  if (!form.value.name) return (error.value = 'Product name is required.')
  saving.value = true
  try {
    if (selectedId.value && selectedId.value !== 'new') {
      await task('Product', 'update', { ...form.value, id: selectedId.value })
    } else {
      await task('Product', 'create', form.value)
    }
    selectedId.value = null
    await load()
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to save.'
  } finally { saving.value = false }
}

async function confirmDelete() {
  deleting.value = true
  try {
    await task('Product', 'delete', { id: deleteTarget.value.id })
    deleteTarget.value = null
    selectedId.value = null
    await load()
  } catch { deleteTarget.value = null }
  finally { deleting.value = false }
}

function taxLabel(id) {
  const t = taxRates.value.find(t => t.id == id)
  return t ? `${t.name} (${t.rate}%)` : '—'
}

const typeColors = { product: 'bg-blue-100 text-blue-700', service: 'bg-purple-100 text-purple-700' }

onMounted(load)
</script>

<template>
  <div class="lg:flex lg:h-full lg:-mx-8 lg:-mt-5">

    <!-- ── LEFT: 40% list ──────────────────────────────────────────────────── -->
    <div class="lg:w-2/5 lg:flex lg:flex-col lg:border-r border-gray-200 lg:overflow-hidden"
         :class="selectedId ? 'hidden lg:flex' : ''">

      <div class="lg:px-5 lg:pt-5 pb-3 lg:border-b lg:border-gray-100 space-y-3">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="page-title flex items-center gap-2">Products & Services <HelpIcon section="products" /></h1>
            <p class="text-xs text-gray-400 mt-0.5">Items saved for quick billing</p>
          </div>
          <button @click="openAdd" class="btn-primary text-sm py-2 px-4">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            Add Item
          </button>
        </div>

        <!-- Type filter -->
        <div class="flex gap-1">
          <button v-for="t in [['', 'All'], ['product', 'Products'], ['service', 'Services']]" :key="t[0]"
            @click="typeFilter = t[0]"
            class="px-3 py-1.5 rounded-xl text-xs font-semibold transition-all shrink-0"
            :class="typeFilter === t[0] ? 'bg-primary-600 text-white' : 'bg-white text-gray-500 border border-gray-200 hover:border-gray-300'">
            {{ t[1] }}
          </button>
        </div>

        <!-- Search -->
        <div class="relative">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input v-model="searchQ" type="text" class="form-input pl-9 py-2 text-sm" placeholder="Search by name or HSN/SAC…" />
        </div>
      </div>

      <!-- List -->
      <div class="lg:flex-1 lg:overflow-y-auto">
        <div class="card overflow-hidden lg:rounded-none lg:border-x-0 lg:border-b-0 lg:shadow-none mt-4 lg:mt-0">
          <div v-if="loading" class="p-10 text-center text-gray-400 text-sm">Loading…</div>
          <div v-else-if="!filteredProducts().length" class="p-10 text-center">
            <div class="w-14 h-14 rounded-2xl bg-primary-50 flex items-center justify-center mx-auto mb-3">
              <svg class="w-7 h-7 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
            <p class="font-semibold text-gray-700 text-sm">No items found</p>
            <p class="text-xs text-gray-400 mt-1">Add products or services to use in bills</p>
            <button @click="openAdd" class="btn-primary btn-sm mt-3">Add First Item</button>
          </div>
          <div v-else class="divide-y divide-gray-50">
            <div v-for="p in filteredProducts()" :key="p.id"
              class="flex items-center gap-3 px-4 py-3.5 hover:bg-blue-50/40 cursor-pointer transition-colors group"
              :class="selectedId == p.id ? 'bg-primary-50 border-l-2 border-primary-500' : ''"
              @click="onRowClick(p)">

              <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 font-bold text-sm"
                :class="typeColors[p.type] || 'bg-gray-100 text-gray-600'">
                {{ p.type === 'service' ? 'S' : 'P' }}
              </div>

              <div class="flex-1 min-w-0">
                <p class="font-semibold text-gray-800 text-sm truncate group-hover:text-primary-700">{{ p.name }}</p>
                <p class="text-[11px] text-gray-400 mt-0.5 truncate">
                  {{ p.hsn_sac ? 'HSN: ' + p.hsn_sac + ' · ' : '' }}{{ p.unit }}
                  <span v-if="p.is_active === false" class="text-red-400"> · Inactive</span>
                </p>
              </div>

              <div class="text-right shrink-0">
                <p class="font-bold text-gray-900 text-sm">{{ inr(p.price) }}</p>
                <p class="text-[11px] text-gray-400">{{ taxLabel(p.tax_rate_id) }}</p>
              </div>

              <svg class="w-3.5 h-3.5 text-gray-300 shrink-0 group-hover:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── RIGHT: 60% add/edit panel ──────────────────────────────────────── -->
    <div class="hidden lg:flex lg:flex-1 lg:flex-col lg:overflow-y-auto bg-slate-50">

      <!-- Placeholder -->
      <div v-if="!selectedId" class="flex-1 flex flex-col items-center justify-center text-center gap-3 text-gray-400 p-10">
        <div class="w-20 h-20 rounded-3xl bg-white border-2 border-dashed border-gray-200 flex items-center justify-center">
          <svg class="w-9 h-9 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        </div>
        <div>
          <p class="font-semibold text-gray-500">Select an item to edit</p>
          <p class="text-sm mt-0.5">or add a new product / service</p>
        </div>
        <button @click="openAdd" class="btn-primary mt-2">+ Add New Item</button>
      </div>

      <!-- Add / Edit form panel -->
      <div v-else class="flex-1 overflow-y-auto">
        <div class="p-4 border-b border-gray-200 bg-white flex items-center gap-3 sticky top-0 z-10">
          <button @click="selectedId = null" class="text-gray-400 hover:text-gray-700 p-1 rounded-lg hover:bg-gray-100">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
          </button>
          <span class="text-sm font-semibold text-gray-700">{{ selectedId === 'new' ? 'Add New Item' : 'Edit Item' }}</span>
          <button v-if="selectedId !== 'new'" @click="deleteTarget = products.find(p => p.id == selectedId)"
            class="ml-auto text-xs text-red-500 hover:text-red-700 flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            Delete
          </button>
        </div>

        <div class="p-5 space-y-4 max-w-lg">

          <!-- Type toggle -->
          <div class="flex gap-2">
            <button type="button" @click="form.type = 'service'"
              class="flex-1 py-2.5 rounded-xl text-sm font-semibold border transition"
              :class="form.type === 'service' ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'">
              Service
            </button>
            <button type="button" @click="form.type = 'product'"
              class="flex-1 py-2.5 rounded-xl text-sm font-semibold border transition"
              :class="form.type === 'product' ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'">
              Product
            </button>
          </div>

          <div>
            <label class="form-label">Name *</label>
            <input v-model="form.name" type="text" class="form-input" placeholder="e.g. Web Development, Cotton Fabric" />
          </div>

          <div>
            <label class="form-label">Description <span class="text-gray-400 font-normal">(optional)</span></label>
            <input v-model="form.description" type="text" class="form-input" placeholder="Short note about this item" />
          </div>

          <div class="grid grid-cols-2 gap-3">
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
            <div>
              <label class="form-label">Selling Price (₹) *</label>
              <input v-model="form.price" type="number" min="0" step="0.01" class="form-input" placeholder="0.00" />
            </div>
            <div>
              <label class="form-label">GST Rate</label>
              <select v-model="form.tax_rate_id" class="form-select">
                <option value="">No GST</option>
                <option v-for="t in taxRates" :key="t.id" :value="t.id">{{ t.name }} ({{ t.rate }}%)</option>
              </select>
            </div>
          </div>

          <div class="flex items-center gap-3">
            <button type="button" @click="form.is_active = !form.is_active"
              class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
              :class="form.is_active ? 'bg-primary-600' : 'bg-gray-200'">
              <span class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
                :class="form.is_active ? 'translate-x-6' : 'translate-x-1'"></span>
            </button>
            <span class="text-sm text-gray-600">{{ form.is_active ? 'Active — shows in bill items' : 'Hidden from bill items' }}</span>
          </div>

          <div v-if="error" class="text-sm text-danger-500 bg-danger-50 rounded-lg px-3 py-2">{{ error }}</div>

          <div class="flex gap-3 pt-2">
            <button @click="selectedId = null" class="btn-outline flex-1">Cancel</button>
            <button @click="save" :disabled="saving" class="btn-primary flex-1">
              <svg v-if="saving" class="w-4 h-4 animate-spin inline mr-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
              {{ saving ? 'Saving…' : selectedId === 'new' ? 'Add Item' : 'Save Changes' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Mobile modal (small screens only) -->
    <div v-if="selectedId" class="lg:hidden fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/40">
      <div class="bg-white rounded-2xl w-full max-w-md shadow-xl">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <h3 class="font-semibold text-gray-800">{{ selectedId === 'new' ? 'Add Product / Service' : 'Edit Product / Service' }}</h3>
          <div class="flex items-center gap-3">
            <button v-if="selectedId !== 'new'" @click="deleteTarget = products.find(p => p.id == selectedId)"
              class="text-xs text-red-500 hover:text-red-700 flex items-center gap-1">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
              Delete
            </button>
            <button @click="selectedId = null" class="text-gray-400 hover:text-gray-600">✕</button>
          </div>
        </div>
        <div class="p-5 space-y-4">
          <div class="flex gap-2">
            <button type="button" @click="form.type = 'service'" class="flex-1 py-2 rounded-xl text-sm font-semibold border transition" :class="form.type === 'service' ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-600 border-gray-200'">Service</button>
            <button type="button" @click="form.type = 'product'" class="flex-1 py-2 rounded-xl text-sm font-semibold border transition" :class="form.type === 'product' ? 'bg-primary-600 text-white border-primary-600' : 'bg-white text-gray-600 border-gray-200'">Product</button>
          </div>
          <div><label class="form-label">Name *</label><input v-model="form.name" type="text" class="form-input" placeholder="e.g. Web Development" /></div>
          <div><label class="form-label">Description</label><input v-model="form.description" type="text" class="form-input" /></div>
          <div class="grid grid-cols-2 gap-3">
            <div><label class="form-label">HSN / SAC</label><input v-model="form.hsn_sac" type="text" class="form-input" /></div>
            <div><label class="form-label">Unit</label><select v-model="form.unit" class="form-select"><option v-for="u in units" :key="u">{{ u }}</option></select></div>
            <div><label class="form-label">Price (₹) *</label><input v-model="form.price" type="number" min="0" step="0.01" class="form-input" placeholder="0.00" /></div>
            <div><label class="form-label">GST Rate</label><select v-model="form.tax_rate_id" class="form-select"><option value="">No GST</option><option v-for="t in taxRates" :key="t.id" :value="t.id">{{ t.name }} ({{ t.rate }}%)</option></select></div>
          </div>
          <div v-if="error" class="text-sm text-danger-500 bg-danger-50 rounded-lg px-3 py-2">{{ error }}</div>
        </div>
        <div class="px-5 pb-5 flex gap-3">
          <button @click="selectedId = null" class="btn-outline flex-1">Cancel</button>
          <button @click="save" :disabled="saving" class="btn-primary flex-1">{{ saving ? 'Saving…' : selectedId === 'new' ? 'Add' : 'Update' }}</button>
        </div>
      </div>
    </div>

    <!-- Delete confirm -->
    <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
      <div class="bg-white rounded-2xl w-full max-w-sm shadow-xl p-6 space-y-4">
        <h3 class="font-semibold text-gray-900">Delete "{{ deleteTarget.name }}"?</h3>
        <p class="text-sm text-gray-500">This cannot be undone. Any bills using this item will keep their data.</p>
        <div class="flex gap-3">
          <button @click="deleteTarget = null" class="btn-outline flex-1" :disabled="deleting">Cancel</button>
          <button @click="confirmDelete" :disabled="deleting" class="btn-primary flex-1 bg-danger-600 hover:bg-danger-700 border-danger-600">
            {{ deleting ? 'Deleting…' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>

  </div>
</template>
