<script setup>
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { task } from '../../api'

const route  = useRoute()
const router = useRouter()
const auth   = useAuthStore()

const tabs = [
  { to: '/admin',             label: 'Overview',   icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
  { to: '/admin/businesses',  label: 'Businesses', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
  { to: '/admin/users',       label: 'Users',      icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
]

function isActive(to) {
  return to === '/admin' ? route.path === '/admin' : route.path.startsWith(to)
}

async function logout() {
  try { await task('Auth', 'logout') } catch {}
  auth.logout()
  router.push('/login')
}
</script>

<template>
  <nav class="fixed z-50 bottom-0 inset-x-0 lg:hidden safe-area-pb">
    <div class="mx-3 mb-2 bg-white rounded-2xl border border-gray-200 shadow-gpay-lg overflow-hidden">
      <div class="flex items-stretch h-[3.75rem]">
        <RouterLink
          v-for="tab in tabs"
          :key="tab.to"
          :to="tab.to"
          class="nav-item flex-1 flex flex-col items-center justify-center gap-0.5 min-w-0"
          :class="{ active: isActive(tab.to) }"
        >
          <div class="icon-wrap p-1.5 rounded-xl transition-all duration-200">
            <svg class="w-[20px] h-[20px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" :d="tab.icon" />
            </svg>
          </div>
          <span class="label text-[9px] font-semibold truncate max-w-full">{{ tab.label }}</span>
        </RouterLink>
        <button
          type="button"
          @click="logout"
          class="nav-item flex-1 flex flex-col items-center justify-center gap-0.5 min-w-0 text-red-500"
        >
          <div class="icon-wrap p-1.5 rounded-xl transition-all duration-200">
            <svg class="w-[20px] h-[20px]" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
          </div>
          <span class="label text-[9px] font-semibold">Sign Out</span>
        </button>
      </div>
    </div>
  </nav>
</template>

<style scoped>
.nav-item .icon-wrap { color: #9ca3af; }
.nav-item .label { color: #9ca3af; }
.nav-item.active .icon-wrap { background: #eef2ff; color: #4f46e5; }
.nav-item.active .label { color: #4f46e5; }
.nav-item:not(.active):active .icon-wrap { background: #f3f4f6; }
</style>
