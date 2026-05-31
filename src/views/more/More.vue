<script setup>
import { computed } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useRouter } from 'vue-router'
import { useRole } from '../../composables/useRole'
import { useBusinessStore } from '../../stores/business'
import HelpIcon from '../../components/HelpIcon.vue'

const auth    = useAuthStore()
const bizStore = useBusinessStore()
const router  = useRouter()
const { can } = useRole()

const bizSlug = computed(() => {
  const biz = auth.businesses?.find(b => b.id == auth.businessId)
  return biz?.slug || null
})

const allNavRaw = [
  { name: 'Customers',       to: '/clients',           perm: null,       icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 4 4 0 016 0z', tint: 'from-primary-500 to-primary-700' },
  { name: 'Products',        to: '/products',          perm: null,       icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', tint: 'from-violet-500 to-purple-600' },
  { name: 'Credit Notes',    to: '/credit-notes',      perm: null,       icon: 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z', tint: 'from-rose-500 to-pink-600' },
  { name: 'Purchase Orders', to: '/purchase-orders',   perm: null,       icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2', tint: 'from-amber-500 to-orange-600' },
  { name: 'Delivery Challan',to: '/delivery-challans', perm: null,       icon: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4', tint: 'from-cyan-500 to-blue-600' },
  { name: 'GST Filing',      to: '/gst-returns',       perm: 'reports',  icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z', tint: 'from-emerald-500 to-teal-600' },
  { name: 'Reports',         to: '/reports',           perm: 'reports',  icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z', tint: 'from-indigo-500 to-blue-600' },
  { name: 'Settings',        to: '/settings',          perm: 'settings', icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z', tint: 'from-slate-600 to-slate-800' },
]
const allNav = computed(() => allNavRaw.filter(n => !n.perm || can(n.perm)))

async function logout() {
  try { await import('../../api').then(m => m.task('Auth', 'logout')) } catch {}
  auth.logout()
  router.push('/login')
}
</script>

<template>
  <div class="gpay-screen lg:flex lg:flex-row lg:gap-8 lg:p-8 lg:max-w-5xl lg:mx-auto pb-8">

    <div class="px-4 pt-5 lg:w-80 shrink-0">
      <h1 class="page-title flex items-center gap-2 lg:hidden">Account <HelpIcon section="menu" class="w-4 h-4 opacity-60" /></h1>

      <div class="mt-4 lg:mt-0 card-premium p-5 relative overflow-hidden">
        <div class="absolute -right-8 -top-8 w-32 h-32 bg-primary-400/20 rounded-full blur-2xl pointer-events-none"></div>
        <div class="relative flex items-center gap-4">
          <div class="w-[4.5rem] h-[4.5rem] rounded-2xl bg-gradient-to-br from-primary-500 to-primary-800 flex items-center justify-center text-white text-2xl font-bold shadow-premium ring-4 ring-white shrink-0">
            {{ auth.user?.name?.charAt(0) }}
          </div>
          <div class="min-w-0 flex-1">
            <h2 class="text-xl font-bold text-ink truncate">{{ auth.user?.name }}</h2>
            <p class="text-sm text-google-muted truncate mt-0.5">{{ auth.user?.email }}</p>
            <p v-if="bizStore.business?.name" class="text-xs font-semibold text-primary-600 mt-2 truncate">{{ bizStore.business.name }}</p>
            <span v-if="auth.role && auth.role !== 'owner'" class="inline-block mt-2 badge badge-gray">{{ auth.role }}</span>
          </div>
        </div>
        <div class="flex gap-2 mt-5 relative">
          <RouterLink to="/settings" class="btn-primary btn-sm flex-1 text-center">Settings</RouterLink>
          <RouterLink v-if="bizSlug" :to="`/shop/${bizSlug}`" target="_blank" class="btn-outline btn-sm flex-1 text-center">Card</RouterLink>
        </div>
      </div>
    </div>

    <div class="flex-1 mt-4 lg:mt-0">
      <div class="gpay-section-card lg:!mx-0">
        <p class="section-title px-4 pt-4 pb-0">Services</p>

        <RouterLink
          v-for="item in allNav"
          :key="item.name"
          :to="item.to"
          class="gpay-activity-row border-b border-google-divider/50 last:border-0 hover:bg-primary-50/30"
        >
          <div class="gpay-action-tile-icon !w-11 !h-11 !rounded-xl shrink-0" :class="'bg-gradient-to-br ' + item.tint">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
            </svg>
          </div>
          <span class="text-[15px] font-semibold text-ink flex-1">{{ item.name }}</span>
          <svg class="w-5 h-5 text-google-muted/60 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </RouterLink>

        <RouterLink to="/help" class="gpay-activity-row border-b border-google-divider/50 hover:bg-primary-50/30">
          <div class="gpay-action-tile-icon !w-11 !h-11 !rounded-xl !bg-gradient-to-br from-sky-400 to-blue-600 shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <div class="flex-1">
            <p class="text-[15px] font-semibold text-ink">Help & Support</p>
            <p class="text-xs text-google-muted">Guides and FAQs</p>
          </div>
          <svg class="w-5 h-5 text-google-muted/60 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </RouterLink>

        <button type="button" @click="logout" class="gpay-activity-row w-full text-left text-danger-600 hover:bg-danger-50/50">
          <div class="gpay-action-tile-icon !w-11 !h-11 !rounded-xl !bg-gradient-to-br from-red-500 to-rose-600 shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
          </div>
          <span class="text-[15px] font-semibold">Sign out</span>
        </button>
      </div>
    </div>
  </div>
</template>
