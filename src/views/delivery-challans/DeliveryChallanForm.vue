<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { task, item, all } from '../../api'
import { today, addDays } from '../../utils/date'
import { useToast } from '../../composables/useToast'

const router = useRouter()
const route  = useRoute()
const emit   = defineEmits(['refresh'])

const toast     = useToast()
const clients   = ref([])
const products  = ref([])
const loading   = ref(false)
const saved     = ref(false)
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

const selectedClient = computed(() => clients.value.find(c => c.id == form.value.client_id))

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
  if (!form.value.client_id) return (error.value = 'Please choose a customer. You must select one from the list.')
  if (!form.value.items.some(i => i.description)) return (error.value = 'Please add at least one item.')
  loading.value = true
  try {
    if (isEdit.value) {
      await task('DeliveryChallan', 'update', { ...form.value, id: route.params.id })
      emit('refresh')
      toast.success('Delivery challan updated.')
      saved.value = true
    } else {
      const res = await task('DeliveryChallan', 'create', form.value)
      emit('refresh')
      toast.success('Delivery challan created.')
      const newId = res.data?.data?.id
      router.push(newId ? `/delivery-challans/${newId}` : '/delivery-challans')
    }
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to save. Please try again.'
  }
  loading.value = false
}
</script>

<template>
  <div class="inv-shell">

    <!-- Toolbar -->
    <div class="inv-toolbar">
      <div class="flex items-center gap-3 min-w-0">
        <button type="button" @click="router.back()" class="inv-back-btn">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <h1 class="inv-page-title">{{ isEdit ? 'Edit Delivery Challan' : 'New Delivery Challan' }}</h1>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" @click="router.back()" class="inv-btn-secondary hidden sm:inline-flex">Cancel</button>
        <button type="submit" form="dc-form" class="inv-btn-primary" :disabled="loading">
          {{ loading ? 'Saving…' : saved ? 'Saved ✓' : isEdit ? 'Save Changes' : 'Create Challan' }}
        </button>
      </div>
    </div>

    <!-- Body: two-column on desktop -->
    <div class="inv-body">
      <form id="dc-form" @submit.prevent="submit" @input="saved = false" class="inv-layout">

        <!-- LEFT: Main content -->
        <div class="inv-main">

          <!-- Items card -->
          <div class="inv-card">
            <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
              <h2 class="text-sm font-semibold text-gray-800">Dispatched Items</h2>
              <span class="text-xs text-gray-400">{{ form.items.length }} item{{ form.items.length > 1 ? 's' : '' }}</span>
            </div>

            <!-- Desktop table -->
            <div class="hidden lg:block">
              <div class="grid grid-cols-12 gap-4 px-5 py-2.5 text-[10px] font-bold uppercase tracking-wider text-gray-400 bg-gray-50 border-b border-gray-100">
                <span class="col-span-5">Items</span>
                <span class="col-span-2 text-center">QTY / Unit</span>
                <span class="col-span-3 text-center">HSN/SAC</span>
                <span class="col-span-2 text-right pr-2">Action</span>
              </div>
              <div class="divide-y divide-gray-100">
                <div v-for="(it, i) in form.items" :key="i" class="grid grid-cols-12 gap-4 px-5 py-4 items-start hover:bg-gray-50/20 transition-colors">
                  <!-- Col 1: Description + product picker -->
                  <div class="col-span-5 space-y-2">
                    <input v-model="it.description" type="text" class="inv-input font-medium !bg-white" placeholder="Item description" required />
                    <select v-if="products.length" v-model="it.product_id" class="inv-select text-xs text-gray-400 w-full !bg-white" @change="pickProduct(i, it.product_id)">
                      <option :value="null">— Select from products —</option>
                      <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                  </div>
                  <!-- Col 2: QTY + Unit -->
                  <div class="col-span-2 space-y-2">
                    <input v-model="it.quantity" type="number" min="0.001" step="0.001" class="inv-input text-center tabular-nums !bg-white" />
                    <select v-model="it.unit" class="inv-select text-center text-xs !bg-white">
                      <option v-for="u in units" :key="u">{{ u }}</option>
                    </select>
                  </div>
                  <!-- Col 3: HSN/SAC -->
                  <div class="col-span-3">
                    <input v-model="it.hsn_sac" type="text" class="inv-input text-center !bg-white" placeholder="Optional HSN/SAC" />
                  </div>
                  <!-- Col 4: Remove -->
                  <div class="col-span-2 flex justify-end pt-1 pr-2">
                    <button v-if="form.items.length > 1" type="button" @click="removeItem(i)"
                      class="w-7 h-7 flex items-center justify-center text-gray-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition" title="Remove">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Mobile: stacked cards -->
            <div class="lg:hidden divide-y divide-gray-100">
              <div v-for="(it, i) in form.items" :key="i" class="p-4 space-y-3">
                <div v-if="products.length">
                  <label class="inv-label">Select Product <span class="text-gray-400 font-normal">(or type manually below)</span></label>
                  <select v-model="it.product_id" class="inv-select w-full" @change="pickProduct(i, it.product_id)">
                    <option :value="null">— Type manually —</option>
                    <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                  </select>
                </div>
                <div><label class="inv-label">Description *</label><input v-model="it.description" type="text" class="inv-input w-full" placeholder="Item description" required /></div>
                <div class="grid grid-cols-2 gap-2">
                  <div><label class="inv-label">Quantity</label><input v-model="it.quantity" type="number" min="0.001" step="0.001" class="inv-input w-full" /></div>
                  <div><label class="inv-label">Unit</label><select v-model="it.unit" class="inv-select w-full"><option v-for="u in units" :key="u">{{ u }}</option></select></div>
                  <div class="col-span-2"><label class="inv-label">HSN/SAC</label><input v-model="it.hsn_sac" type="text" class="inv-input w-full" placeholder="Optional" /></div>
                </div>
                <div class="flex justify-end">
                  <button v-if="form.items.length > 1" type="button" @click="removeItem(i)" class="text-xs text-red-500 font-medium">Remove Item</button>
                </div>
              </div>
            </div>

            <!-- Add item -->
            <div class="px-5 py-3 border-t border-gray-100">
              <button type="button" @click="addItem"
                class="flex items-center gap-2 text-sm text-primary-600 font-medium hover:text-primary-700 transition">
                <span class="w-5 h-5 rounded-full border-2 border-primary-300 flex items-center justify-center text-primary-500 text-xs leading-none">+</span>
                Add item
              </button>
            </div>
          </div>

          <!-- Notes -->
          <div class="inv-card p-5 space-y-3">
            <h2 class="text-sm font-semibold text-gray-700">Notes / Instructions</h2>
            <textarea v-model="form.notes" rows="3" class="inv-textarea w-full" placeholder="Any delivery instructions…"></textarea>
          </div>

        </div><!-- /inv-main -->

        <!-- RIGHT: Sidebar -->
        <aside class="inv-sidebar">

          <!-- Delivery To -->
          <div class="inv-card">
            <div class="px-5 py-3.5 border-b border-gray-100">
              <h2 class="text-sm font-semibold text-gray-800">Delivery To</h2>
              <p class="text-xs text-gray-400 mt-0.5">*Select one customer</p>
            </div>
            <div class="p-4">
              <div v-if="form.client_id" class="flex items-center gap-3 p-3 bg-primary-50 rounded-xl border border-primary-100">
                <div class="w-9 h-9 rounded-full bg-primary-600 flex items-center justify-center shrink-0">
                  <span class="text-white text-sm font-bold">{{ selectedClient?.name?.charAt(0)?.toUpperCase() }}</span>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm font-semibold text-gray-900 truncate">{{ selectedClient?.name }}</p>
                  <p class="text-xs text-gray-500 truncate">{{ selectedClient?.mobile || selectedClient?.email || '—' }}</p>
                </div>
                <button type="button" @click="form.client_id = ''; clientSearch = ''" class="text-xs text-primary-600 font-semibold shrink-0 hover:underline">Change</button>
              </div>
              <div v-else class="space-y-2">
                <div class="relative">
                  <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                  <input v-model="clientSearch" type="text" placeholder="Search by name, mobile…" class="inv-input w-full pl-9" />
                </div>
                <div v-if="clientSearch || filteredClients.length > 0" class="max-h-48 overflow-y-auto rounded-lg border border-gray-200 divide-y divide-gray-50 bg-white shadow-sm">
                  <div v-if="!filteredClients.length" class="px-3 py-3 text-center text-sm text-gray-400">No match for "{{ clientSearch }}"</div>
                  <button v-for="c in filteredClients" :key="c.id" type="button"
                    @click="form.client_id = c.id; clientSearch = ''"
                    class="w-full flex items-center gap-3 px-3 py-2.5 hover:bg-gray-50 transition text-left">
                    <div class="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center shrink-0">
                      <span class="text-xs font-bold text-gray-500">{{ c.name?.charAt(0)?.toUpperCase() }}</span>
                    </div>
                    <div class="min-w-0">
                      <p class="text-sm font-medium text-gray-800 truncate">{{ c.name }}</p>
                      <p class="text-xs text-gray-400 truncate">{{ c.mobile || c.email || 'Customer' }}</p>
                    </div>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Delivery Details -->
          <div class="inv-card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-800">Delivery Details</h2>
            <div>
              <label class="inv-label">Challan Date *</label>
              <input v-model="form.challan_date" type="date" class="inv-input w-full" />
            </div>
            <div>
              <label class="inv-label">Destination</label>
              <input v-model="form.destination" type="text" class="inv-input w-full" placeholder="Delivery address / city" />
            </div>
          </div>

          <!-- Transport Details -->
          <div class="inv-card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-800">Transport Details</h2>
            <div>
              <label class="inv-label">Vehicle Number</label>
              <input v-model="form.vehicle_no" type="text" class="inv-input w-full" placeholder="e.g. TN 01 AB 1234" />
            </div>
            <div>
              <label class="inv-label">Driver Name</label>
              <input v-model="form.driver_name" type="text" class="inv-input w-full" placeholder="Driver's name" />
            </div>
          </div>

          <!-- Error -->
          <div v-if="error" class="inv-card p-4">
            <p class="text-xs text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2">{{ error }}</p>
          </div>

        </aside>
      </form>
    </div>

    <!-- Mobile sticky footer -->
    <div class="form-footer-mobile lg:hidden">
      <button type="button" @click="router.back()" class="btn-outline">Cancel</button>
      <button type="submit" form="dc-form" class="btn-primary flex-1" :disabled="loading">
        {{ loading ? 'Saving…' : saved ? 'Saved ✓' : isEdit ? 'Update DC' : 'Create DC' }}
      </button>
    </div>
  </div>
</template>
