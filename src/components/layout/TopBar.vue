<script setup>
import { ref, watch } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useBusinessStore } from '../../stores/business'
import { useRouter } from 'vue-router'
import { list } from '../../api'
import { inrCompact } from '../../utils/currency'
import { statusBadge, statusLabel } from '../../utils/invoice'

const auth    = useAuthStore()
const bizStore = useBusinessStore()
const router  = useRouter()

const query    = ref('')
const open     = ref(false)
const loading  = ref(false)
const bills    = ref([])
const customers = ref([])
const inputRef  = ref(null)

let timer = null

watch(query, val => {
  clearTimeout(timer)
  if (!val.trim()) { bills.value = []; customers.value = []; loading.value = false; return }
  loading.value = true
  timer = setTimeout(() => search(val.trim()), 300)
})

async function search(q) {
  try {
    const [bRes, cRes] = await Promise.all([
      list('Invoice', { 'filter.search': `%${q}%`, limit: 5, sort_by: 'i.created_at', sort_order: 'desc' }),
      list('Client',  { 'filter.search': `%${q}%`, limit: 5 }),
    ])
    bills.value     = bRes.data?.data || []
    customers.value = cRes.data?.data || []
  } catch {}
  loading.value = false
}

function openSearch() {
  open.value = true
  setTimeout(() => inputRef.value?.focus(), 50)
}

function close() {
  open.value = false
  query.value = ''
  bills.value = []
  customers.value = []
}

function go(path) {
  close()
  router.push(path)
}

function onKeydown(e) {
  if (e.key === 'Escape') close()
}

const hasResults = () => bills.value.length > 0 || customers.value.length > 0

const avatarColors = [
  'bg-blue-100 text-blue-700', 'bg-emerald-100 text-emerald-700',
  'bg-purple-100 text-purple-700', 'bg-amber-100 text-amber-700',
  'bg-pink-100 text-pink-700', 'bg-teal-100 text-teal-700',
]
const avatarColor = name => avatarColors[(name?.charCodeAt(0) || 0) % avatarColors.length]
</script>

