<script setup>
import { computed, onMounted, ref } from 'vue'
import { all, task } from '../../api'
import { inr } from '../../utils/currency'
import InfoTip from '../../components/InfoTip.vue'
import HelpIcon from '../../components/HelpIcon.vue'

const loading = ref(true)
const busy = ref(false)
const error = ref('')
const message = ref('')
const data = ref({ settings: { inventory_mode: 'none', inventory_advanced: 0 }, locations: [], stock: [], summary: {}, movements: [], transfers: [] })
const products = ref([])
const tab = ref('stock')
const locationFilter = ref('')
const settings = ref({ mode: 'none', advanced: false })
const adjust = ref({ product_id: '', location_id: '', kind: 'purchase', quantity: '', unit_cost: '', batch_no: '', expiry_date: '', serial_numbers: '', note: '' })
const place = ref({ name: '', type: 'shop', address: '', is_default: false, active: true })
const transfer = ref({ from_location_id: '', to_location_id: '', transfer_date: new Date().toISOString().slice(0, 10), notes: '', items: [{ product_id: '', quantity: '' }] })
const search = ref('')
const stockFilter = ref('all')
const modeText = { none: 'Billing only', warn: 'Show stock', strict: 'Control stock' }
const kindText = { opening: 'Opening Stock', purchase: 'Purchase Stock In', purchase_return: 'Purchase Return to Supplier', adjustment: 'Stock Correction', damage: 'Damaged / Expired', count: 'Physical Stock Count' }

const tabs = [
  { key: 'stock', label: 'Current Stock' },
  { key: 'in', label: 'Stock In / Correction' },
  { key: 'transfer', label: 'Move Stock' },
  { key: 'places', label: 'Shops / Godowns' },
  { key: 'history', label: 'Stock History' },
]

const filteredStock = computed(() => data.value.stock.filter(s => {
  const q = search.value.toLowerCase()
  if (q && !`${s.name} ${s.sku || ''} ${s.barcode || ''}`.toLowerCase().includes(q)) return false
  if (stockFilter.value === 'low') return +s.quantity > 0 && +s.quantity <= +s.reorder_level
  if (stockFilter.value === 'out') return +s.quantity <= 0
  if (stockFilter.value === 'dead') return +s.quantity > 0 && s.last_moved_at && new Date(s.last_moved_at) < new Date(Date.now() - 90 * 86400000)
  return true
}))

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [o, p] = await Promise.all([
      task('Inventory', 'overview', locationFilter.value ? { location_id: locationFilter.value } : {}),
      all('Product')
    ])
    data.value = o.data.data
    products.value = (p.data.data || []).filter(x => x.type === 'product')
    settings.value = { mode: data.value.settings.inventory_mode, advanced: !!+data.value.settings.inventory_advanced }
    if (!adjust.value.location_id) adjust.value.location_id = data.value.defaultId
    if (!transfer.value.from_location_id) transfer.value.from_location_id = data.value.defaultId
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not load stock.'
  }
  loading.value = false
}

async function run(method, payload, ok) {
  busy.value = true
  error.value = ''
  message.value = ''
  try {
    await task('Inventory', method, payload)
    message.value = ok
    await load()
  } catch (e) {
    error.value = e.response?.data?.message || 'Could not save.'
  }
  busy.value = false
}

const blankPlace = () => ({ name: '', type: 'shop', address: '', is_default: false, active: true })

async function saveLocation() {
  await run('saveLocation', place.value, 'Shop / Godown saved.')
  if (!error.value) place.value = blankPlace()
}

function editLocation(l) {
  place.value = { id: l.id, name: l.name, type: l.type, address: l.address || '', is_default: !!+l.is_default, active: !!+l.active }
}

function cancelEdit() {
  place.value = blankPlace()
}

async function deleteLocation(l) {
  if (!confirm(`Delete "${l.name}"? This cannot be undone.`)) return
  await run('deleteLocation', { id: l.id }, 'Location deleted.')
}

