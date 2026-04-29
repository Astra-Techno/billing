<script setup>
import { ref } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useRoute, useRouter } from 'vue-router'

const auth   = useAuthStore()
const route  = useRoute()
const router = useRouter()
const showMore = ref(false)

const allNav = [
  { name: 'Home',          to: '/',             icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
  { name: 'Customers',     to: '/clients',      icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
  { name: 'Bills',         to: '/invoices',     icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
  { name: 'Quotations',    to: '/quotes',       icon: 'M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2' },
  { name: 'Expenses',      to: '/expenses',     icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z' },
  { name: 'Products',      to: '/products',     icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' },
  { name: 'Returns/Adj.',  to: '/credit-notes', icon: 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z' },
  { name: 'GST Filing',    to: '/gst-returns',  icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
  { name: 'Reports',       to: '/reports',      icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
  { name: 'Settings',      to: '/settings',     icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z' },
]

const isActive = (to) => to === '/' ? route.path === '/' : route.path.startsWith(to)

function goTo(to) { router.push(to); showMore.value = false }

async function logout() {
  try { await import('../../api').then(m => m.task('Auth', 'logout')) } catch {}
  auth.logout()
  router.push('/login')
  showMore.value = false
}
</script>

<template>
  <!-- ========== BOTTOM NAV (all screen sizes) ========== -->
  <nav class="fixed bottom-0 inset-x-0 z-40 bg-white border-t border-gray-100 shadow-lg safe-area-pb">
    <div class="max-w-2xl mx-auto lg:max-w-none lg:px-4 flex items-center">

      <RouterLink to="/" class="flex-1 flex flex-col items-center gap-0.5 py-2 transition-colors"
        :class="isActive('/') ? 'text-primary-600' : 'text-gray-400'">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
        </svg>
        <span class="text-[10px] font-semibold">Home</span>
      </RouterLink>

      <RouterLink to="/invoices" class="flex-1 flex flex-col items-center gap-0.5 py-2 transition-colors"
        :class="isActive('/invoices') ? 'text-primary-600' : 'text-gray-400'">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <span class="text-[10px] font-semibold">Bills</span>
      </RouterLink>

      <!-- Center FAB -->
      <div class="flex-1 flex flex-col items-center py-1">
        <RouterLink to="/invoices/new"
          class="w-14 h-14 rounded-full bg-gradient-to-br from-primary-500 to-primary-700 flex items-center justify-center shadow-lg shadow-primary-300 active:scale-95 transition-transform -mt-5">
          <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
          </svg>
        </RouterLink>
        <span class="text-[10px] font-semibold text-gray-400 mt-0.5">New Bill</span>
      </div>

      <RouterLink to="/quotes" class="flex-1 flex flex-col items-center gap-0.5 py-2 transition-colors"
        :class="isActive('/quotes') ? 'text-primary-600' : 'text-gray-400'">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"/>
        </svg>
        <span class="text-[10px] font-semibold">Quotes</span>
      </RouterLink>

      <RouterLink to="/expenses" class="flex-1 flex flex-col items-center gap-0.5 py-2 transition-colors"
        :class="isActive('/expenses') ? 'text-primary-600' : 'text-gray-400'">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
        </svg>
        <span class="text-[10px] font-semibold">Expenses</span>
      </RouterLink>

      <button @click="showMore = true" class="flex-1 flex flex-col items-center gap-0.5 py-2 text-gray-400">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/>
        </svg>
        <span class="text-[10px] font-semibold">More</span>
      </button>

    </div>
  </nav>

  <!-- ========== MORE BOTTOM SHEET ========== -->
  <Teleport to="body">
    <div v-if="showMore" class="fixed inset-0 z-50 flex flex-col justify-end">
      <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showMore = false" />
      <div class="relative bg-white rounded-t-3xl shadow-2xl safe-area-pb">
        <div class="flex justify-center pt-3 pb-1">
          <div class="w-10 h-1 rounded-full bg-gray-200"></div>
        </div>
        <div class="flex items-center gap-3 px-5 py-3 border-b border-gray-100">
          <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center shrink-0">
            <span class="text-white font-bold">{{ auth.user?.name?.charAt(0) }}</span>
          </div>
          <div>
            <p class="font-semibold text-gray-900">{{ auth.user?.name }}</p>
            <p class="text-xs text-gray-400">{{ auth.user?.email }}</p>
          </div>
        </div>
        <div class="grid grid-cols-4 gap-1 p-4">
          <button v-for="item in allNav" :key="item.name" @click="goTo(item.to)"
            class="flex flex-col items-center gap-1.5 p-3 rounded-2xl active:bg-gray-100 transition-colors"
            :class="isActive(item.to) ? 'bg-primary-50' : ''">
            <div class="w-12 h-12 rounded-2xl flex items-center justify-center"
              :class="isActive(item.to) ? 'bg-primary-600' : 'bg-gray-100'">
              <svg class="w-5 h-5" :class="isActive(item.to) ? 'text-white' : 'text-gray-600'"
                fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
              </svg>
            </div>
            <span class="text-[10px] font-semibold text-center leading-tight"
              :class="isActive(item.to) ? 'text-primary-700' : 'text-gray-600'">{{ item.name }}</span>
          </button>
        </div>
        <div class="px-4 pb-4">
          <button @click="logout"
            class="w-full flex items-center justify-center gap-2 py-3.5 rounded-2xl bg-red-50 text-red-500 font-semibold text-sm active:bg-red-100">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
            Sign Out
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
