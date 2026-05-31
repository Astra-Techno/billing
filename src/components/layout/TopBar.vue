<script setup>
import { ref, watch, onMounted } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useBusinessStore } from '../../stores/business'
import { useRouter } from 'vue-router'
import { list } from '../../api'
import { inrCompact } from '../../utils/currency'
import { statusBadge, statusLabel } from '../../utils/invoice'
import { useNotifications } from '../../composables/useNotifications'

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

// ── Notifications ───────────────────────────────────────────────────────────
const { notifications, count: notifCount, loading: notifLoading, load: loadNotifs } = useNotifications()
const notifOpen = ref(false)

function toggleNotif() {
  notifOpen.value = !notifOpen.value
  if (notifOpen.value) loadNotifs()
}

function closeNotif() { notifOpen.value = false }

function goNotif(link) {
  closeNotif()
  router.push(link)
}

onMounted(() => loadNotifs())

const avatarColors = [
  'bg-blue-100 text-blue-700', 'bg-emerald-100 text-emerald-700',
  'bg-purple-100 text-purple-700', 'bg-amber-100 text-amber-700',
  'bg-pink-100 text-pink-700', 'bg-teal-100 text-teal-700',
]
const avatarColor = name => avatarColors[(name?.charCodeAt(0) || 0) % avatarColors.length]
</script>

<template>
  <header class="bg-white border-b border-google-divider sticky top-0 z-30 w-full">
    <div class="w-full px-4 py-2.5 flex items-center gap-3">

      <RouterLink to="/settings" class="shrink-0" title="Profile">
        <div class="w-10 h-10 rounded-full overflow-hidden bg-primary-100 flex items-center justify-center ring-2 ring-white shadow-soft">
          <img v-if="bizStore.logo" :src="bizStore.logo" class="w-full h-full object-cover" alt="" />
          <span v-else-if="auth.user?.name" class="text-primary-700 text-sm font-medium">{{ auth.user.name.charAt(0).toUpperCase() }}</span>
          <span v-else class="text-primary-700 text-sm font-medium">B</span>
        </div>
      </RouterLink>

      <div class="flex-1 relative min-w-0">

        <!-- Trigger (closed state) -->
        <div v-if="!open"
          class="flex items-center gap-2.5 text-sm text-google-muted bg-surface-muted rounded-full px-4 py-2.5 cursor-text hover:bg-google-divider/50 transition-colors select-none w-full"
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
            class="w-full bg-white border border-primary-200 rounded-full pl-10 pr-10 py-2.5 text-sm text-google-text outline-none ring-2 ring-primary-50 placeholder:text-google-muted shadow-soft"
          />
          <button @click="close" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>

          <!-- Results dropdown -->
          <div class="absolute left-0 right-0 top-full mt-2 bg-white border border-google-divider rounded-gpay-xl shadow-gpay-lg overflow-hidden z-50">

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

      <!-- Bell icon + notification dropdown -->
      <div class="relative shrink-0">
        <button @click="toggleNotif"
          class="relative p-2 rounded-full transition-colors"
          :class="notifOpen ? 'bg-primary-50 text-primary-600' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
          </svg>
          <span v-if="notifCount > 0"
            class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center px-1 leading-none">
            {{ notifCount > 9 ? '9+' : notifCount }}
          </span>
        </button>

        <!-- Dropdown -->
        <div v-if="notifOpen"
          class="absolute right-0 top-full mt-2 w-80 bg-white border border-gray-200 rounded-2xl shadow-xl overflow-hidden z-50">

          <!-- Header -->
          <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <p class="font-bold text-gray-900 text-sm">Notifications</p>
            <span v-if="notifCount > 0" class="text-xs text-gray-400">{{ notifCount }} alert{{ notifCount !== 1 ? 's' : '' }}</span>
          </div>

          <!-- Loading -->
          <div v-if="notifLoading" class="flex items-center justify-center py-8">
            <svg class="w-5 h-5 animate-spin text-gray-300" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
          </div>

          <!-- Empty -->
          <div v-else-if="notifications.length === 0" class="px-4 py-8 text-center">
            <svg class="w-8 h-8 text-gray-200 mx-auto mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm text-gray-400">All clear — no alerts</p>
          </div>

          <!-- Items -->
          <div v-else class="max-h-80 overflow-y-auto divide-y divide-gray-50">
            <div v-for="n in notifications" :key="n.id"
              class="flex items-start gap-3 px-4 py-3 hover:bg-gray-50 cursor-pointer transition-colors"
              @click="goNotif(n.link)">
              <!-- Icon -->
              <div class="mt-0.5 w-7 h-7 rounded-full flex items-center justify-center shrink-0"
                :class="n.type === 'overdue' ? 'bg-red-100' : 'bg-amber-100'">
                <svg v-if="n.type === 'overdue'" class="w-3.5 h-3.5 text-red-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
                <svg v-else class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
              </div>
              <!-- Text -->
              <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-800 truncate">{{ n.title }}</p>
                <p class="text-xs text-gray-500 truncate">{{ n.body }}</p>
              </div>
              <svg class="w-3.5 h-3.5 text-gray-300 shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </div>
          </div>

          <!-- Footer -->
          <div v-if="notifications.length > 0" class="border-t border-gray-100 px-4 py-2.5">
            <button @click="goNotif('/invoices?status=overdue')"
              class="text-xs font-semibold text-primary-600 hover:text-primary-700 transition-colors">
              View all overdue bills →
            </button>
          </div>

        </div>
      </div>

    </div>

    <!-- Click-outside overlay for search -->
    <div v-if="open" class="fixed inset-0 z-40" @click="close" />
    <!-- Click-outside overlay for notifications -->
    <div v-if="notifOpen" class="fixed inset-0 z-40" @click="closeNotif" />
  </header>
</template>
