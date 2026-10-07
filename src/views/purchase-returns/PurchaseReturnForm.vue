<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { task, item, list } from '../../api'
import { inr } from '../../utils/currency'
import { today } from '../../utils/date'
import { useToast } from '../../composables/useToast'
import { useFormKeys } from '../../composables/useFormKeys'

const router = useRouter()
const route  = useRoute()
const emit   = defineEmits(['refresh'])
const toast  = useToast()
useFormKeys({ formId: 'pr-form', autoFocus: false })

const pi       = ref(null)
const piItems  = ref([])
const loading  = ref(false)
const error    = ref('')

const reasons = [
  { value: 'defective',  label: 'Defective / Damaged' },
  { value: 'expired',    label: 'Expired' },
  { value: 'excess',     label: 'Excess Quantity' },
  { value: 'wrong_item', label: 'Wrong Item' },
  { value: 'other',      label: 'Other' },
]

const form = ref({
  pi_id:       '',
  reason:      'defective',
  return_date: today(),
  notes:       '',
  items:       [],
})

const totals = computed(() => {
  let subtotal = 0, tax = 0
  for (const it of form.value.items) {
    if (!it.selected) continue
    const taxable = parseFloat(it.return_qty || 0) * parseFloat(it.unit_price || 0)
    subtotal += taxable
    tax += taxable * parseFloat(it.gst_rate || 0) / 100
  }
  const rawTotal = subtotal + tax
  return { subtotal, tax, total: Math.round(rawTotal) }
})

const selectedCount = computed(() => form.value.items.filter(i => i.selected).length)

onMounted(async () => {
  const piId = route.query.pi_id
  if (!piId) { error.value = 'No purchase invoice specified.'; return }
  form.value.pi_id = piId

  try {
    const [piRes, itmRes] = await Promise.all([
      item('PurchaseInvoice', { id: piId }),
      list('PurchaseInvoice:items', { pi_id: piId }),
    ])
    pi.value      = piRes.data?.data
    piItems.value = itmRes.data?.data || []

    form.value.items = piItems.value.map(it => ({
      selected:    true,
      product_id:  it.product_id || null,
      description: it.description,
      hsn_sac:     it.hsn_sac || '',
      unit:        it.unit || 'Nos',
      quantity:    it.quantity,
      return_qty:  it.quantity,
      unit_price:  it.unit_price,
      gst_rate:    parseFloat(it.gst_rate || 0),
      batch_no:    it.batch_no || '',
      expiry_date: it.expiry_date || '',
    }))
  } catch { error.value = 'Could not load purchase invoice.' }
})

async function submit() {
  error.value = ''
  const selected = form.value.items.filter(i => i.selected && parseFloat(i.return_qty) > 0)
  if (!selected.length) return (error.value = 'Select at least one item to return.')

  for (const it of selected) {
    if (parseFloat(it.return_qty) > parseFloat(it.quantity)) {
      error.value = `Return qty for "${it.description}" cannot exceed original qty (${it.quantity}).`
      return
    }
  }

  loading.value = true
  try {
    const payload = {
      pi_id:       form.value.pi_id,
      reason:      form.value.reason,
      return_date: form.value.return_date,
      notes:       form.value.notes,
      items: selected.map(it => ({
        product_id:  it.product_id,
        description: it.description,
        hsn_sac:     it.hsn_sac,
        unit:        it.unit,
        quantity:    parseFloat(it.return_qty),
        unit_price:  parseFloat(it.unit_price),
        gst_rate:    it.gst_rate,
        batch_no:    it.batch_no || null,
        expiry_date: it.expiry_date || null,
      })),
    }
    const res = await task('PurchaseReturn', 'create', payload)
    emit('refresh')
    toast.success('Purchase return created.')
    const newId = res.data?.data?.id
    router.push(newId ? `/purchase-returns/${newId}` : '/purchase-returns')
  } catch (e) {
    error.value = e.response?.data?.message || 'Failed to create return.'
  }
  loading.value = false
}
</script>

