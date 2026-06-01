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

const grandTotal = () => form.value.items.reduce((s, it) => s + lineTotal(it), 0)

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
  <div class="inv-shell">

    <!-- Toolbar -->
    <div class="inv-toolbar">
      <div class="flex items-center gap-3 min-w-0">
        <button type="button" @click="router.push('/credit-notes')" class="inv-back-btn">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <h1 class="inv-page-title flex items-center gap-2">New Credit Note <HelpIcon section="returns" /></h1>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" @click="router.push('/credit-notes')" class="inv-btn-secondary hidden sm:inline-flex">Cancel</button>
        <button type="submit" form="credit-note-form" class="inv-btn-primary" :disabled="saving || loading">
          <svg v-if="saving" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
          {{ saving ? 'Creating…' : 'Create Credit Note' }}
        </button>
      </div>
    </div>

    <!-- Body: two-column on desktop -->
    <div class="inv-body">
      <form id="credit-note-form" @submit.prevent="save" class="inv-layout">

        <!-- LEFT: Main content -->
        <div class="inv-main">

          <!-- Items card -->
          <div class="inv-card">
            <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
              <h2 class="text-sm font-semibold text-gray-800">Items to Credit</h2>
              <span class="text-xs text-gray-400">{{ form.items.length }} item{{ form.items.length > 1 ? 's' : '' }}</span>
            </div>

            <div v-if="loading" class="p-10 text-center text-gray-400 text-sm">Loading…</div>

            <template v-else>
              <!-- Desktop table -->
              <div class="hidden lg:block">
                <div class="grid grid-cols-12 gap-4 px-5 py-2.5 text-[10px] font-bold uppercase tracking-wider text-gray-400 bg-gray-50 border-b border-gray-100">
                  <span class="col-span-5">Items</span>
                  <span class="col-span-2 text-center">QTY</span>
                  <span class="col-span-3 text-right">Price / Tax</span>
                  <span class="col-span-2 text-right pr-2">Amount</span>
                </div>
                <div class="divide-y divide-gray-100">
                  <div v-for="(it, i) in form.items" :key="i" class="grid grid-cols-12 gap-4 px-5 py-4 items-start hover:bg-gray-50/20 transition-colors">
                    <!-- Col 1: Description -->
                    <div class="col-span-5">
                      <input v-model="it.description" type="text" class="inv-input font-medium !bg-white" placeholder="Description *" required />
                    </div>
                    <!-- Col 2: QTY -->
                    <div class="col-span-2">
                      <input v-model="it.quantity" type="number" min="0.001" step="0.001" class="inv-input text-center tabular-nums !bg-white" placeholder="1.00" />
                    </div>
                    <!-- Col 3: Rate + Tax/GST -->
                    <div class="col-span-3 space-y-2">
                      <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₹</span>
                        <input v-model="it.unit_price" type="number" min="0" step="0.01" class="inv-input pl-7 text-right tabular-nums !bg-white" placeholder="0.00" />
                      </div>
                      <select v-model="it.gst_rate" class="inv-select text-center text-xs !bg-white">
                        <option v-for="r in gstRates" :key="r" :value="r">{{ r }}% GST</option>
                      </select>
                    </div>
                    <!-- Col 4: Total + remove -->
                    <div class="col-span-2 flex flex-col items-end justify-between h-[86px] py-1">
                      <span class="text-sm font-semibold text-gray-800 tabular-nums pr-2">{{ inr(lineTotal(it)) }}</span>
                      <button v-if="form.items.length > 1" type="button" @click="removeItem(i)"
                        class="mr-1 w-7 h-7 flex items-center justify-center text-gray-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition" title="Remove">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                      </button>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Mobile: stacked cards -->
              <div class="lg:hidden divide-y divide-gray-100">
                <div v-for="(it, i) in form.items" :key="i" class="p-4 space-y-3">
                  <div class="flex items-start justify-between gap-3">
                    <input v-model="it.description" type="text" class="inv-input flex-1" placeholder="Description *" required />
                    <button v-if="form.items.length > 1" type="button" @click="removeItem(i)" class="text-gray-300 hover:text-red-500 p-2 transition">✕</button>
                  </div>
                  <div class="grid grid-cols-2 gap-2">
                    <div><label class="inv-label">Qty</label><input v-model="it.quantity" type="number" class="inv-input w-full" /></div>
                    <div><label class="inv-label">Rate ₹</label><input v-model="it.unit_price" type="number" class="inv-input w-full" /></div>
                    <div><label class="inv-label">GST</label><select v-model="it.gst_rate" class="inv-select w-full"><option v-for="r in gstRates" :key="r" :value="r">{{ r }}%</option></select></div>
                    <div class="flex items-end pb-1"><span class="text-sm font-bold text-gray-900">{{ inr(lineTotal(it)) }}</span></div>
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
            </template>
          </div>

          <!-- Notes -->
          <div class="inv-card p-5 space-y-3">
            <h2 class="text-sm font-semibold text-gray-700">Notes</h2>
            <label class="inv-label">Additional information</label>
            <textarea v-model="form.notes" rows="3" class="inv-textarea w-full" placeholder="Reason or additional info…"></textarea>
          </div>

        </div><!-- /inv-main -->

        <!-- RIGHT: Sidebar -->
        <aside class="inv-sidebar">

          <!-- Invoice Reference -->
          <div class="inv-card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-800">Invoice Reference</h2>
            <div>
              <label class="inv-label">Against Invoice *</label>
              <select v-model="form.invoice_id" class="inv-select w-full" @change="prefillFromInvoice(form.invoice_id)">
                <option value="">Select Invoice</option>
                <option v-for="inv in invoices" :key="inv.id" :value="inv.id">
                  {{ inv.number }} — {{ inv.client_name }} ({{ inr(inv.total) }})
                </option>
              </select>
            </div>
          </div>

          <!-- Credit Note Details -->
          <div class="inv-card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-800">Credit Note Details</h2>
            <div>
              <label class="inv-label">Reason *</label>
              <select v-model="form.reason" class="inv-select w-full">
                <option v-for="r in reasons" :key="r.value" :value="r.value">{{ r.label }}</option>
              </select>
            </div>
            <div>
              <label class="inv-label">Date *</label>
              <input v-model="form.issue_date" type="date" class="inv-input w-full" />
            </div>
          </div>

          <!-- Summary -->
          <div class="inv-card p-5 space-y-3">
            <div class="space-y-2 text-sm">
              <div class="flex justify-between items-center pt-1">
                <span class="font-semibold text-gray-800">Credit Total</span>
                <span class="text-xl font-bold tabular-nums text-primary-600">{{ inr(grandTotal()) }}</span>
              </div>
            </div>
            <div v-if="error" class="text-xs text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2">{{ error }}</div>
          </div>

        </aside>
      </form>
    </div>

    <!-- Mobile sticky footer -->
    <div class="form-footer-mobile lg:hidden">
      <div class="flex-1 min-w-0">
        <p class="text-[10px] font-bold text-google-muted uppercase">Credit Total</p>
        <p class="text-lg font-bold text-primary-600 tabular-nums">{{ inr(grandTotal()) }}</p>
      </div>
      <button type="button" @click="router.push('/credit-notes')" class="btn-outline">Cancel</button>
      <button type="submit" form="credit-note-form" class="btn-primary" :disabled="saving || loading">
        {{ saving ? '…' : 'Create' }}
      </button>
    </div>
  </div>
</template>
