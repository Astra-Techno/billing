<script setup>
import { useRoute } from 'vue-router'

const route  = useRoute()

const isActive = (to) => to === '/' ? route.path === '/' : route.path.startsWith(to)
</script>

<template>
  <!-- ========== BOTTOM NAV (Mobile Only) ========== -->
  <nav class="fixed z-50 transition-all duration-300
              bottom-0 inset-x-0 bg-white border-t border-gray-100 shadow-[0_-4px_20px_-10px_rgba(0,0,0,0.1)] safe-area-pb
              lg:hidden">
    
    <div class="max-w-2xl mx-auto px-2 flex items-center w-full">
      <RouterLink to="/" class="flex-1 flex flex-col items-center gap-1 py-2 transition-colors group"
        :class="isActive('/') ? 'text-gray-900' : 'text-gray-500'">
        <div class="px-4 py-1 rounded-full transition-colors duration-300"
          :class="isActive('/') ? 'bg-primary-100 text-primary-700' : 'bg-transparent text-gray-500 group-hover:bg-gray-100'">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
          </svg>
        </div>
        <span class="text-[10px] font-semibold tracking-wide" :class="isActive('/') ? 'font-bold' : ''">Home</span>
      </RouterLink>

      <RouterLink to="/invoices" class="flex-1 flex flex-col items-center gap-1 py-2 transition-colors group"
        :class="isActive('/invoices') ? 'text-gray-900' : 'text-gray-500'">
        <div class="px-4 py-1 rounded-full transition-colors duration-300"
          :class="isActive('/invoices') ? 'bg-primary-100 text-primary-700' : 'bg-transparent text-gray-500 group-hover:bg-gray-100'">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
        </div>
        <span class="text-[10px] font-semibold tracking-wide" :class="isActive('/invoices') ? 'font-bold' : ''">Bills</span>
      </RouterLink>

      <!-- New Bill -->
      <!-- New Bill -->
      <RouterLink to="/invoices/new" class="flex-1 flex flex-col items-center gap-1 py-2 transition-colors group text-gray-500">
        <div class="px-4 py-1 rounded-full bg-transparent group-hover:bg-gray-100 transition-colors duration-300 text-gray-500 flex items-center justify-center">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
          </svg>
        </div>
        <span class="text-[10px] font-semibold tracking-wide">New Bill</span>
      </RouterLink>

      <RouterLink to="/quotes" class="flex-1 flex flex-col items-center gap-1 py-2 transition-colors group"
        :class="isActive('/quotes') ? 'text-gray-900' : 'text-gray-500'">
        <div class="px-4 py-1 rounded-full transition-colors duration-300"
          :class="isActive('/quotes') ? 'bg-primary-100 text-primary-700' : 'bg-transparent text-gray-500 group-hover:bg-gray-100'">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"/>
          </svg>
        </div>
        <span class="text-[10px] font-semibold tracking-wide" :class="isActive('/quotes') ? 'font-bold' : ''">Quotes</span>
      </RouterLink>

      <RouterLink to="/expenses" class="flex-1 flex flex-col items-center gap-1 py-2 transition-colors group"
        :class="isActive('/expenses') ? 'text-gray-900' : 'text-gray-500'">
        <div class="px-4 py-1 rounded-full transition-colors duration-300"
          :class="isActive('/expenses') ? 'bg-primary-100 text-primary-700' : 'bg-transparent text-gray-500 group-hover:bg-gray-100'">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
          </svg>
        </div>
        <span class="text-[10px] font-semibold tracking-wide" :class="isActive('/expenses') ? 'font-bold' : ''">Expenses</span>
      </RouterLink>

      <RouterLink to="/more" class="flex-1 flex flex-col items-center gap-1 py-2 transition-colors group"
        :class="isActive('/more') ? 'text-gray-900' : 'text-gray-500'">
        <div class="px-4 py-1 rounded-full transition-colors duration-300"
          :class="isActive('/more') ? 'bg-primary-100 text-primary-700' : 'bg-transparent text-gray-500 group-hover:bg-gray-100'">
          <svg class="w-6 h-6 text-gray-500 group-hover:text-primary-600" :class="isActive('/more') ? 'text-primary-700' : ''" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/>
          </svg>
        </div>
        <span class="text-[10px] font-semibold tracking-wide" :class="isActive('/more') ? 'font-bold' : ''">More</span>
      </RouterLink>

    </div>
  </nav>
</template>
