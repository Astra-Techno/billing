<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { inr } from '../../utils/currency'
import { fmtDateShort } from '../../utils/date'
import PurchaseReturnForm from './PurchaseReturnForm.vue'
import { usePagedList } from '../../composables/usePagedList'
import { useRole } from '../../composables/useRole'

const { can } = useRole()
const router      = useRouter()
const route       = useRoute()
const showFilters = ref(false)
const filter      = ref({ status: '' })

const tabs = [
  { label: 'All',      value: '' },
  { label: 'Draft',    value: 'draft' },
  { label: 'Issued',   value: 'issued' },
  { label: 'Adjusted', value: 'adjusted' },
]

const badgeClass  = s => ({ draft: 'badge-gray', issued: 'badge-blue', adjusted: 'badge-green' }[s] || 'badge-gray')
const statusLabel = s => ({ draft: 'Draft', issued: 'Issued', adjusted: 'Adjusted' }[s] || s)
const reasonLabel = r => ({ defective: 'Defective', expired: 'Expired', excess: 'Excess', wrong_item: 'Wrong Item', other: 'Other' }[r] || r)

const avatarColors = ['bg-amber-100 text-amber-700', 'bg-emerald-100 text-emerald-700', 'bg-purple-100 text-purple-700', 'bg-rose-100 text-rose-700', 'bg-cyan-100 text-cyan-700']
const avatarColor  = name => avatarColors[(name?.charCodeAt(0) || 0) % avatarColors.length]

const { items: returns, loading, loadingMore, total, hasMore, search, onSearch, loadMore, reload } = usePagedList('PurchaseReturn', {
  limit: 50,
  params: () => {
    const p = { sort_by: 'pr.created_at', sort_order: 'desc' }
    if (filter.value.status) p['filter.status'] = filter.value.status
    return p
  },
  listRouteName: 'PurchaseReturns',
  scrollContainer: '#pr-scroll',
})

async function load() { await reload() }
</script>

