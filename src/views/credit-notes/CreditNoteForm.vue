<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { list, task, item } from '../../api'
import HelpIcon from '../../components/HelpIcon.vue'
import { inr } from '../../utils/currency'
import { today } from '../../utils/date'
import { useToast } from '../../composables/useToast'

const router = useRouter()
const route  = useRoute()
const emit   = defineEmits(['refresh'])
const toast  = useToast()

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

// When invoice_id changes, offer to prefill items from that invoice
async function prefillFromInvoice(invoiceId) {
  if (!invoiceId) return
  try {
    const [itmRes] = await Promise.all([
      list('Invoice:items', { invoice_id: invoiceId }),
    ])
    const invItems = itmRes.data?.data || []
    if (invItems.length > 0) {
      form.value.items = invItems.map(it => ({
        description: it.description,
        hsn_sac:     it.hsn_sac || '',
        unit:        it.unit || 'Nos',
        quantity:    parseFloat(it.quantity) || 1,
        unit_price:  parseFloat(it.unit_price) || 0,
        gst_rate:    parseFloat(it.gst_rate) || 0,
      }))
    }
  } catch {}
}

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
    // Prefill if coming from an invoice detail page
    const fromId = route.query.from_invoice
    if (fromId) {
      form.value.invoice_id = parseInt(fromId)
      await prefillFromInvoice(fromId)
    }
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
    const res = await task('CreditNote', 'create', form.value)
    emit('refresh')
    toast.success('Credit note created.')
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
  <div class="gpay-screen px-4 py-4 max-w-3xl lg:mx-auto space-y-6 pb-20">
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
      <div class="border border-gray-200 rounded-2xl overflow-hidden shadow-sm bg-white">
        <div class="bg-gray-50 px-5 py-3.5 text-xs font-semibold text-gray-500 uppercase flex items-center justify-between border-b border-gray-200">
          <span>Items to credit</span>
          <button type="button" @click="addItem" class="text-primary-600 text-xs font-bold px-2.5 py-1 hover:bg-primary-50 border border-primary-100 rounded-xl transition-colors bg-white shadow-soft">+ Add Item</button>
        </div>

        <!-- Desktop items table (Redesigned Grid) -->
        <div class="hidden lg:block">
          <!-- Table Header -->
          <div class="grid grid-cols-12 gap-4 px-5 py-2.5 text-[10px] font-bold uppercase tracking-wider text-gray-400 bg-gray-50/50 border-b border-gray-100">
            <span class="col-span-5">Items</span>
            <span class="col-span-2 text-center">QTY</span>
            <span class="col-span-3 text-right">Price / Tax</span>
            <span class="col-span-2 text-right pr-2">Amount</span>
          </div>
          <!-- Rows -->
          <div class="divide-y divide-gray-100 bg-white">
            <div v-for="(it, i) in form.items" :key="i" class="grid grid-cols-12 gap-4 px-5 py-4 items-start hover:bg-gray-50/20 transition-colors">
              <!-- Column 1: Description -->
              <div class="col-span-5">
                <input v-model="it.description" type="text" class="inv-input font-medium !bg-white" placeholder="Description *" required />
              </div>

              <!-- Column 2: QTY -->
              <div class="col-span-2">
                <input v-model="it.quantity" type="number" min="0.001" step="0.001" class="inv-input text-center tabular-nums !bg-white" placeholder="1.00" />
              </div>

              <!-- Column 3: Rate + Tax/GST -->
              <div class="col-span-3 space-y-2">
                <div class="relative">
                  <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₹</span>
                  <input v-model="it.unit_price" type="number" min="0" step="0.01" class="inv-input pl-7 text-right tabular-nums !bg-white" placeholder="0.00" />
                </div>
                <select v-model="it.gst_rate" class="inv-select text-center text-xs !bg-white">
                  <option v-for="r in gstRates" :key="r" :value="r">{{ r }}% GST</option>
                </select>
              </div>

              <!-- Column 4: Total Amount + actions -->
              <div class="col-span-2 flex flex-col items-end justify-between h-[86px] py-1">
                <span class="text-sm font-semibold text-gray-800 tabular-nums pr-2">{{ inr(lineTotal(it)) }}</span>
                <button v-if="form.items.length > 1" type="button" @click="removeItem(i)"
                  class="mr-1 w-7 h-7 flex items-center justify-center text-gray-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition" title="Remove">
                  <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Mobile items layout (stacked cards) -->
        <div class="lg:hidden divide-y divide-gray-100">
          <div v-for="(it, i) in form.items" :key="i" class="p-5 space-y-3 bg-white group">
            <div class="flex items-start justify-between gap-4">
              <input v-model="it.description" type="text" class="form-input flex-1 !bg-white" placeholder="Description *" required />
              <button v-if="form.items.length > 1" type="button" @click="removeItem(i)" class="text-gray-300 hover:text-danger-500 p-2 opacity-0 group-hover:opacity-100 transition-opacity">✕</button>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
              <div>
                <label class="form-label text-[10px]">Qty</label>
                <input v-model="it.quantity" type="number" min="0.001" class="form-input !bg-white" placeholder="Qty" />
              </div>
              <div>
                <label class="form-label text-[10px]">Rate ₹</label>
                <input v-model="it.unit_price" type="number" min="0" step="0.01" class="form-input !bg-white" placeholder="Rate ₹" />
              </div>
              <div>
                <label class="form-label text-[10px]">GST</label>
                <select v-model="it.gst_rate" class="form-select !bg-white">
                  <option v-for="r in gstRates" :key="r" :value="r">{{ r }}%</option>
                </select>
              </div>
              <div class="flex flex-col justify-end items-end pb-2">
                <span class="text-sm font-bold text-gray-900">{{ inr(lineTotal(it)) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div>
        <label class="form-label">Notes</label>
        <textarea v-model="form.notes" rows="2" class="form-textarea" placeholder="Reason or additional info…"></textarea>
      </div>

    </div>
  </div>
</template>
