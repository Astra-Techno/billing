<script setup>
import { useRoute } from 'vue-router'

const route = useRoute()
const isActive = (to) => to === '/' ? route.path === '/' : route.path.startsWith(to)

const items = [
  { to: '/', label: 'Home', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
  { to: '/invoices', label: 'Bills', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
  { to: '/invoices/new', label: 'New', icon: 'M12 4v16m8-8H4', primary: true },
  { to: '/quotes', label: 'Quotes', icon: 'M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2' },
  { to: '/expenses', label: 'Money', icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z' },
  { to: '/more', label: 'You', icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z' },
]
</script>

<template>
  <nav class="fixed z-50 bottom-0 inset-x-0 bg-white border-t border-google-divider lg:hidden safe-area-pb">
    <div class="flex items-stretch justify-around max-w-lg mx-auto h-[3.25rem]">
      <RouterLink
        v-for="item in items"
        :key="item.to"
        :to="item.to"
        class="flex-1 flex flex-col items-center justify-center gap-0.5 min-w-0 relative"
        :class="item.primary ? '-mt-3' : ''"
      >
        <div
          v-if="item.primary"
          class="w-12 h-12 rounded-full bg-primary-600 text-white flex items-center justify-center shadow-gpay-lg ring-4 ring-white"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
          </svg>
        </div>
        <template v-else>
          <svg
            class="w-6 h-6 transition-colors"
            :class="isActive(item.to) ? 'text-primary-600' : 'text-google-muted'"
            fill="none"
            stroke="currentColor"
            stroke-width="1.75"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
          </svg>
          <span
            class="text-[10px] font-medium truncate max-w-full px-0.5"
            :class="isActive(item.to) ? 'text-primary-600' : 'text-google-muted'"
          >{{ item.label }}</span>
          <span
            v-if="isActive(item.to)"
            class="absolute bottom-1 w-1 h-1 rounded-full bg-primary-600"
          />
        </template>
      </RouterLink>
    </div>
  </nav>
</template>