function saveAdjustment() {
  const payload = { ...adjust.value, serial_numbers: adjust.value.serial_numbers.split(/[,\n]/).map(x => x.trim()).filter(Boolean) }
  run('adjust', payload, 'Stock updated.')
  adjust.value = { ...adjust.value, product_id: '', quantity: '', unit_cost: '', batch_no: '', expiry_date: '', serial_numbers: '', note: '' }
}

function addTransferRow() { transfer.value.items.push({ product_id: '', quantity: '' }) }
const saveTransfer = () => run('createTransfer', transfer.value, 'Transfer prepared. Send it when goods leave the shop.')
const dispatch = id => run('dispatchTransfer', { id }, 'Stock sent and marked In transit.')
const receive = id => run('receiveTransfer', { id }, 'Stock received at destination.')

onMounted(load)
</script>

<template>
<div class="gpay-screen">
<div class="max-w-5xl mx-auto w-full px-4 py-4 lg:px-6 lg:py-6 pb-24 space-y-5">

  <!-- Header -->
  <div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="page-title flex items-center gap-2">
      Stock <HelpIcon section="inventory" class="w-3.5 h-3.5" />
    </h1>
    <span class="badge badge-blue">{{ modeText[settings.mode] }}</span>
  </div>

  <!-- Alerts -->
  <div v-if="error" class="card card-body !py-3 border-red-200 bg-red-50 text-sm text-red-700">{{ error }}</div>
  <div v-if="message" class="card card-body !py-3 border-green-200 bg-green-50 text-sm text-green-700">{{ message }}</div>

  <!-- Tabs -->
  <div class="flex gap-1.5 overflow-x-auto pb-1">
    <button v-for="t in tabs" :key="t.key" @click="tab = t.key"
      class="px-4 py-2 rounded-xl text-sm font-semibold whitespace-nowrap transition-all duration-150 shrink-0"
      :class="tab === t.key
        ? 'bg-primary-600 text-white shadow-sm shadow-primary-200'
        : 'bg-white text-gray-500 border border-gray-200 hover:border-gray-300 hover:text-gray-700'">
      {{ t.label }}
    </button>
  </div>

  <!-- Loading -->
  <div v-if="loading" class="card p-10 text-center text-gray-400 text-sm">Loading your stock book...</div>

  <template v-else>

    <!-- ━━ Current Stock ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
    <section v-if="tab === 'stock'" class="space-y-4">

      <!-- Summary cards -->
      <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
        <div v-for="c in [
          [data.summary.products, 'Stock Items', 'Products whose stock is maintained'],
          [data.summary.low, 'Low Stock', 'Products at or below the alert quantity'],
          [data.summary.out, 'Out of Stock', 'Products with no available stock'],
          [data.summary.dead, 'Dead Stock', 'Stock with no movement for more than 90 days'],
          ...(data.summary.value != null ? [[inr(data.summary.value || 0), 'Stock Value', 'Current quantity x average purchase cost']] : [])
        ]" :key="c[1]" class="card card-body">
          <div class="flex items-center gap-2 section-title !mb-0">{{ c[1] }} <InfoTip :text="c[2]" /></div>
          <p class="mt-2 text-2xl font-bold text-ink">{{ c[0] }}</p>
        </div>
      </div>

      <!-- Search & filter -->
      <div class="flex flex-wrap gap-2">
        <input v-model="search"
          class="min-w-52 flex-1 bg-white border border-gray-200 shadow-sm text-gray-900 text-xs font-semibold rounded-lg focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 px-3 py-2 transition-all"
          placeholder="Search product, SKU or barcode" />
        <select v-model="locationFilter" @change="load"
          class="bg-white border border-gray-200 shadow-sm text-gray-700 text-xs rounded-lg focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 px-3 py-2 font-bold transition-all">
          <option value="">All Shops / Godowns</option>
          <option v-for="l in data.locations.filter(x => +x.active)" :value="l.id" :key="l.id">{{ l.name }}</option>
        </select>
        <select v-model="stockFilter"
          class="bg-white border border-gray-200 shadow-sm text-gray-700 text-xs rounded-lg focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 px-3 py-2 font-bold transition-all">
          <option value="all">All products</option>
          <option value="low">Low stock</option>
          <option value="out">Out of stock</option>
          <option value="dead">Dead stock (90+ days)</option>
        </select>
      </div>

      <!-- Stock list -->
      <div class="card overflow-hidden">
        <div v-if="!filteredStock.length" class="p-10 text-center text-sm text-gray-500">
          No stock items yet. Enable "Maintain stock" on a product, then add opening stock.
        </div>
        <div v-for="s in filteredStock" :key="`${s.product_id}-${s.location_id}`"
          class="flex items-center gap-3 border-b border-google-divider/50 last:border-0 px-4 py-3.5 hover:bg-surface-dim/30 transition-colors">
          <div class="min-w-0 flex-1">
            <p class="text-[14px] font-bold text-gray-900 tracking-tight">{{ s.name }}</p>
            <p class="text-[11px] text-gray-500 mt-0.5">
              {{ s.location_name }}
              <span v-if="s.sku"> · SKU {{ s.sku }}</span>
            </p>
          </div>
          <span v-if="+s.quantity <= 0" class="badge badge-red">OUT</span>
          <span v-else-if="+s.quantity <= +s.reorder_level" class="badge badge-yellow">LOW</span>
          <div class="text-right shrink-0">
            <p class="text-[15px] font-bold text-ink tabular-nums">{{ +s.quantity }} {{ s.unit }}</p>
            <p v-if="s.average_cost != null" class="text-[11px] text-gray-500">{{ inr(+s.quantity * +s.average_cost) }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ━━ Stock In / Correction ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
    <section v-if="tab === 'in'" class="max-w-2xl mx-auto">
      <div class="card card-body space-y-4">
        <div class="flex items-center gap-2">
          <h2 class="text-base font-bold text-gray-900">Stock In / Correction</h2>
          <InfoTip text="Use Purchase Stock In when goods arrive. Use Stock Correction only to fix a counting mistake. Every change stays in Stock History." />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <label class="text-sm font-medium text-gray-700">
            Reason
            <select v-model="adjust.kind" class="form-input mt-1">
              <option v-for="(v, k) in kindText" :value="k" :key="k">{{ v }}</option>
            </select>
          </label>
          <label class="text-sm font-medium text-gray-700">
            Shop / Godown
            <select v-model="adjust.location_id" class="form-input mt-1">
              <option v-for="l in data.locations.filter(x => +x.active)" :value="l.id" :key="l.id">{{ l.name }}</option>
            </select>
          </label>
          <label class="text-sm font-medium text-gray-700 sm:col-span-2">
            Product
            <select v-model="adjust.product_id" class="form-input mt-1">
              <option value="">Choose product</option>
              <option v-for="p in products.filter(x => +x.track_stock)" :value="p.id" :key="p.id">{{ p.name }} ({{ p.unit }})</option>
            </select>
          </label>
          <label class="text-sm font-medium text-gray-700">
            Quantity <InfoTip text="For correction, enter + to add or - to reduce. Damaged / Expired always reduces stock." />
            <input v-model="adjust.quantity" type="number" step="0.001" class="form-input mt-1" />
          </label>
          <label class="text-sm font-medium text-gray-700">
            Purchase cost per unit
            <input v-model="adjust.unit_cost" type="number" step="0.01" class="form-input mt-1" />
          </label>
          <template v-if="settings.advanced">
            <label class="text-sm font-medium text-gray-700">
              Batch / Lot number
              <input v-model="adjust.batch_no" class="form-input mt-1" />
            </label>
            <label class="text-sm font-medium text-gray-700">
              Expiry date
              <input v-model="adjust.expiry_date" type="date" class="form-input mt-1" />
            </label>
            <label class="text-sm font-medium text-gray-700 sm:col-span-2">
              Serial / IMEI numbers <InfoTip text="Enter one number per line or separate with commas. Useful for mobiles and electronics." />
              <textarea v-model="adjust.serial_numbers" rows="2" class="form-input mt-1"></textarea>
            </label>
          </template>
          <label class="text-sm font-medium text-gray-700 sm:col-span-2">
            Reason / note
            <input v-model="adjust.note" class="form-input mt-1" placeholder="Example: Supplier bill 174, physical count correction" />
          </label>
        </div>

        <button @click="saveAdjustment" :disabled="busy || !adjust.product_id || !adjust.quantity"
          class="btn-primary">
          Update Stock
        </button>
      </div>
    </section>

    <!-- ━━ Move Stock ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
    <section v-if="tab === 'transfer'" class="grid gap-5 lg:grid-cols-2">

      <!-- Transfer form -->
      <div class="card card-body space-y-4">
        <div class="flex items-center gap-2">
          <h2 class="text-base font-bold text-gray-900">Move Stock</h2>
          <InfoTip text="Stock leaves the first location when you tap Send. It reaches the second location only when the receiving person confirms Received." />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <select v-model="transfer.from_location_id" class="form-input">
            <option value="">From Shop / Godown</option>
            <option v-for="l in data.locations" :value="l.id" :key="l.id">{{ l.name }}</option>
          </select>
          <select v-model="transfer.to_location_id" class="form-input">
            <option value="">To Shop / Godown</option>
            <option v-for="l in data.locations" :value="l.id" :key="l.id">{{ l.name }}</option>
          </select>
        </div>

        <div v-for="(i, n) in transfer.items" :key="n" class="flex gap-2">
          <select v-model="i.product_id" class="form-input min-w-0 flex-1">
            <option value="">Product</option>
            <option v-for="p in products.filter(x => +x.track_stock)" :value="p.id" :key="p.id">{{ p.name }}</option>
          </select>
          <input v-model="i.quantity" type="number" step="0.001" placeholder="Qty" class="form-input w-24" />
        </div>

        <button @click="addTransferRow" class="text-sm font-semibold text-primary-600 hover:text-primary-700">+ Add another product</button>
        <textarea v-model="transfer.notes" class="form-input" placeholder="Optional note"></textarea>
        <button @click="saveTransfer" :disabled="busy" class="btn-primary">Prepare Transfer</button>
      </div>

      <!-- Recent transfers -->
      <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b border-google-divider/50 font-bold text-gray-900">Recent Transfers</div>
        <div v-if="!data.transfers.length" class="p-8 text-center text-sm text-gray-400">No transfers yet</div>
        <div v-for="t in data.transfers" :key="t.id"
          class="border-b border-google-divider/50 last:border-0 px-4 py-3.5">
          <div class="flex justify-between gap-2">
            <div>
              <p class="text-[14px] font-bold text-gray-900">{{ t.transfer_no }}</p>
              <p class="text-[11px] text-gray-500 mt-0.5">{{ t.from_name }} → {{ t.to_name }}</p>
            </div>
            <span class="badge"
              :class="{ 'badge-gray': t.status === 'draft', 'badge-yellow': t.status === 'dispatched', 'badge-green': t.status === 'received', 'badge-red': t.status === 'cancelled' }">
              {{ t.status }}
            </span>
          </div>
          <div class="mt-3 flex gap-2" v-if="t.status === 'draft' || t.status === 'dispatched'">
            <button v-if="t.status === 'draft'" @click="dispatch(t.id)"
              class="btn-sm btn-outline text-amber-600 border-amber-300 hover:bg-amber-50">
              Send Stock
            </button>
            <button v-if="t.status === 'dispatched'" @click="receive(t.id)"
              class="btn-sm btn-outline text-green-600 border-green-300 hover:bg-green-50">
              Confirm Received
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- ━━ Shops / Godowns ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
    <section v-if="tab === 'places'" class="grid gap-5 lg:grid-cols-2">

      <!-- Add / Edit location form -->
      <div class="card card-body space-y-4">
        <div class="flex items-center gap-2">
          <h2 class="text-base font-bold text-gray-900">{{ place.id ? 'Edit' : 'Add' }} Shop / Godown</h2>
          <InfoTip text="Single-shop users need only Main Shop. Add a godown or branch only when stock is physically stored there." />
        </div>
        <input v-model="place.name" class="form-input" placeholder="Example: Main Shop, Central Godown" />
        <select v-model="place.type" class="form-input">
          <option value="shop">Shop / Branch</option>
          <option value="godown">Godown</option>
          <option value="damaged">Damaged / Returns Store</option>
        </select>
        <textarea v-model="place.address" class="form-input" placeholder="Address (optional)"></textarea>
        <label v-if="!place.id" class="flex items-center gap-2 text-sm text-gray-700">
          <input v-model="place.is_default" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
          Use as default billing location
        </label>
        <label v-if="place.id" class="flex items-center gap-2 text-sm text-gray-700">
          <input v-model="place.active" type="checkbox" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" />
          Active
        </label>
        <div class="flex gap-2">
          <button @click="saveLocation" class="btn-primary">{{ place.id ? 'Update' : 'Save' }} Location</button>
          <button v-if="place.id" @click="cancelEdit" class="btn-secondary">Cancel</button>
        </div>
      </div>

      <!-- Location list -->
      <div class="card overflow-hidden">
        <div v-if="!data.locations.length" class="p-8 text-center text-sm text-gray-400">No locations yet</div>
        <div v-for="l in data.locations" :key="l.id"
          class="flex items-center justify-between border-b border-google-divider/50 last:border-0 px-4 py-3.5">
          <div>
            <p class="text-[14px] font-bold text-gray-900">{{ l.name }}</p>
            <p class="text-[11px] text-gray-500 capitalize mt-0.5">
              {{ l.type }}
              <span v-if="+l.is_default"> · Default</span>
            </p>
          </div>
          <div class="flex items-center gap-2">
            <span class="badge" :class="+l.active ? 'badge-green' : 'badge-gray'">
              {{ +l.active ? 'Active' : 'Inactive' }}
            </span>
            <button @click="editLocation(l)" class="text-primary-600 hover:text-primary-800 text-xs font-medium">Edit</button>
            <button v-if="!+l.is_default" @click="deleteLocation(l)" class="text-red-500 hover:text-red-700 text-xs font-medium">Delete</button>
          </div>
        </div>
      </div>
    </section>

    <!-- ━━ Stock History ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
    <section v-if="tab === 'history'">
      <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b border-google-divider/50">
          <h2 class="font-bold text-gray-900">Stock History</h2>
          <p class="text-[11px] text-gray-500 mt-0.5">Permanent audit trail. Posted entries are reversed, never silently deleted.</p>
        </div>
        <div v-if="!data.movements.length" class="p-8 text-center text-sm text-gray-400">No movements recorded yet</div>
        <div v-for="m in data.movements" :key="m.id"
          class="flex gap-3 border-b border-google-divider/50 last:border-0 px-4 py-3.5 hover:bg-surface-dim/30 transition-colors">
          <div class="flex-1 min-w-0">
            <p class="text-[14px] font-bold text-gray-900 tracking-tight">{{ m.product_name }}</p>
            <p class="text-[11px] text-gray-500 mt-0.5">
              {{ m.location_name }} · {{ m.movement_type.replaceAll('_', ' ') }} · {{ m.note || 'No note' }}
            </p>
            <p class="text-[10px] text-gray-400 mt-0.5">{{ m.occurred_at }} · {{ m.user_name || 'System' }}</p>
          </div>
          <div class="text-right shrink-0">
            <p class="text-[15px] font-bold tabular-nums" :class="+m.quantity >= 0 ? 'text-green-700' : 'text-red-700'">
              {{ +m.quantity >= 0 ? '+' : '' }}{{ +m.quantity }} {{ m.unit }}
            </p>
            <p class="text-[11px] text-gray-500">Balance {{ +m.balance_after }}</p>
          </div>
        </div>
      </div>
    </section>

  </template>
</div>
</div>
</template>
