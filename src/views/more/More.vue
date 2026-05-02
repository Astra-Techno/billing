<script setup>
import { useAuthStore } from '../../stores/auth'
import { useRouter } from 'vue-router'
import HelpIcon from '../../components/HelpIcon.vue'

const auth   = useAuthStore()
const router = useRouter()

const allNav = [
  { name: 'Customers',     to: '/clients',      icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
  { name: 'Products',      to: '/products',     icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' },
  { name: 'Credit Notes',  to: '/credit-notes',      icon: 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z' },
  { name: 'Purchase Orders', to: '/purchase-orders',  icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
  { name: 'Delivery Challan', to: '/delivery-challans', icon: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4' },
  { name: 'GST Filing',    to: '/gst-returns',       icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
  { name: 'Reports',       to: '/reports',      icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
  { name: 'Settings',      to: '/settings',     icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z' },
]

async function logout() {
  try { await import('../../api').then(m => m.task('Auth', 'logout')) } catch {}
  auth.logout()
  router.push('/login')
}
</script>

<template>
  <div class="space-y-6 max-w-2xl mx-auto pb-20">
    <div class="flex items-center justify-between">
      <h1 class="page-title flex items-center gap-2">Menu <HelpIcon section="menu" /></h1>
    </div>

    <!-- Profile Section (GPay style Hero) -->
    <div class="bg-white rounded-[2rem] shadow-soft border-0 p-6 flex flex-col items-center text-center animate-fade-in-up">
      <div class="w-24 h-24 rounded-full bg-gradient-to-br from-primary-400 to-primary-600 flex items-center justify-center shadow-soft-blue mb-4">
        <span class="text-white text-4xl font-extrabold">{{ auth.user?.name?.charAt(0) }}</span>
      </div>
      <h2 class="text-2xl font-extrabold text-gray-900">{{ auth.user?.name }}</h2>
      <p class="text-sm text-gray-500 mt-1">{{ auth.user?.email }}</p>
      
      <RouterLink to="/settings" class="mt-5 px-6 py-2 bg-gray-50 text-gray-700 font-bold text-sm rounded-full hover:bg-gray-100 transition-colors">
        Manage Profile
      </RouterLink>
    </div>

    <!-- Menu Grid -->
    <div class="bg-white rounded-[2rem] shadow-soft border-0 p-5 animate-fade-in-up anim-delay-75">
      <div class="grid grid-cols-3 sm:grid-cols-4 gap-4">
        <RouterLink v-for="item in allNav" :key="item.name" :to="item.to"
          class="flex flex-col items-center gap-2 p-3 rounded-2xl hover:bg-gray-50 active:bg-gray-100 transition-colors group">
          <div class="w-14 h-14 rounded-full bg-primary-50 flex items-center justify-center group-hover:scale-110 transition-transform">
            <svg class="w-6 h-6 text-primary-600" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
            </svg>
          </div>
          <span class="text-xs font-bold text-center text-gray-700 group-hover:text-primary-700">{{ item.name }}</span>
        </RouterLink>
      </div>
    </div>

    <!-- Help & Support -->
    <div class="bg-white rounded-[2rem] shadow-soft border-0 p-3 animate-fade-in-up anim-delay-150">
      <RouterLink to="/help" class="flex items-center gap-4 p-4 hover:bg-gray-50 rounded-xl transition-colors">
        <div class="w-12 h-12 rounded-full bg-sky-50 flex items-center justify-center shrink-0">
          <svg class="w-6 h-6 text-sky-600" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
        <div class="flex-1">
          <p class="font-bold text-gray-900 text-sm">Help & Support</p>
          <p class="text-xs text-gray-500 mt-0.5">Read guides and FAQs</p>
        </div>
        <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
      </RouterLink>
    </div>

    <!-- Sign Out Button -->
    <div class="px-2 animate-fade-in-up anim-delay-150">
      <button @click="logout"
        class="w-full flex items-center justify-center gap-2 py-4 rounded-[1.5rem] bg-danger-50 text-danger-600 font-extrabold text-sm hover:bg-danger-100 transition-colors shadow-soft">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
        </svg>
        Sign Out Securely
      </button>
    </div>

  </div>
</template>