<template>
  <div class="inv-shell">
    <div class="inv-toolbar">
      <div class="flex items-center gap-3 min-w-0">
        <button type="button" @click="router.push('/purchase-returns')" class="inv-back-btn">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <h1 class="inv-page-title">Return Items to Supplier</h1>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" @click="router.back()" class="inv-btn-secondary hidden sm:inline-flex">Cancel</button>
        <button type="submit" form="pr-form" class="inv-btn-primary" :disabled="loading || !selectedCount">
          {{ loading ? 'Saving…' : 'Create Return' }}
        </button>
      </div>
    </div>

    <div class="inv-body">
      <form id="pr-form" @submit.prevent="submit" class="inv-layout">

        <div class="inv-main">
          <!-- Source PI info -->
          <div v-if="pi" class="inv-card p-5">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-full bg-orange-50 text-orange-600 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
              </div>
              <div>
                <p class="text-sm font-bold text-gray-900">{{ pi.supplier_name }} · {{ pi.number }}</p>
                <p class="text-xs text-gray-500">Total: {{ inr(pi.total) }} · Due: {{ inr(pi.amount_due) }}</p>
              </div>
            </div>
          </div>

          <!-- Items to return -->
          <div class="inv-card !overflow-visible">
            <div class="px-5 py-3.5 border-b border-gray-100">
              <h2 class="text-sm font-semibold text-gray-800">Select Items to Return</h2>
            </div>
            <div class="divide-y divide-gray-100">
              <div v-for="(it, idx) in form.items" :key="idx" class="px-5 py-4 space-y-3">
                <div class="flex items-start gap-3">
                  <label class="flex items-center mt-1">
                    <input type="checkbox" v-model="it.selected" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
                  </label>
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800">{{ it.description }}</p>
                    <p class="text-xs text-gray-500">{{ it.unit }} · GST {{ it.gst_rate }}%<template v-if="it.batch_no"> · Batch: {{ it.batch_no }}</template></p>
                  </div>
                  <div class="text-right shrink-0">
                    <p class="text-sm font-semibold text-gray-700">{{ inr(it.unit_price) }}</p>
                    <p class="text-xs text-gray-400">Purchased: {{ it.quantity }}</p>
                  </div>
                </div>
                <div v-if="it.selected" class="ml-8 flex items-center gap-3">
                  <label class="text-xs text-gray-500 shrink-0">Return Qty:</label>
                  <input v-model="it.return_qty" type="number" min="0.01" :max="it.quantity" step="0.01"
                    class="inv-input w-24 text-center" />
                  <span class="text-xs text-gray-400">of {{ it.quantity }}</span>
                  <span class="text-sm font-semibold text-gray-700 ml-auto">{{ inr(parseFloat(it.return_qty || 0) * parseFloat(it.unit_price || 0)) }}</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Notes -->
          <div class="inv-card p-5 space-y-3">
            <h2 class="text-sm font-semibold text-gray-700">Notes</h2>
            <textarea v-model="form.notes" rows="2" class="inv-textarea w-full" placeholder="Reason details…"></textarea>
          </div>
        </div>

        <aside class="inv-sidebar">
          <div class="inv-card p-5 space-y-4">
            <h2 class="text-sm font-semibold text-gray-800">Return Details</h2>
            <div class="space-y-3">
              <div>
                <label class="inv-label">Reason *</label>
                <select v-model="form.reason" class="inv-select w-full">
                  <option v-for="r in reasons" :key="r.value" :value="r.value">{{ r.label }}</option>
                </select>
              </div>
              <div>
                <label class="inv-label">Return Date *</label>
                <input v-model="form.return_date" type="date" class="inv-input w-full" required />
              </div>
            </div>
          </div>

          <div class="inv-card p-5 space-y-3">
            <div class="space-y-2 text-sm">
              <div class="flex justify-between text-gray-500"><span>Subtotal</span><span class="font-medium text-gray-800 tabular-nums">{{ inr(totals.subtotal) }}</span></div>
              <div v-if="totals.tax > 0" class="flex justify-between text-gray-500"><span>GST</span><span class="font-medium text-gray-800 tabular-nums">{{ inr(totals.tax) }}</span></div>
              <div class="flex justify-between items-center pt-3 border-t border-gray-100">
                <span class="font-semibold text-gray-800">Return Total</span>
                <span class="text-xl font-bold tabular-nums" :class="totals.total > 0 ? 'text-amber-600' : 'text-gray-400'">{{ inr(totals.total) }}</span>
              </div>
            </div>
            <p class="text-[10px] text-gray-400">{{ selectedCount }} item{{ selectedCount !== 1 ? 's' : '' }} selected for return</p>
            <div v-if="error" class="text-xs text-red-600 bg-red-50 border border-red-100 rounded-lg px-3 py-2">{{ error }}</div>
          </div>
        </aside>
      </form>
    </div>
  </div>
</template>
