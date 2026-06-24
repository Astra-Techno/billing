<script setup>
import { ref, onMounted } from 'vue'
import { task } from '../../api'

const businesses = ref([])
const loading     = ref(true)
const toggling    = ref(null)

onMounted(load)

async function load() {
  loading.value = true
  try {
    const res = await task('Admin', 'businesses')
    businesses.value = res.data || []
  } finally {
    loading.value = false
  }
}

async function toggle(biz) {
  toggling.value = biz.id
  try {
    const res = await task('Admin', 'toggleBusiness', { business_id: biz.id })
    biz.active = res.data.active
  } finally {
    toggling.value = null
  }
}

function fmt(date) {
  if (!date) return '—'
  return new Date(date).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>

<template>
  <div class="flex flex-col h-full">
    <!-- Header -->
    <div class="px-4 lg:px-6 pt-4 pb-3 border-b border-gray-100 bg-white flex items-center gap-3">
      <RouterLink to="/admin" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition-colors">
        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
      </RouterLink>
      <div>
        <h1 class="text-base font-bold text-gray-900">Businesses</h1>
        <p class="text-xs text-gray-400">All registered businesses</p>
      </div>
      <span class="ml-auto text-xs text-gray-400 font-medium">{{ businesses.length }} total</span>
    </div>

    <!-- Table -->
    <div class="flex-1 overflow-y-auto">
      <div v-if="loading" class="p-6 space-y-3">
        <div v-for="i in 5" :key="i" class="h-14 bg-gray-100 rounded-xl animate-pulse"></div>
      </div>

      <div v-else-if="!businesses.length" class="flex flex-col items-center justify-center h-48 gap-2">
        <svg class="w-10 h-10 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16"/>
        </svg>
        <p class="text-sm text-gray-400">No businesses found</p>
      </div>

      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-100">
          <tr>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Business</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden md:table-cell">Owner</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide hidden sm:table-cell">Invoices</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden lg:table-cell">Joined</th>
            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="biz in businesses" :key="biz.id" class="hover:bg-gray-50/50 transition-colors">
            <td class="px-4 py-3">
              <div class="font-medium text-gray-800">{{ biz.name }}</div>
              <div class="text-xs text-gray-400">{{ biz.email || biz.mobile || '—' }}</div>
            </td>
            <td class="px-4 py-3 hidden md:table-cell">
              <div class="text-gray-700">{{ biz.owner_name || '—' }}</div>
              <div class="text-xs text-gray-400">{{ biz.owner_email || '' }}</div>
            </td>
            <td class="px-4 py-3 text-center text-gray-600 hidden sm:table-cell">{{ biz.invoice_count }}</td>
            <td class="px-4 py-3 text-gray-500 hidden lg:table-cell text-xs">{{ fmt(biz.created_at) }}</td>
            <td class="px-4 py-3 text-center">
              <button @click="toggle(biz)" :disabled="toggling === biz.id"
                class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold transition-colors"
                :class="biz.active
                  ? 'bg-green-100 text-green-700 hover:bg-red-100 hover:text-red-700'
                  : 'bg-red-100 text-red-700 hover:bg-green-100 hover:text-green-700'">
                <span v-if="toggling === biz.id" class="w-3 h-3 border border-current border-t-transparent rounded-full animate-spin"></span>
                {{ biz.active ? 'Active' : 'Suspended' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
