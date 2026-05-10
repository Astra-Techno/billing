<script setup>
import { useRoute } from 'vue-router'

const route = useRoute()
const isActive = (to) => to === '/' ? route.path === '/' : route.path.startsWith(to)

const menus = [
  { path: '/', icon: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z', title: 'Dashboard' },
  { path: '/invoices', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', title: 'Invoices' },
  { path: '/quotes', icon: 'M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2', title: 'Quotes' },
  { path: '/delivery-challans', icon: 'M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z', title: 'Delivery Challans' },
  { path: '/purchase-orders', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01', title: 'Purchase Orders' },
  { path: '/credit-notes', icon: 'M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z', title: 'Credit Notes' },
  { path: '/clients', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', title: 'Clients' },
  { path: '/products', icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', title: 'Products' },
  { path: '/expenses', icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z', title: 'Expenses' },
  { path: '/gst-returns', icon: 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z', title: 'GST Returns' },
  { path: '/reports', icon: 'M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z', title: 'Reports' }
]
</script>

<template>
  <div class="hidden lg:flex w-[60px] bg-[#FAFAFA] border-r border-gray-200/60 flex-col items-center py-4 gap-2.5 shrink-0 z-40 relative">
      <div class="absolute inset-0 bg-gradient-to-b from-white to-transparent opacity-50 pointer-events-none"></div>
      
      <RouterLink v-for="menu in menus" :key="menu.path" :to="menu.path" :title="menu.title" class="relative group">
        <div v-if="isActive(menu.path)" class="absolute -left-3 top-1/2 -translate-y-1/2 w-1 h-6 bg-gray-900 rounded-r-full z-50"></div>
        <div class="nav-icon w-10 h-10 rounded-[12px] flex items-center justify-center transition-all z-10"
          :class="isActive(menu.path) ? 'bg-gray-900 shadow-[0_4px_16px_rgba(15,23,42,0.3)] border border-gray-800 text-white' : 'text-gray-400 hover:text-gray-900 hover:bg-white hover:shadow-[0_2px_8px_rgba(0,0,0,0.04)] border border-transparent'">
            <svg class="w-4 h-4" :class="{ 'text-white': isActive(menu.path) }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path v-for="(d, index) in menu.icon.split(' M').map((p, i) => i === 0 ? p : 'M' + p)" :key="index" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="d" />
            </svg>
        </div>
      </RouterLink>

      <div class="mt-auto"></div>

      <!-- Settings -->
      <RouterLink to="/settings" title="Settings" class="relative group">
        <div v-if="isActive('/settings')" class="absolute -left-3 top-1/2 -translate-y-1/2 w-1 h-6 bg-gray-900 rounded-r-full z-50"></div>
        <div class="nav-icon w-10 h-10 rounded-[12px] flex items-center justify-center transition-all z-10"
          :class="isActive('/settings') ? 'bg-gray-900 shadow-[0_4px_16px_rgba(15,23,42,0.3)] border border-gray-800 text-white' : 'text-gray-400 hover:text-gray-900 hover:bg-white hover:shadow-[0_2px_8px_rgba(0,0,0,0.04)] border border-transparent'">
            <svg class="w-4 h-4" :class="{ 'text-white': isActive('/settings') }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </div>
      </RouterLink>
  </div>
</template>
