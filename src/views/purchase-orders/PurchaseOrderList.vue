<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { list } from '../../api'
import HelpIcon from '../../components/HelpIcon.vue'
import { inr } from '../../utils/currency'
import { fmtDateShort } from '../../utils/date'

const router      = useRouter()
const orders      = ref([])
const loading     = ref(true)
const showFilters = ref(false)
const filter      = ref({ status: '', search: '' })
let timer = null

const tabs = [
  { label: 'All',      value: '' },
  { label: 'Draft',    value: 'draft' },
  { label: 'Sent',     value: 'sent' },
  { label: 'Received', value: 'received' },
]

const badgeClass  = s => ({ draft: 'badge-gray', sent: 'badge-blue', received: 'badge-green', cancelled: 'badge-red' }[s] || 'badge-gray')
const statusLabel = s => ({ draft: 'Draft', sent: 'Sent', received: 'Received', cancelled: 'Cancelled' }[s] || s)

const avatarColors = ['bg-blue-100 text-blue-700', 'bg-emerald-100 text-emerald-700', 'bg-purple-100 text-purple-700', 'bg-amber-100 text-amber-700', 'bg-pink-100 text-pink-700']
const avatarColor  = name => avatarColors[(name?.charCodeAt(0) || 0) % avatarColors.length]

async function load() {
  loading.value = true
  try {
    const p = { sort_by: 'po.created_at', sort_order: 'desc' }
    if (filter.value.status) p['filter.status'] = filter.value.status
    if (filter.value.search) p['filter.search'] = `%${filter.value.search}%`
    const { data } = await list('PurchaseOrder', p)
    orders.value = data.data || []
  } catch {}
  loading.value = false
}

function onSearch() { clearTimeout(timer); timer = setTimeout(load, 350) }
onMounted(load)
</script>

