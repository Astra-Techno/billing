<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useBusinessStore } from '../../stores/business'
import { task } from '../../api'

const route    = useRoute()
const router   = useRouter()
const auth     = useAuthStore()
const biz      = useBusinessStore()
const isActive = (to) => to === '/' ? route.path === '/' : route.path.startsWith(to)

async function logout() {
  try { await task('Auth', 'logout') } catch {}
  auth.logout()
  router.push('/login')
}

const allMenus = [
  { path: '/', icon: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z', title: 'Dashboard', label: 'Home' },
  { path: '/invoices', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', title: 'Invoices', label: 'Invoices' },
  { path: '/quotes', icon: 'M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2', title: 'Quotes', label: 'Quotes', feature: 'quotes' },
  { path: '/delivery-challans', icon: 'M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z', title: 'Delivery Challans', label: 'Challans', feature: 'delivery_challans' },
  { path: '/purchase-orders', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01', title: 'Purchase Orders', label: 'Orders', feature: 'purchase_orders' },
  { path: '/credit-notes', icon: 'M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z', title: 'Credit Notes', label: 'Credits', feature: 'credit_notes' },
  { path: '/clients', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z', title: 'Clients', label: 'Clients' },
  { path: '/products', icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', title: 'Products', label: 'Products' },
  { path: '/expenses', icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z', title: 'Expenses', label: 'Expenses', feature: 'expenses' },
  { path: '/payroll', icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z', title: 'Payroll', label: 'Payroll', feature: 'payroll' },
  { path: '/gst-returns', icon: 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z', title: 'GST Returns', label: 'GST', feature: 'gst_returns' },
  { path: '/reports', icon: 'M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z', title: 'Reports', label: 'Reports', feature: 'reports' }
]

const menus = computed(() => {
  if (auth.isSuperAdmin && !auth.businessId) return []
  return allMenus.filter(m => !m.feature || biz.isEnabled(m.feature))
})
</script>

<template>
  <div class="hidden lg:flex w-[76px] bg-white/80 backdrop-blur-xl border-r border-google-divider/80 flex-col items-center py-4 gap-1 shrink-0 z-40 relative overflow-y-auto no-scrollbar shadow-soft">

    <RouterLink v-for="menu in menus" :key="menu.path" :to="menu.path" :title="menu.title"
      class="relative w-full flex flex-col items-center py-2 px-1 group">
      <!-- Active pill indicator -->
      <div v-if="isActive(menu.path)"
        class="absolute left-0 top-1/2 -translate-y-1/2 w-[3px] h-6 bg-primary-600 rounded-r-full"></div>
      <div class="flex flex-col items-center gap-1 w-full">
        <div class="w-9 h-9 rounded-[10px] flex items-center justify-center transition-all duration-150"
          :class="isActive(menu.path)
            ? 'bg-primary-50 text-primary-600 ring-1 ring-primary-100'
            : 'text-gray-400 group-hover:bg-gray-100 group-hover:text-gray-600'">
          <svg class="w-[17px] h-[17px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path v-for="(d, index) in menu.icon.split(' M').map((p, i) => i === 0 ? p : 'M' + p)" :key="index"
              stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="d" />
          </svg>
        </div>
        <span class="text-[11px] font-semibold tracking-wide leading-none transition-colors"
          :class="isActive(menu.path) ? 'text-primary-600' : 'text-gray-400 group-hover:text-gray-500'">
          {{ menu.label }}
        </span>
      </div>
    </RouterLink>

    <div class="mt-auto pt-2 w-full">
      <!-- Admin section (super admin only) -->
      <template v-if="auth.isSuperAdmin">
        <div class="mx-3 border-t border-gray-100 mb-1"></div>
        <RouterLink to="/admin" title="Admin Panel" class="relative w-full flex flex-col items-center py-2 px-1 group">
          <div v-if="route.path.startsWith('/admin')"
            class="absolute left-0 top-1/2 -translate-y-1/2 w-[3px] h-6 bg-red-500 rounded-r-full"></div>
          <div class="flex flex-col items-center gap-1 w-full">
            <div class="w-9 h-9 rounded-[10px] flex items-center justify-center transition-all duration-150"
              :class="route.path.startsWith('/admin')
                ? 'bg-red-50 text-red-600 ring-1 ring-red-100'
                : 'text-gray-400 group-hover:bg-red-50 group-hover:text-red-500'">
              <svg class="w-[17px] h-[17px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
              </svg>
            </div>
            <span class="text-[11px] font-semibold tracking-wide leading-none transition-colors"
              :class="route.path.startsWith('/admin') ? 'text-red-600' : 'text-gray-400 group-hover:text-red-500'">
              Admin
            </span>
          </div>
        </RouterLink>
      </template>

      <!-- Divider -->
      <div class="mx-3 border-t border-gray-100 mb-2"></div>
      <!-- Settings (hidden for super admin with no business) -->
      <RouterLink v-if="!auth.isSuperAdmin || auth.businessId" to="/settings" title="Settings" class="relative w-full flex flex-col items-center py-2 px-1 group">
        <div v-if="isActive('/settings')"
          class="absolute left-0 top-1/2 -translate-y-1/2 w-[3px] h-6 bg-primary-600 rounded-r-full"></div>
        <div class="flex flex-col items-center gap-1 w-full">
          <div class="w-9 h-9 rounded-[10px] flex items-center justify-center transition-all duration-150"
            :class="isActive('/settings')
              ? 'bg-primary-50 text-primary-600 ring-1 ring-primary-100'
              : 'text-gray-400 group-hover:bg-gray-100 group-hover:text-gray-600'">
            <svg class="w-[17px] h-[17px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
                d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
          </div>
          <span class="text-[11px] font-semibold tracking-wide leading-none transition-colors"
            :class="isActive('/settings') ? 'text-primary-600' : 'text-gray-400 group-hover:text-gray-500'">
            Settings
          </span>
        </div>
      </RouterLink>
      <!-- Logout -->
      <button @click="logout" title="Sign Out" class="w-full flex flex-col items-center py-2 px-1 group">
        <div class="flex flex-col items-center gap-1 w-full">
          <div class="w-9 h-9 rounded-[10px] flex items-center justify-center transition-all duration-150 text-gray-400 group-hover:bg-red-50 group-hover:text-red-500">
            <svg class="w-[17px] h-[17px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
          </div>
          <span class="text-[11px] font-semibold tracking-wide leading-none transition-colors text-gray-400 group-hover:text-red-500">
            Logout
          </span>
        </div>
      </button>
    </div>
  </div>
</template>
