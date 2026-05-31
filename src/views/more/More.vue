<script setup>
import { computed } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useRouter } from 'vue-router'
import { useRole } from '../../composables/useRole'
import HelpIcon from '../../components/HelpIcon.vue'

const auth    = useAuthStore()
const router  = useRouter()
const { can } = useRole()

const bizSlug = computed(() => {
  const biz = auth.businesses?.find(b => b.id == auth.businessId)
  return biz?.slug || null
})

const allNavRaw = [
  { name: 'Customers',       to: '/clients',           perm: null,       icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
  { name: 'Products',        to: '/products',          perm: null,       icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' },
  { name: 'Credit Notes',    to: '/credit-notes',      perm: null,       icon: 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z' },
  { name: 'Purchase Orders', to: '/purchase-orders',   perm: null,       icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
  { name: 'Delivery Challan',to: '/delivery-challans', perm: null,       icon: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4' },
  { name: 'GST Filing',      to: '/gst-returns',       perm: 'reports',  icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
  { name: 'Reports',         to: '/reports',           perm: 'reports',  icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
  { name: 'Settings',        to: '/settings',          perm: 'settings', icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z' },
]
const allNav = computed(() => allNavRaw.filter(n => !n.perm || can(n.perm)))

async function logout() {
  try { await import('../../api').then(m => m.task('Auth', 'logout')) } catch {}
  auth.logout()
  router.push('/login')
}
</script>

<template>
  <div class="gpay-screen lg:flex lg:flex-row lg:gap-6 lg:p-6 lg:max-w-5xl lg:mx-auto">

    <!-- Profile header (Google Pay "You" tab style) -->
    <div class="px-4 pt-4 pb-6 lg:w-80 shrink-0">
      <h1 class="page-title flex items-center gap-2 lg:hidden">You <HelpIcon section="menu" class="w-4 h-4" /></h1>

      <div class="flex items-center gap-4 mt-4 lg:mt-0 p-4 lg:p-6 bg-surface-muted rounded-gpay-xl">
        <div class="w-16 h-16 rounded-full bg-primary-600 flex items-center justify-center text-white text-2xl font-medium shrink-0">
          {{ auth.user?.name?.charAt(0) }}
        </div>
        <div class="min-w-0">
          <h2 class="text-xl font-medium text-google-text truncate">{{ auth.user?.name }}</h2>
          <p class="text-sm text-google-muted truncate">{{ auth.user?.email }}</p>
          <span v-if="auth.role && auth.role !== 'owner'" class="inline-block mt-2 text-[10px] font-medium uppercase tracking-wide px-2 py-0.5 rounded-full bg-white text-google-muted">
            {{ auth.role }}
          </span>
        </div>
      </div>

      <div class="flex flex-col gap-2 mt-4">
        <RouterLink to="/settings" class="gpay-activity-row rounded-gpay-lg bg-white border border-google-divider">
          <span class="text-[15px] text-google-text">Manage profile & business</span>
          <svg class="w-5 h-5 text-google-muted ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </RouterLink>
        <RouterLink v-if="bizSlug" :to="`/shop/${bizSlug}`" target="_blank" class="gpay-activity-row rounded-gpay-lg bg-white border border-google-divider">
          <span class="text-[15px] text-google-text">View business card</span>
          <svg class="w-5 h-5 text-google-muted ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
        </RouterLink>
      </div>
    </div>

    <!-- Menu list -->
    <div class="flex-1 bg-white lg:rounded-gpay-xl lg:border lg:border-google-divider lg:overflow-hidden">
      <p class="px-4 pt-4 pb-2 text-xs font-medium text-google-muted uppercase tracking-wide hidden lg:block">More services</p>

      <RouterLink
        v-for="item in allNav"
        :key="item.name"
        :to="item.to"
        class="gpay-activity-row border-b border-google-divider/60 last:border-0"
      >
        <div class="gpay-activity-icon bg-primary-50 text-primary-600">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
          </svg>
        </div>
        <span class="text-[15px] text-google-text flex-1">{{ item.name }}</span>
        <svg class="w-5 h-5 text-google-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
      </RouterLink>

      <RouterLink to="/help" class="gpay-activity-row border-b border-google-divider/60">
        <div class="gpay-activity-icon bg-sky-50 text-sky-600">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
        <div class="flex-1">
          <p class="text-[15px] text-google-text">Help & Support</p>
          <p class="text-xs text-google-muted">Guides and FAQs</p>
        </div>
        <svg class="w-5 h-5 text-google-muted shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
      </RouterLink>

      <button
        type="button"
        @click="logout"
        class="gpay-activity-row w-full text-left text-danger-500"
      >
        <div class="gpay-activity-icon bg-danger-50 text-danger-500">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
          </svg>
        </div>
        <span class="text-[15px] font-medium">Sign out</span>
      </button>
    </div>
  </div>
</template>
