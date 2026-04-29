<script setup>
import { ref, onMounted } from 'vue'
import { list, task } from '../../api'
import HelpIcon from '../../components/HelpIcon.vue'
import { inr } from '../../utils/currency'
import { fmtDateShort, today } from '../../utils/date'

const creditNotes = ref([])
const invoices    = ref([])
const loading     = ref(true)
const showModal   = ref(false)
const saving      = ref(false)
const error       = ref('')
const acting      = ref(null)
const actError    = ref('')

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

const statusBadge = (s) => ({
  draft:    'badge-gray',
  issued:   'badge-blue',
  adjusted: 'badge-green',
}[s] || 'badge-gray')

async function load() {
  loading.value = true
  try {
    const [cnRes, invRes] = await Promise.all([
      list('CreditNote', { sort_by: 'cn.created_at', sort_order: 'desc' }),
      list('Invoice',    { sort_by: 'i.created_at',  sort_order: 'desc', limit: 500 }),
    ])
    creditNotes.value = cnRes.data?.data  || []
    invoices.value    = invRes.data?.data || []
  } catch {}
  loading.value = false
}

function openCreate() {
  form.value = { invoice_id: '', reason: 'return', issue_date: today(), notes: '', items: [blankItem()] }
  error.value = ''
  showModal.value = true
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
    showModal.value = false
    await load()
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to create credit note.'
  } finally { saving.value = false }
}

async function issueCN(cn) {
  acting.value  = cn.id + '_issue'
  actError.value = ''
  try {
    await task('CreditNote', 'issue', { id: cn.id })
    await load()
  } catch (e) {
    actError.value = e.response?.data?.message || 'Failed to issue.'
  } finally { acting.value = null }
}

async function adjustCN(cn) {
  acting.value  = cn.id + '_adjust'
  actError.value = ''
  try {
    await task('CreditNote', 'adjust', { id: cn.id })
    await load()
  } catch (e) {
    actError.value = e.response?.data?.message || 'Failed to adjust.'
  } finally { acting.value = null }
}

onMounted(load)
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center justify-between">
      <h1 class="page-title flex items-center gap-2">Credit Notes <HelpIcon section="returns" /></h1>
      <button @click="openCreate" class="btn-primary">+ New Credit Note</button>
    </div>

    <div v-if="actError" class="text-sm text-danger-600 bg-danger-50 rounded-lg px-4 py-3">{{ actError }}</div>

    <div class="card">
      <div v-if="loading" class="p-10 text-center text-gray-400 text-sm">Loading…</div>
      <div v-else-if="!creditNotes.length" class="p-10 text-center">
        <p class="text-gray-500 font-medium">No credit notes yet</p>
        <p class="text-sm text-gray-400 mt-1">Issue credit notes to reverse or reduce invoice amounts</p>
        <button @click="openCreate" class="btn-primary btn-sm mt-4">New Credit Note</button>
      </div>
      <div v-else>
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
            <tr>
              <th class="px-5 py-3 text-left">Number</th>
              <th class="px-5 py-3 text-left hidden sm:table-cell">Invoice</th>
              <th class="px-5 py-3 text-left hidden md:table-cell">Date</th>
              <th class="px-5 py-3 text-left hidden md:table-cell">Reason</th>
              <th class="px-5 py-3 text-right">Amount</th>
              <th class="px-5 py-3 text-center">Status</th>
              <th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="cn in creditNotes" :key="cn.id" class="hover:bg-gray-50">
              <td class="px-5 py-3.5 font-medium text-gray-800">{{ cn.number }}</td>
              <td class="px-5 py-3.5 text-gray-500 hidden sm:table-cell">{{ cn.invoice_number || '—' }}</td>
              <td class="px-5 py-3.5 text-gray-500 hidden md:table-cell">{{ fmtDateShort(cn.issue_date) }}</td>
              <td class="px-5 py-3.5 text-gray-500 hidden md:table-cell capitalize">{{ cn.reason }}</td>
              <td class="px-5 py-3.5 text-right font-semibold text-gray-900">{{ inr(cn.total) }}</td>
              <td class="px-5 py-3.5 text-center">
                <span :class="statusBadge(cn.status)">{{ cn.status }}</span>
              </td>
              <td class="px-5 py-3.5">
                <div class="flex items-center gap-2 justify-end">
                  <button v-if="cn.status === 'draft'" @click="issueCN(cn)"
                    :disabled="acting === cn.id + '_issue'"
                    class="text-xs btn-outline px-2 py-1">
                    {{ acting === cn.id + '_issue' ? '…' : 'Issue' }}
                  </button>
                  <button v-if="cn.status === 'issued'" @click="adjustCN(cn)"
                    :disabled="acting === cn.id + '_adjust'"
                    class="text-xs btn-primary px-2 py-1">
                    {{ acting === cn.id + '_adjust' ? '…' : 'Adjust Invoice' }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/40">
      <div class="bg-white rounded-2xl w-full max-w-lg shadow-xl max-h-[90vh] overflow-y-auto">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between sticky top-0 bg-white">
          <h3 class="font-semibold text-gray-800">New Credit Note</h3>
          <button @click="showModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
        <div class="p-5 space-y-4">
          <div>
            <label class="form-label">Invoice *</label>
            <select v-model="form.invoice_id" class="form-select">
              <option value="">Select Invoice</option>
              <option v-for="inv in invoices" :key="inv.id" :value="inv.id">
                {{ inv.number }} — {{ inv.client_name }} ({{ inr(inv.total) }})
              </option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-3">
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

          <!-- Items -->
          <div class="border border-gray-200 rounded-lg overflow-hidden">
            <div class="bg-gray-50 px-4 py-2 text-xs font-semibold text-gray-500 uppercase flex items-center justify-between">
              <span>Items</span>
              <button type="button" @click="addItem" class="text-primary-600 text-xs font-medium">+ Add</button>
            </div>
            <div v-for="(it, i) in form.items" :key="i" class="p-3 space-y-2 border-t border-gray-100">
              <input v-model="it.description" type="text" class="form-input" placeholder="Description *" />
              <div class="grid grid-cols-4 gap-2">
                <input v-model="it.quantity"   type="number" min="0.001" class="form-input" placeholder="Qty" />
                <input v-model="it.unit_price" type="number" min="0" step="0.01" class="form-input" placeholder="Rate ₹" />
                <select v-model="it.gst_rate" class="form-select">
                  <option v-for="r in gstRates" :key="r" :value="r">{{ r }}%</option>
                </select>
                <div class="flex items-center justify-between">
                  <span class="text-xs font-medium text-gray-700">{{ inr(lineTotal(it)) }}</span>
                  <button v-if="form.items.length > 1" type="button" @click="removeItem(i)" class="text-danger-500 text-xs ml-1">✕</button>
                </div>
              </div>
            </div>
          </div>

          <div>
            <label class="form-label">Notes</label>
            <textarea v-model="form.notes" rows="2" class="form-input" placeholder="Reason or additional info…"></textarea>
          </div>

          <div v-if="error" class="text-sm text-danger-500 bg-danger-50 rounded-lg px-3 py-2">{{ error }}</div>
        </div>
        <div class="px-5 pb-5 flex gap-3 sticky bottom-0 bg-white border-t border-gray-100 pt-4">
          <button @click="showModal = false" class="btn-outline flex-1">Cancel</button>
          <button @click="save" :disabled="saving" class="btn-primary flex-1">
            {{ saving ? 'Creating…' : 'Create Credit Note' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
