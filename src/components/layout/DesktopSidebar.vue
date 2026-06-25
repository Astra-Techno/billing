<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useBusinessStore } from '../../stores/business'
import { task } from '../../api'
import { APP_NAME } from '../../config/brand'

const route  = useRoute()
const router = useRouter()
const auth   = useAuthStore()
const biz    = useBusinessStore()

const isActive = (to) => to === '/' ? route.path === '/' : route.path.startsWith(to)

async function logout() {
  try { await task('Auth', 'logout') } catch {}
  auth.logout()
  router.push('/login')
}

const allMenus = [
  {
    group: 'Main',
    items: [
      { path: '/',         label: 'Dashboard', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
      { path: '/invoices', label: 'Invoices',  icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
      { path: '/quotes',   label: 'Quotes',    icon: 'M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2', feature: 'quotes' },
      { path: '/delivery-challans', label: 'Challans', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', feature: 'delivery_challans' },
      { path: '/purchase-orders', label: 'Orders', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01', feature: 'purchase_orders' },
      { path: '/credit-notes', label: 'Credits', icon: 'M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z', feature: 'credit_notes' },
    ]
  },
  {
    group: 'People',
    items: [
      { path: '/clients', label: 'Clients', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
    ]
  },
  {
    group: 'Finance',
    items: [
      { path: '/expenses', label: 'Expenses', icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z', feature: 'expenses' },
      { path: '/payroll',  label: 'Payroll',  icon: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z', feature: 'payroll' },
      { path: '/products', label: 'Products', icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' },
    ]
  },
  {
    group: 'Reports',
    items: [
      { path: '/gst-returns', label: 'GST',     icon: 'M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z', feature: 'gst_returns' },
      { path: '/reports',    label: 'Reports',  icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', feature: 'reports' },
    ]
  },
]

const groups = computed(() => {
  if (auth.isSuperAdmin && !auth.businessId) return []
  return allMenus.map(g => ({
    ...g,
    items: g.items.filter(m => !m.feature || biz.isEnabled(m.feature))
  })).filter(g => g.items.length > 0)
})

const businessInitial = computed(() =>
  biz.business?.name?.charAt(0)?.toUpperCase() || 'B'
)
const businessName = computed(() =>
  biz.business?.name || APP_NAME
)
</script>

<template>
  <aside class="hidden lg:flex flex-col shrink-0 z-40 relative overflow-y-auto no-scrollbar sidebar-root">

    <!-- Brand -->
    <div class="sidebar-brand">
      <div class="sidebar-logo">{{ businessInitial }}</div>
      <span class="sidebar-biz-name">{{ businessName }}</span>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 px-3 py-3 overflow-y-auto no-scrollbar">

      <!-- Super admin: no business -->
      <template v-if="auth.isSuperAdmin && !auth.businessId">
        <p class="sidebar-group-label">Platform</p>
        <RouterLink to="/admin"
          class="nav-item"
          :class="route.path === '/admin' ? 'nav-active' : 'nav-default'">
          <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
          </svg>
          <span>Overview</span>
        </RouterLink>
        <RouterLink to="/admin/businesses"
          class="nav-item"
          :class="route.path === '/admin/businesses' ? 'nav-active' : 'nav-default'">
          <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
          </svg>
          <span>Businesses</span>
        </RouterLink>
        <RouterLink to="/admin/users"
          class="nav-item"
          :class="route.path === '/admin/users' ? 'nav-active' : 'nav-default'">
          <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
          </svg>
          <span>Users</span>
        </RouterLink>
      </template>

      <!-- Regular navigation -->
      <template v-else>
        <template v-for="group in groups" :key="group.group">
          <p class="sidebar-group-label">{{ group.group }}</p>
          <RouterLink v-for="item in group.items" :key="item.path"
            :to="item.path"
            class="nav-item"
            :class="isActive(item.path) ? 'nav-active' : 'nav-default'">
            <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path v-for="(d, i) in item.icon.split(' M').map((p, idx) => idx === 0 ? p : 'M' + p)"
                :key="i" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" :d="d" />
            </svg>
            <span>{{ item.label }}</span>
          </RouterLink>
        </template>

        <!-- Admin shortcut -->
        <template v-if="auth.isSuperAdmin">
          <div class="sidebar-divider"></div>
          <RouterLink to="/admin"
            class="nav-item"
            :class="route.path.startsWith('/admin') ? 'nav-active-danger' : 'nav-danger'">
            <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            <span>Admin</span>
          </RouterLink>
        </template>
      </template>
    </nav>

    <!-- Bottom -->
    <div class="sidebar-footer">
      <RouterLink v-if="!auth.isSuperAdmin || auth.businessId"
        to="/settings"
        class="nav-item"
        :class="isActive('/settings') ? 'nav-active' : 'nav-default'">
        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
            d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
        </svg>
        <span>Settings</span>
      </RouterLink>
      <button @click="logout" class="nav-item nav-logout w-full text-left">
        <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75"
            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
        </svg>
        <span>Sign Out</span>
      </button>
    </div>
  </aside>
</template>

<style scoped>
.sidebar-root {
  width: 224px;
  background: #111827;
}
@media (min-width: 1280px) {
  .sidebar-root { width: 240px; }
}

.sidebar-brand {
  height: 60px;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 0 16px;
  flex-shrink: 0;
  border-bottom: 1px solid #1f2937;
}
.sidebar-logo {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: linear-gradient(135deg, #6366f1, #4f46e5);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 14px;
  font-weight: 700;
  flex-shrink: 0;
}
.sidebar-biz-name {
  font-size: 13px;
  font-weight: 600;
  color: #f9fafb;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.sidebar-group-label {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: #4b5563;
  padding: 4px 10px;
  margin-top: 16px;
  margin-bottom: 2px;
}
.sidebar-group-label:first-child { margin-top: 4px; }
.sidebar-divider {
  border-top: 1px solid #1f2937;
  margin: 8px 8px;
}
.sidebar-footer {
  padding: 12px 12px 16px;
  border-top: 1px solid #1f2937;
  flex-shrink: 0;
}

/* Nav items */
.nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 7px 10px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 500;
  text-decoration: none;
  transition: all 0.12s ease;
  margin-bottom: 1px;
  cursor: pointer;
  border: none;
  background: transparent;
}
.nav-icon { width: 16px; height: 16px; flex-shrink: 0; }

.nav-default { color: #9ca3af; }
.nav-default:hover { background: #1f2937; color: #e5e7eb; }

.nav-active { background: #1e1b4b; color: #e0e7ff; font-weight: 600; }
.nav-active:hover { background: #1e1b4b; }

.nav-danger { color: #9ca3af; }
.nav-danger:hover { background: rgba(239,68,68,0.08); color: #fca5a5; }
.nav-active-danger { background: rgba(239,68,68,0.1); color: #fca5a5; font-weight: 600; }

.nav-logout { color: #6b7280; }
.nav-logout:hover { background: rgba(239,68,68,0.08); color: #fca5a5; }
</style>
