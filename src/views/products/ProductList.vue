<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { list, task, all } from '../../api'
import HelpIcon from '../../components/HelpIcon.vue'
import { inr } from '../../utils/currency'

const router      = useRouter()
const products    = ref([])
const taxRates    = ref([])
const loading     = ref(true)
const deleteTarget = ref(null)
const deleting    = ref(false)
const searchQ     = ref('')
const typeFilter  = ref('')

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
  router.push('/products/new')
}

function onRowClick(p) {
  router.push(`/products/${p.id}/edit`)
}

async function confirmDelete() {
  deleting.value = true
  try {
    await task('Product', 'delete', { id: deleteTarget.value.id })
    deleteTarget.value = null
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
  <div class="space-y-5">

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

      <div class="flex flex-col lg:flex-row gap-3 items-start lg:items-center justify-between">
        <!-- Type filter -->
        <div class="flex gap-1 overflow-x-auto pb-1 no-scrollbar w-full lg:w-auto">
          <button v-for="t in [['', 'All'], ['product', 'Products'], ['service', 'Services']]" :key="t[0]"
            @click="typeFilter = t[0]"
            class="px-3 py-1.5 rounded-xl text-xs font-semibold transition-all shrink-0"
            :class="typeFilter === t[0] ? 'bg-primary-600 text-white' : 'bg-white text-gray-500 border border-gray-200 hover:border-gray-300'">
            {{ t[1] }}
          </button>
        </div>

        <!-- Search -->
        <div class="relative w-full lg:max-w-md animate-fade-in-up">
          <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input v-model="searchQ" type="text" class="w-full bg-white border-0 shadow-soft text-gray-900 text-sm rounded-full focus:ring-2 focus:ring-primary-500 block pl-12 p-3.5 transition-shadow" placeholder="Search by name or HSN/SAC…" />
        </div>
      </div>
    </div>

    <!-- List -->
    <div class="bg-white rounded-[2rem] shadow-soft border-0 overflow-hidden mt-2 lg:mt-0 animate-fade-in-up anim-delay-75">
          <div v-if="loading" class="p-12 text-center text-gray-400 text-sm">Loading…</div>
          <div v-else-if="!filteredProducts().length" class="p-12 text-center">
            <div class="w-16 h-16 rounded-full bg-primary-50 flex items-center justify-center mx-auto mb-4">
              <svg class="w-8 h-8 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
            <p class="font-extrabold text-gray-900 text-lg">No items found</p>
            <p class="text-sm text-gray-500 mt-1">Add products or services to use in bills</p>
            <button @click="openAdd" class="btn bg-primary-600 text-white hover:bg-primary-700 shadow-soft-blue rounded-full px-6 py-2.5 mt-5 inline-flex items-center gap-2 font-bold">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
              Add First Item
            </button>
          </div>
          <div v-else class="divide-y divide-gray-50">
            <div v-for="p in filteredProducts()" :key="p.id"
              class="flex items-center gap-4 px-5 py-4 hover:bg-gray-50 active:bg-gray-100 cursor-pointer transition-colors group"
              @click="onRowClick(p)">

              <div class="w-12 h-12 rounded-full flex items-center justify-center shrink-0 text-lg font-extrabold"
                :class="typeColors[p.type] || 'bg-gray-100 text-gray-600'">
                {{ p.type === 'service' ? 'S' : 'P' }}
              </div>

              <div class="flex-1 min-w-0">
                <p class="font-bold text-gray-900 text-base truncate group-hover:text-primary-700 transition-colors">{{ p.name }}</p>
                <p class="text-xs text-gray-500 mt-0.5 truncate">
                  {{ p.hsn_sac ? 'HSN: ' + p.hsn_sac + ' · ' : '' }}{{ p.unit }}
                  <span v-if="p.is_active === false" class="text-red-400"> · Inactive</span>
                </p>
              </div>

              <div class="text-right shrink-0 flex flex-col items-end">
                <p class="font-extrabold text-gray-900 text-base">{{ inr(p.price) }}</p>
                <p class="text-[10px] uppercase font-bold tracking-wider mt-1 text-gray-400 bg-gray-50 px-2 py-0.5 rounded-full">{{ taxLabel(p.tax_rate_id) }}</p>
              </div>

              <svg class="w-5 h-5 text-gray-300 shrink-0 group-hover:text-primary-500 group-hover:translate-x-1 transition-all ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
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
