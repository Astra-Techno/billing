<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { list, task } from '../../api'
import HelpIcon from '../../components/HelpIcon.vue'
import { inr } from '../../utils/currency'
import { today } from '../../utils/date'

const router = useRouter()
const emit   = defineEmits(['refresh'])

const invoices = ref([])
const loading  = ref(false)
const saving   = ref(false)
const error    = ref('')

const blankItem = () => ({ description: '', hsn_sac: '', unit: 'Nos', quantity: 1, unit_price: '', gst_rate: 18 })

const form = ref({
  invoice_id: '',
  reason:     'return',
  issue_date: today(),
  notes:      '',
  items:      [blankItem()],
})

const reasons = [
  { value: 'return',     label: 'Goods Return' },
  { value: 'discount',   label: 'Discount / Price Adjustment' },
  { value: 'correction', label: 'Invoice Correction' },
  { value: 'other',      label: 'Other' },
]

const units    = ['Nos', 'Kg', 'Ltr', 'Hrs', 'Pcs', 'Mtr', 'Box', 'Set']
const gstRates = [0, 5, 12, 18, 28]

async function load() {
  loading.value = true
  try {
    const invRes = await list('Invoice', { sort_by: 'i.created_at', sort_order: 'desc', limit: 500 })
    invoices.value = invRes.data?.data || []
  } catch (err) {
    error.value = 'Failed to load invoices.'
  }
  loading.value = false
}

function addItem() { form.value.items.push(blankItem()) }
function removeItem(i) { if (form.value.items.length > 1) form.value.items.splice(i, 1) }

function lineTotal(it) {
  const qty   = parseFloat(it.quantity   || 0)
  const price = parseFloat(it.unit_price || 0)
  const gst   = parseFloat(it.gst_rate   || 0)
  return qty * price * (1 + gst / 100)
}

async function save() {
  error.value = ''
  if (!form.value.invoice_id) return (error.value = 'Please select an invoice.')
  if (!form.value.items.some(i => i.description)) return (error.value = 'Add at least one item.')
  
  saving.value = true
  try {
    await task('CreditNote', 'create', form.value)
    emit('refresh')
    router.push('/credit-notes')
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to create credit note.'
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
        <button @click="router.push('/credit-notes')" class="w-10 h-10 rounded-full bg-white shadow-soft flex items-center justify-center text-gray-500 hover:text-gray-900 transition-colors">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <h1 class="page-title flex items-center gap-2">New Credit Note <HelpIcon section="returns" /></h1>
      </div>
      <div class="flex gap-3">
        <button @click="router.push('/credit-notes')" class="btn-outline hidden sm:flex">Cancel</button>
        <button @click="save" :disabled="saving || loading" class="btn-primary">
          <svg v-if="saving" class="w-4 h-4 animate-spin inline mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
          {{ saving ? 'Creating…' : 'Create Credit Note' }}
        </button>
      </div>
    </div>

    <div v-if="loading" class="card p-12 text-center text-gray-400">Loading...</div>
    
    <div v-else class="card p-6 sm:p-8 space-y-6 animate-fade-in-up">
      <div v-if="error" class="text-sm text-danger-500 bg-danger-50 rounded-lg px-4 py-3">{{ error }}</div>

      <div>
        <label class="form-label">Invoice *</label>
        <select v-model="form.invoice_id" class="form-select">
          <option value="">Select Invoice</option>
          <option v-for="inv in invoices" :key="inv.id" :value="inv.id">
            {{ inv.number }} — {{ inv.client_name }} ({{ inr(inv.total) }})
          </option>
        </select>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div>
          <label class="form-label">Reason *</label>
          <select v-model="form.reason" class="form-select">
            <option v-for="r in reasons" :key="r.value" :value="r.value">{{ r.label }}</option>
          </select>
        </div>
        <div>
          <label class="form-label">Date *</label>
          <input v-model="form.issue_date" type="date" class="form-input" />
        </div>
      </div>

      <hr class="border-gray-100" />

      <!-- Items -->
      <div class="border border-gray-200 rounded-2xl overflow-hidden">
        <div class="bg-gray-50 px-5 py-3 text-xs font-semibold text-gray-500 uppercase flex items-center justify-between border-b border-gray-200">
          <span>Items to credit</span>
          <button type="button" @click="addItem" class="text-primary-600 text-xs font-bold px-2 py-1 hover:bg-primary-50 rounded transition-colors">+ Add Item</button>
        </div>
        <div v-for="(it, i) in form.items" :key="i" class="p-5 space-y-3 border-b border-gray-100 last:border-0 bg-white group">
          <div class="flex items-start justify-between gap-4">
            <input v-model="it.description" type="text" class="form-input flex-1" placeholder="Description *" />
            <button v-if="form.items.length > 1" type="button" @click="removeItem(i)" class="text-gray-300 hover:text-danger-500 p-2 opacity-0 group-hover:opacity-100 transition-opacity">✕</button>
          </div>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div>
              <label class="form-label text-[10px]">Qty</label>
              <input v-model="it.quantity" type="number" min="0.001" class="form-input" placeholder="Qty" />
            </div>
            <div>
              <label class="form-label text-[10px]">Rate ₹</label>
              <input v-model="it.unit_price" type="number" min="0" step="0.01" class="form-input" placeholder="Rate ₹" />
            </div>
            <div>
              <label class="form-label text-[10px]">GST</label>
              <select v-model="it.gst_rate" class="form-select">
                <option v-for="r in gstRates" :key="r" :value="r">{{ r }}%</option>
              </select>
            </div>
            <div class="flex flex-col justify-end items-end pb-2">
              <span class="text-sm font-bold text-gray-900">{{ inr(lineTotal(it)) }}</span>
            </div>
          </div>
        </div>
      </div>

      <div>
        <label class="form-label">Notes</label>
        <textarea v-model="form.notes" rows="2" class="form-input" placeholder="Reason or additional info…"></textarea>
      </div>

    </div>
  </div>
</template>