<template>
  <div class="flex flex-col lg:flex-row h-full min-h-0 w-full overflow-hidden">

    <!-- Left Pane -->
    <div id="c3-left-panel" :class="{ 'hidden lg:flex': $route.name !== 'PurchaseOrders', 'w-full lg:w-[340px] border-r border-gray-200/60 flex flex-col shrink-0 bg-[#FAFAFA] transition-all duration-300 relative z-30 h-full': true }">

      <!-- Sticky Header -->
      <div class="px-5 py-4 border-b border-gray-200/60 bg-white/60 backdrop-blur-md sticky top-0 z-10">
        <div class="flex justify-between items-center mb-4">
          <h2 class="font-bold text-gray-900 text-sm tracking-tight flex items-center gap-2">Purchase Orders <HelpIcon section="purchase-orders" class="w-3.5 h-3.5" /></h2>
          <div class="flex gap-2">
            <button @click="showFilters = !showFilters"
              class="w-7 h-7 bg-white border border-gray-200/80 shadow-sm hover:shadow hover:border-gray-300 rounded-lg flex items-center justify-center transition-all"
              :class="showFilters ? 'text-indigo-600 border-indigo-200 bg-indigo-50' : 'text-gray-600'">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </button>
            <RouterLink to="/purchase-orders/new"
              class="w-7 h-7 bg-white border border-gray-200/80 shadow-sm hover:shadow hover:border-gray-300 rounded-lg flex items-center justify-center text-gray-600 transition-all">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            </RouterLink>
          </div>
        </div>

        <!-- Search -->
        <div v-show="showFilters" class="mb-3 animate-fade-in-up">
          <input v-model="filter.search" @input="onSearch" type="text"
            class="w-full bg-white border border-gray-200 shadow-sm text-gray-900 text-xs font-semibold rounded-lg focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 block px-3 py-2 transition-all"
            placeholder="Search by PO no. or supplier…" />
        </div>

        <!-- Status Tabs -->
        <div class="flex gap-1 bg-gray-100/80 p-1 rounded-[10px] ring-1 ring-inset ring-gray-200/50 overflow-x-auto hide-scrollbar">
          <button v-for="t in tabs" :key="t.value"
            @click="filter.status = t.value; load()"
            class="flex-1 text-[11px] font-semibold rounded-md py-1.5 transition-all whitespace-nowrap px-2"
            :class="filter.status === t.value ? 'bg-white shadow-sm text-gray-900 font-bold' : 'text-gray-500 hover:text-gray-700'">
            {{ t.label }}
          </button>
        </div>
      </div>

      <!-- Scrollable List -->
      <div class="flex-1 overflow-y-auto px-3 py-3 space-y-1.5 custom-scrollbar min-h-0">

        <div v-if="loading" class="space-y-1.5">
          <div v-for="i in 5" :key="i" class="p-4 rounded-xl border border-gray-100 bg-white/40 animate-pulse flex justify-between items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-gray-200 shrink-0"></div>
            <div class="space-y-2 flex-1"><div class="h-3.5 bg-gray-200 rounded w-24"></div><div class="h-2.5 bg-gray-100 rounded w-16"></div></div>
            <div class="h-3.5 bg-gray-200 rounded w-16 shrink-0"></div>
          </div>
        </div>

        <div v-else-if="!orders.length" class="p-8 text-center">
          <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
          </div>
          <p class="font-bold text-gray-900 text-[13px]">No purchase orders yet</p>
          <p class="text-[11px] text-gray-500 mt-1">Raise POs to your suppliers</p>
          <RouterLink to="/purchase-orders/new" class="btn bg-primary-600 text-white hover:bg-primary-700 shadow-soft-blue rounded-full px-5 py-2 mt-4 inline-flex items-center gap-2 font-bold text-xs">New PO</RouterLink>
        </div>

        <div v-else>
          <div v-for="(po, idx) in orders" :key="po.id"
            class="p-4 rounded-xl border cursor-pointer transition-all group relative overflow-hidden list-item-1"
            :style="{ animationDelay: (idx * 0.05) + 's' }"
            :class="[$route.params.id == po.id ? 'bg-white border-gray-200 shadow-[0_2px_8px_rgba(0,0,0,0.03)]' : 'border-transparent hover:border-gray-200/60 hover:bg-white hover:shadow-[0_2px_8px_rgba(0,0,0,0.02)]']"
            @click="router.push('/purchase-orders/' + po.id)">
            <div v-if="$route.params.id == po.id" class="absolute left-0 top-0 bottom-0 w-[3px] bg-gray-900 rounded-l-xl"></div>
            <div class="flex gap-3 items-center">
              <div class="w-9 h-9 rounded-lg flex items-center justify-center font-bold text-xs border shrink-0 group-hover:scale-105 transition-transform"
                :class="avatarColor(po.supplier_name)">
                {{ po.supplier_name?.charAt(0)?.toUpperCase() || 'S' }}
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-1 mb-0.5">
                  <p class="font-bold text-gray-900 text-[13px] truncate">{{ po.supplier_name || 'Unknown Supplier' }}</p>
                  <p class="font-bold text-gray-900 text-[13px] shrink-0 tabular-nums">{{ inr(po.total) }}</p>
                </div>
                <div class="flex items-center justify-between">
                  <p class="text-[11px] text-gray-500">{{ po.number }} · {{ fmtDateShort(po.order_date) }}</p>
                  <span :class="badgeClass(po.status)" class="text-[10px]">{{ statusLabel(po.status) }}</span>
                </div>
              </div>
            </div>
          </div>

          <div class="pt-3 pb-1 text-center">
            <span class="text-[10px] text-gray-400 font-semibold uppercase tracking-widest">{{ orders.length }} order{{ orders.length !== 1 ? 's' : '' }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Pane -->
    <div v-if="$route.name !== 'PurchaseOrders'" id="c3-right-view" class="flex-1 bg-[#F4F4F5] overflow-y-auto flex flex-col relative z-20 shadow-[-10px_0_20px_rgba(0,0,0,0.02)] custom-scrollbar">
      <router-view v-slot="{ Component }">
        <component :is="Component" :key="$route.fullPath" @refresh="load" />
      </router-view>
    </div>

  </div>
</template>
