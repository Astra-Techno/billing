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
  <div class="flex flex-col lg:flex-row h-full min-h-0 w-full overflow-hidden">
    
    <!-- Left Pane: List -->
    <div id="c3-left-panel" :class="{ 'hidden lg:flex': $route.name !== 'Products', 'w-full lg:w-[340px] border-r border-gray-200/60 flex flex-col shrink-0 bg-[#FAFAFA] transition-all duration-300 relative z-30 h-full': true }">
      
      <!-- Top Sticky Header Area -->
      <div class="px-5 py-4 border-b border-gray-200/60 bg-white/60 backdrop-blur-md sticky top-0 z-10">
        <!-- Header & Actions -->
        <div class="flex justify-between items-center mb-4">
            <h2 class="font-bold text-gray-900 text-sm tracking-tight flex items-center gap-2">Items <HelpIcon section="products" class="w-3.5 h-3.5" /></h2>
            <div class="flex gap-2">
                <!-- New Product -->
                <button @click="openAdd" class="w-7 h-7 bg-white border border-gray-200/80 shadow-sm hover:shadow hover:border-gray-300 rounded-lg flex items-center justify-center text-gray-600 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                </button>
            </div>
        </div>

        <!-- Search & Filter -->
        <div class="mb-2 space-y-2 animate-fade-in-up">
            <div class="flex gap-2">
                <input v-model="searchQ" type="text"
                  class="flex-1 bg-white border border-gray-200 shadow-sm text-gray-900 text-xs font-semibold rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 block px-3 py-2 transition-all min-w-0"
                  placeholder="Search item or HSN..." />
                  
                <div class="shrink-0 w-24 relative">
                  <select v-model="typeFilter" class="w-full h-full bg-white border border-gray-200 shadow-sm text-gray-700 text-[11px] rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 pl-2.5 pr-6 appearance-none cursor-pointer font-bold transition-all">
                    <option value="">All</option>
                    <option value="product">Products</option>
                    <option value="service">Services</option>
                  </select>
                  <svg class="w-3 h-3 text-gray-400 absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                </div>
            </div>
        </div>
      </div>

      <!-- Scrollable List -->
      <div class="flex-1 overflow-y-auto px-3 py-3 space-y-1.5 custom-scrollbar min-h-0">
          
          <div v-if="loading" class="space-y-1.5">
            <div v-for="i in 6" :key="i" class="p-4 rounded-xl border border-gray-100 bg-white/40 animate-pulse flex justify-between items-center gap-3">
              <div class="w-8 h-8 rounded-lg bg-gray-200 shrink-0"></div>
              <div class="space-y-2 flex-1"><div class="h-3.5 bg-gray-200 rounded w-24"></div><div class="h-2.5 bg-gray-100 rounded w-16"></div></div>
              <div class="h-3.5 bg-gray-200 rounded w-16 shrink-0"></div>
            </div>
          </div>

          <div v-else-if="!filteredProducts().length" class="p-8 text-center">
            <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
              <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
            <p class="font-bold text-gray-900 text-[13px]">No items found</p>
            <p class="text-[11px] text-gray-500 mt-1">Add a product or service to get started</p>
          </div>

          <div v-else v-for="(p, idx) in filteredProducts()" :key="p.id"
            class="p-4 rounded-xl border cursor-pointer transition-all group relative overflow-hidden list-item-1"
            :style="{ animationDelay: (idx * 0.05) + 's' }"
            :class="[
              $route.params.id == p.id ? 'bg-white border-gray-200 shadow-[0_2px_8px_rgba(0,0,0,0.03)]' : 'border-transparent hover:border-gray-200/60 hover:bg-white hover:shadow-[0_2px_8px_rgba(0,0,0,0.02)]'
            ]"
            @click="onRowClick(p)">
            
            <!-- Active Indicator Line -->
            <div v-if="$route.params.id == p.id" class="absolute left-0 top-0 bottom-0 w-[3px] bg-gray-900 rounded-l-xl"></div>
            
            <div class="flex gap-3 items-center">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center font-bold text-xs border shrink-0 group-hover:scale-105 transition-transform" 
                     :class="p.type === 'service' ? 'bg-purple-50 text-purple-600 border-purple-100' : 'bg-blue-50 text-blue-600 border-blue-100'">
                  {{ p.type === 'service' ? 'S' : 'P' }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-start mb-0.5">
                        <span class="text-[14px] font-bold truncate pr-2 tracking-tight transition-colors"
                              :class="$route.params.id == p.id ? 'text-indigo-600' : 'text-gray-900 group-hover:text-indigo-600'">
                              {{ p.name }}
                        </span>
                        <span class="text-[13px] font-bold tabular-nums text-gray-900 shrink-0">
                              {{ inr(p.price) }}
                        </span>
                    </div>
                    <div class="flex justify-between items-center mt-1">
                        <span class="text-[11px] font-medium text-gray-500 truncate pr-2 flex items-center gap-1.5">
                            <span v-if="p.hsn_sac" class="font-mono text-gray-400">{{ p.hsn_sac }}</span>
                            <span v-if="p.hsn_sac" class="text-gray-300">•</span>
                            <span>{{ p.unit }}</span>
                        </span>
                        
                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded tracking-wider flex items-center shrink-0 bg-gray-100 text-gray-500">
                            {{ taxLabel(p.tax_rate_id) }}
                        </span>
                    </div>
                </div>
            </div>
          </div>
      </div>
    </div>

    <!-- Right Pane: Detail/Form wrapper -->
    <div v-if="$route.name !== 'Products'" id="c3-right-view" class="flex-1 bg-[#F4F4F5] overflow-y-auto flex flex-col relative z-20 shadow-[-10px_0_20px_rgba(0,0,0,0.02)] custom-scrollbar">
      <!-- Subtle noise/texture overlay -->
      <div class="absolute inset-0 opacity-[0.03] pointer-events-none mix-blend-multiply" style="background-image: url('data:image/svg+xml,%3Csvg viewBox=%220 0 200 200%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cfilter id=%22noiseFilter%22%3E%3CfeTurbulence type=%22fractalNoise%22 baseFrequency=%220.65%22 numOctaves=%223%22 stitchTiles=%22stitch%22/%3E%3C/filter%3E%3Crect width=%22100%25%22 height=%22100%25%22 filter=%22url(%23noiseFilter)%22/%3E%3C/svg%3E');"></div>
      
      <router-view v-slot="{ Component }">
        <component :is="Component" :key="$route.fullPath" @refresh="load" />
      </router-view>
    </div>

    <!-- Delete confirm -->
    <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/40 backdrop-blur-sm">
      <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl p-6 space-y-4 border border-gray-100 animate-fade-in-up">
        <h3 class="font-bold text-[16px] text-gray-900 tracking-tight">Delete "{{ deleteTarget.name }}"?</h3>
        <p class="text-[13px] text-gray-500">This cannot be undone. Any bills using this item will keep their data.</p>
        <div class="flex gap-2.5 mt-2">
          <button @click="deleteTarget = null" class="flex-1 px-4 py-2.5 bg-gray-100 hover:bg-gray-200 rounded-xl text-[13px] font-bold text-gray-700 transition-colors" :disabled="deleting">Cancel</button>
          <button @click="confirmDelete" :disabled="deleting" class="flex-1 px-4 py-2.5 bg-red-600 hover:bg-red-700 text-white shadow-md shadow-red-600/20 border border-red-600 rounded-xl text-[13px] font-bold transition-all disabled:opacity-60 flex justify-center items-center">
            {{ deleting ? 'Deleting…' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