<template>
  <header class="bg-white/80 backdrop-blur-md border-b border-gray-100 sticky top-0 z-30 w-full">
    <div class="w-full max-w-2xl mx-auto lg:max-w-none px-4 lg:px-8 py-3 flex items-center justify-between gap-3">

      <!-- Logo -->
      <RouterLink to="/" class="flex items-center gap-2 shrink-0">
        <div class="w-9 h-9 rounded-full bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center shadow-soft-blue">
          <span class="text-white font-bold text-sm">B</span>
        </div>
        <span class="font-bold text-gray-900 tracking-tight text-[17px]">BillBook</span>
      </RouterLink>

      <!-- Navigation (Desktop Only) -->
      <nav class="hidden lg:flex items-center gap-5 ml-4 mr-2 shrink-0">
        <RouterLink to="/" class="text-sm font-semibold text-gray-500 hover:text-gray-900 transition-colors" active-class="!text-primary-600">Home</RouterLink>
        <RouterLink to="/invoices" class="text-sm font-semibold text-gray-500 hover:text-gray-900 transition-colors" active-class="!text-primary-600">Bills</RouterLink>
        <RouterLink to="/quotes" class="text-sm font-semibold text-gray-500 hover:text-gray-900 transition-colors" active-class="!text-primary-600">Quotes</RouterLink>
        <RouterLink to="/expenses" class="text-sm font-semibold text-gray-500 hover:text-gray-900 transition-colors" active-class="!text-primary-600">Expenses</RouterLink>
        <RouterLink to="/more" class="text-sm font-semibold text-gray-500 hover:text-gray-900 transition-colors" active-class="!text-primary-600">More</RouterLink>
        <RouterLink to="/invoices/new" class="ml-2 px-4 py-1.5 bg-primary-600 text-white text-sm font-semibold rounded-full hover:bg-primary-700 transition-all shadow-md hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 active:shadow-sm">
          New Bill
        </RouterLink>
      </nav>

      <!-- Search -->
      <div class="flex-1 relative min-w-0 flex justify-end">

        <!-- Trigger (closed state) -->
        <div v-if="!open"
          class="flex items-center gap-2 text-sm text-gray-500 bg-gray-100 border border-transparent rounded-full px-4 py-2.5 cursor-text hover:bg-gray-200 transition-colors select-none w-full max-w-sm"
          @click="openSearch">
          <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          <span class="truncate">Search bills, customers…</span>
        </div>

        <!-- Active search input -->
        <div v-else class="relative">
          <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 text-primary-500 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          <input
            ref="inputRef"
            v-model="query"
            @keydown="onKeydown"
            type="text"
            placeholder="Search bills, customers…"
            class="w-full lg:min-w-[300px] bg-white border border-primary-200 rounded-full pl-10 pr-10 py-2.5 text-sm text-gray-800 outline-none ring-4 ring-primary-50 placeholder:text-gray-400 shadow-soft"
          />
          <button @click="close" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>

          <!-- Results dropdown -->
          <div class="absolute left-0 right-0 top-full mt-2 bg-white border border-gray-200 rounded-2xl shadow-xl overflow-hidden z-50">

            <!-- Loading -->
            <div v-if="loading" class="px-4 py-5 text-center text-sm text-gray-400">
              <svg class="w-4 h-4 animate-spin mx-auto mb-1" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              Searching…
            </div>

            <!-- Empty query -->
            <div v-else-if="!query.trim()" class="px-4 py-4 text-sm text-gray-400 text-center">
              Type to search bills or customers
            </div>

            <!-- No results -->
            <div v-else-if="!hasResults()" class="px-4 py-5 text-center text-sm text-gray-400">
              No results for "<span class="font-medium text-gray-600">{{ query }}</span>"
            </div>

            <template v-else-if="hasResults()">

              <!-- Bills section -->
              <div v-if="bills.length">
                <div class="px-4 pt-3 pb-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Bills</div>
                <div v-for="inv in bills" :key="'inv-' + inv.id"
                  class="flex items-center gap-3 px-4 py-2.5 hover:bg-blue-50/60 cursor-pointer transition-colors group"
                  @click="go('/invoices/' + inv.id)">
                  <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 font-bold text-xs" :class="avatarColor(inv.client_name)">
                    {{ inv.client_name?.charAt(0)?.toUpperCase() }}
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-800 truncate group-hover:text-primary-700">{{ inv.client_name }}</p>
                    <p class="text-xs text-gray-400 truncate">{{ inv.number }}</p>
                  </div>
                  <div class="text-right shrink-0">
                    <p class="text-sm font-bold text-gray-900">{{ inrCompact(inv.total) }}</p>
                    <span :class="statusBadge(inv.status)">{{ statusLabel(inv.status) }}</span>
                  </div>
                </div>
              </div>

              <!-- Customers section -->
              <div v-if="customers.length" :class="bills.length ? 'border-t border-gray-100' : ''">
                <div class="px-4 pt-3 pb-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Customers</div>
                <div v-for="c in customers" :key="'c-' + c.id"
                  class="flex items-center gap-3 px-4 py-2.5 hover:bg-blue-50/60 cursor-pointer transition-colors group"
                  @click="go('/clients/' + c.id)">
                  <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 font-bold text-xs" :class="avatarColor(c.name)">
                    {{ c.name?.charAt(0)?.toUpperCase() }}
                  </div>
                  <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-800 truncate group-hover:text-primary-700">{{ c.name }}</p>
                    <p class="text-xs text-gray-400 truncate">{{ c.mobile || c.email || 'Customer' }}</p>
                  </div>
                  <svg class="w-4 h-4 text-gray-300 shrink-0 group-hover:text-primary-400 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                  </svg>
                </div>
              </div>

              <!-- Footer -->
              <div class="border-t border-gray-100 px-4 py-2.5 flex items-center justify-between">
                <span class="text-xs text-gray-400">{{ bills.length + customers.length }} result{{ bills.length + customers.length !== 1 ? 's' : '' }}</span>
                <span class="text-xs text-gray-400">Press <kbd class="px-1.5 py-0.5 bg-gray-100 rounded text-gray-500 font-mono text-[10px]">Esc</kbd> to close</span>
              </div>

            </template>

          </div>
        </div>
      </div>

      <!-- Avatar / logo → settings -->
      <RouterLink to="/settings" class="shrink-0" title="Settings">
        <div class="w-9 h-9 rounded-full overflow-hidden bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center shadow-sm ring-2 ring-white hover:ring-primary-200 transition-all">
          <img v-if="bizStore.logo" :src="bizStore.logo" class="w-full h-full object-cover" alt="logo" />
          <span v-else-if="auth.user?.name" class="text-white text-sm font-bold">{{ auth.user.name.charAt(0).toUpperCase() }}</span>
          <svg v-else class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
          </svg>
        </div>
      </RouterLink>

    </div>

    <!-- Click-outside overlay -->
    <div v-if="open" class="fixed inset-0 z-40" @click="close" />
  </header>
</template>
