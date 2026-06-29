<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import AdminMobileNav from './AdminMobileNav.vue'

const route = useRoute()
const auth  = useAuthStore()
const isActive = (to) => to === '/' ? route.path === '/' : route.path.startsWith(to)

const items = [
  { to: '/',        label: 'Home',   icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
  { to: '/invoices',label: 'Bills',  icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
  { to: '/quotes',  label: 'Quotes', icon: 'M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2' },
  { to: '/expenses', label: 'Expenses', icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z' },
  { to: '/more',    label: 'More',   icon: 'M4 6h16M4 12h16M4 18h16' },
]

const showNavbar = computed(() => {
  return ['Dashboard', 'Invoices', 'Quotes', 'Expenses', 'Products', 'CreditNotes', 'PurchaseOrders', 'DeliveryChallans', 'GstReturns', 'Reports', 'Settings', 'Help', 'More', 'Clients', 'Payroll', 'StaffNew', 'StaffEdit', 'PayrollRun'].includes(route.name)
})

const showAdminNav = computed(() =>
  auth.isSuperAdmin && !auth.businessId &&
  ['AdminDashboard', 'AdminBusinesses', 'AdminUsers'].includes(route.name)
)
</script>

<template>
  <AdminMobileNav v-if="showAdminNav" />
  <nav v-else-if="showNavbar" class="fixed z-50 bottom-0 inset-x-0 lg:hidden safe-area-pb">
    <div class="mx-3 mb-2 bg-white rounded-2xl border border-gray-200 shadow-gpay-lg overflow-hidden">
      <div class="flex items-stretch justify-around h-[3.75rem] px-1">
        <RouterLink
          v-for="item in items"
          :key="item.to"
          :to="item.to"
          class="nav-item flex-1 flex flex-col items-center justify-center gap-0.5 min-w-0 relative"
          :class="{ active: isActive(item.to) }"
        >
          <div class="icon-wrap p-1.5 rounded-xl transition-all duration-200">
            <svg class="w-[22px] h-[22px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
            </svg>
          </div>
          <span class="label text-[10px] font-semibold truncate max-w-full transition-colors duration-200">{{ item.label }}</span>
        </RouterLink>
      </div>
    </div>
  </nav>
</template>

<style scoped>
.nav-item .icon-wrap {
  color: #9ca3af;
}
.nav-item .label {
  color: #9ca3af;
}
.nav-item.active .icon-wrap {
  background: #eef2ff;
  color: #4f46e5;
}
.nav-item.active .label {
  color: #4f46e5;
}
.nav-item:not(.active):active .icon-wrap,
.nav-item:not(.active):hover .icon-wrap {
  background: #f3f4f6;
  color: #374151;
}
</style>
