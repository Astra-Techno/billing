<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useBusinessStore } from '../../stores/business'
import { useAuthStore } from '../../stores/auth'
import { APP_NAME } from '../../config/brand'
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useNotifications } from '../../composables/useNotifications'

const route = useRoute()
const router = useRouter()
const businessStore = useBusinessStore()
const authStore = useAuthStore()

const { notifications, count: notifCount, loading: notifLoading, load: loadNotifs } = useNotifications()
const notifOpen  = ref(false)
const userOpen   = ref(false)

function toggleNotif() {
  notifOpen.value = !notifOpen.value
  if (notifOpen.value) loadNotifs()
}

function closeNotif() { notifOpen.value = false }
function closeUser()  { userOpen.value  = false }

async function logout() {
  try { await import('../../api').then(m => m.task('Auth', 'logout')) } catch {}
  authStore.logout()
  router.push('/login')
}

function goNotif(link) {
  closeNotif()
  router.push(link)
}

onMounted(() => loadNotifs())

const breadcrumbs = computed(() => {
  const paths = []
  if (route.name === 'Dashboard') {
    paths.push({ label: 'Dashboard', to: '/' })
  } else if (route.name?.startsWith('Invoice')) {
    paths.push({ label: 'Invoices', to: '/invoices' })
    if (route.name === 'InvoiceNew') paths.push({ label: 'New' })
    else if (route.name === 'InvoiceEdit') paths.push({ label: 'Edit' })
    else if (route.name === 'InvoiceView') paths.push({ label: route.params.id })
    else paths.push({ label: 'All' })
  } else if (route.name?.startsWith('Client')) {
    paths.push({ label: 'Clients', to: '/clients' })
    if (route.name === 'ClientNew') paths.push({ label: 'New' })
    else if (route.name === 'ClientEdit') paths.push({ label: 'Edit' })
    else if (route.name === 'ClientView') paths.push({ label: 'View' })
    else paths.push({ label: 'All' })
  } else if (route.name?.startsWith('Expense')) {
    paths.push({ label: 'Expenses', to: '/expenses' })
    if (route.name === 'ExpenseNew') paths.push({ label: 'New' })
    else if (route.name === 'ExpenseEdit') paths.push({ label: 'Edit' })
    else paths.push({ label: 'All' })
  } else if (route.name?.startsWith('Quote')) {
    paths.push({ label: 'Quotes', to: '/quotes' })
    if (route.name === 'QuoteNew') paths.push({ label: 'New' })
    else if (route.name === 'QuoteEdit') paths.push({ label: 'Edit' })
    else if (route.name === 'QuoteView') paths.push({ label: 'View' })
    else paths.push({ label: 'All' })
  } else {
    paths.push({ label: route.name })
  }
  return paths
})

const userInitials = computed(() => {
  const name = authStore.user?.name || 'G'
  return name.charAt(0).toUpperCase()
})
</script>

<template>
  <header class="hidden lg:flex h-[60px] border-b border-white/60 glass items-center justify-between px-6 shrink-0 z-50 shadow-soft">
      <div class="flex items-center gap-5">
          <div class="flex items-center gap-2.5 font-bold text-gray-900 tracking-tight cursor-pointer select-none" @click="$router.push('/')">
              <div class="w-8 h-8 rounded-full bg-primary-600 shadow-gpay flex items-center justify-center text-white text-xs font-medium">
                {{ businessStore.business?.name?.charAt(0)?.toUpperCase() || 'B' }}
              </div>
              <span class="text-[14px] font-semibold text-gray-800">{{ businessStore.business?.name || APP_NAME }}</span>
          </div>
          <div class="h-4 w-px bg-gray-200"></div>
          <div class="flex items-center text-[13px] text-gray-500 gap-1.5 font-medium">
              <template v-for="(crumb, i) in breadcrumbs" :key="i">
                <span v-if="i > 0">
                  <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </span>
                <RouterLink v-if="crumb.to" :to="crumb.to" class="hover:text-gray-900 cursor-pointer transition-colors" :class="{ 'font-semibold text-gray-900': i === breadcrumbs.length - 1 }">{{ crumb.label }}</RouterLink>
                <span v-else class="text-gray-900 font-semibold">{{ crumb.label }}</span>
              </template>
          </div>
      </div>
      <div class="flex items-center gap-4">
          <!-- Dark mode toggle -->
          <button @click="businessStore.toggleDarkMode()"
            class="p-2 rounded-full transition-colors text-gray-500 hover:text-gray-700 hover:bg-gray-100"
            :title="businessStore.darkMode ? 'Switch to light mode' : 'Switch to dark mode'">
            <!-- Sun icon (shown in dark mode) -->
            <svg v-if="businessStore.darkMode" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
            <!-- Moon icon (shown in light mode) -->
            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
            </svg>
          </button>

          <button class="flex items-center gap-2 text-[12px] bg-gray-50 hover:bg-gray-100 px-3 py-1.5 rounded-lg transition-colors border border-gray-200/80 text-gray-400 hover:text-gray-600 font-medium">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
              <span>Search</span>
              <div class="flex gap-0.5 ml-1"><kbd class="font-sans text-[10px] bg-white border border-gray-200 rounded px-1 py-0.5 text-gray-400">⌘</kbd><kbd class="font-sans text-[10px] bg-white border border-gray-200 rounded px-1 py-0.5 text-gray-400">K</kbd></div>
          </button>
          
          <div class="relative shrink-0 flex items-center">
            <button @click="toggleNotif"
              class="relative p-2 rounded-full transition-colors"
              :class="notifOpen ? 'bg-primary-50 text-primary-600' : 'text-gray-500 hover:text-gray-700 hover:bg-gray-100'">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
              </svg>
              <span v-if="notifCount > 0"
                class="absolute top-1 right-1 min-w-[16px] h-[16px] bg-red-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center px-1 leading-none">
                {{ notifCount > 9 ? '9+' : notifCount }}
              </span>
            </button>

            <!-- Dropdown -->
            <div v-if="notifOpen"
              class="absolute right-0 top-full mt-3 w-80 bg-white border border-gray-200 rounded-2xl shadow-xl overflow-hidden z-50">
              
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

          <!-- User avatar + dropdown -->
          <div class="relative ml-1 shrink-0">
            <button @click="userOpen = !userOpen"
              class="w-8 h-8 rounded-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center text-white text-xs font-bold ring-2 ring-white shadow hover:scale-105 transition-transform">
              {{ userInitials }}
            </button>
            <div v-if="userOpen"
              class="absolute right-0 top-full mt-2 w-48 bg-white border border-gray-200 rounded-2xl shadow-xl overflow-hidden z-50 py-1">
              <div class="px-4 py-2.5 border-b border-gray-100">
                <p class="text-[13px] font-semibold text-gray-900 truncate">{{ authStore.user?.name || 'Account' }}</p>
                <p class="text-[11px] text-gray-400 truncate">{{ authStore.user?.email || '' }}</p>
              </div>
              <button @click="closeUser(); $router.push('/settings')"
                class="w-full flex items-center gap-2.5 px-4 py-2.5 text-[13px] font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Settings
              </button>
              <button @click="logout"
                class="w-full flex items-center gap-2.5 px-4 py-2.5 text-[13px] font-medium text-red-600 hover:bg-red-50 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                Sign Out
              </button>
            </div>
          </div>
      </div>
  </header>

  <!-- Click-outside overlays -->
  <div v-if="notifOpen" class="fixed inset-0 z-40" @click="closeNotif" />
  <div v-if="userOpen"  class="fixed inset-0 z-40" @click="closeUser" />
</template>