<template>
  <div class="flex flex-col lg:flex-row h-full min-h-0 w-full overflow-hidden">

    <!-- Left Pane -->
    <div id="c3-left-panel" :class="{ 'hidden lg:flex': $route.name !== 'PurchaseReturns', 'split-pane-left transition-all duration-300 relative z-30 h-full': true }">

      <div class="px-5 py-4 border-b border-gray-200/60 bg-white/60 backdrop-blur-md sticky top-0 z-10">
        <div class="flex justify-between items-center mb-4">
          <h2 class="font-bold text-gray-900 text-sm tracking-tight">Purchase Returns</h2>
          <button @click="showFilters = !showFilters"
            class="w-7 h-7 bg-white border border-gray-200/80 shadow-sm hover:shadow hover:border-gray-300 rounded-lg flex items-center justify-center transition-all"
            :class="showFilters ? 'text-primary-600 border-primary-200 bg-primary-50' : 'text-gray-600'">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          </button>
        </div>

        <div v-show="showFilters" class="mb-3 animate-fade-in-up">
          <input :value="search" @input="onSearch($event.target.value)" type="text"
            class="w-full bg-white border border-gray-200 shadow-sm text-gray-900 text-xs font-semibold rounded-lg focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 block px-3 py-2 transition-all"
            placeholder="Search return no., bill no., supplier…" />
        </div>

        <div class="flex gap-1 bg-gray-100/80 p-1 rounded-[10px] ring-1 ring-inset ring-gray-200/50 overflow-x-auto hide-scrollbar">
          <button v-for="t in tabs" :key="t.value"
            @click="filter.status = t.value; reload()"
            class="flex-1 text-[11px] font-semibold rounded-md py-1.5 transition-all whitespace-nowrap px-2"
            :class="filter.status === t.value ? 'bg-white shadow-sm text-gray-900 font-bold' : 'text-gray-500 hover:text-gray-700'">
            {{ t.label }}
          </button>
        </div>
      </div>

      <div id="pr-scroll" class="flex-1 overflow-y-auto px-3 py-3 space-y-1.5 custom-scrollbar min-h-0">

        <div v-if="loading" class="space-y-1.5">
          <div v-for="i in 5" :key="i" class="p-4 rounded-xl border border-gray-100 bg-white/40 animate-pulse flex justify-between items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-gray-200 shrink-0"></div>
            <div class="space-y-2 flex-1"><div class="h-3.5 bg-gray-200 rounded w-24"></div><div class="h-2.5 bg-gray-100 rounded w-16"></div></div>
            <div class="h-3.5 bg-gray-200 rounded w-16 shrink-0"></div>
          </div>
        </div>

        <div v-else-if="!returns.length" class="p-8 text-center">
          <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
          </div>
          <p class="font-bold text-gray-900 text-[13px]">No purchase returns yet</p>
          <p class="text-[11px] text-gray-500 mt-1">Return items to suppliers from purchase bills</p>
        </div>

        <div v-else>
          <div v-for="(pr, idx) in returns" :key="pr.id"
            class="p-4 rounded-xl border cursor-pointer transition-all group relative overflow-hidden list-item-1"
            :style="{ animationDelay: (idx * 0.05) + 's' }"
            :class="[$route.params.id == pr.id ? 'bg-white border-gray-200 shadow-[0_2px_8px_rgba(0,0,0,0.03)]' : 'border-transparent hover:border-gray-200/60 hover:bg-white hover:shadow-[0_2px_8px_rgba(0,0,0,0.02)]']"
            @click="router.push('/purchase-returns/' + pr.id)">
            <div v-if="$route.params.id == pr.id" class="absolute left-0 top-0 bottom-0 w-[3px] bg-gray-900 rounded-l-xl"></div>
            <div class="flex gap-3 items-center">
              <div class="w-9 h-9 rounded-lg flex items-center justify-center font-bold text-xs border shrink-0 group-hover:scale-105 transition-transform"
                :class="avatarColor(pr.supplier_name)">
                {{ pr.supplier_name?.charAt(0)?.toUpperCase() || 'S' }}
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-1 mb-0.5">
                  <p class="font-bold text-gray-900 text-[13px] truncate">{{ pr.supplier_name || 'Unknown' }}</p>
                  <p class="font-bold text-gray-900 text-[13px] shrink-0 tabular-nums">{{ inr(pr.total) }}</p>
                </div>
                <div class="flex items-center justify-between">
                  <p class="text-[11px] text-gray-500">{{ pr.number }} · {{ fmtDateShort(pr.return_date) }}</p>
                  <span :class="badgeClass(pr.status)" class="text-[10px]">{{ statusLabel(pr.status) }}</span>
                </div>
                <p class="text-[10px] text-gray-400 mt-0.5">{{ reasonLabel(pr.reason) }} · Bill {{ pr.pi_number }}</p>
              </div>
            </div>
          </div>

          <div class="py-3 text-center">
            <button v-if="hasMore" @click="loadMore" :disabled="loadingMore"
              class="px-4 py-2 text-xs font-bold text-primary-600 hover:text-primary-700 bg-primary-50 hover:bg-primary-100 rounded-lg transition-colors disabled:opacity-50">
              {{ loadingMore ? 'Loading…' : `Load more (${returns.length} of ${total})` }}
            </button>
            <p v-else class="text-[11px] text-gray-400 font-medium">Showing all {{ total }} returns</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Pane -->
    <div id="c3-right-view" class="split-pane-right relative z-20 hidden lg:flex" :class="$route.name !== 'PurchaseReturns' ? '!flex' : ''">
      <router-view v-slot="{ Component }">
        <component v-if="Component" :is="Component" :key="$route.fullPath" @refresh="load" />
        <PurchaseReturnForm v-else :key="'default-new'" @refresh="load" />
      </router-view>
    </div>

  </div>
</template>
