<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { list } from '../../api'
import HelpIcon from '../../components/HelpIcon.vue'

const router  = useRouter()
const clients = ref([])
const loading = ref(true)
const search  = ref('')
let timer = null

async function load() {
  loading.value = true
  try {
    const p = {}
    if (search.value) p['filter.search'] = `%${search.value}%`
    const { data } = await list('Client', p)
    clients.value = data.data || []
  } catch {}
  loading.value = false
}

function onSearch() { clearTimeout(timer); timer = setTimeout(load, 350) }
onMounted(load)

const avatarColors = ['bg-blue-100 text-blue-700', 'bg-emerald-100 text-emerald-700', 'bg-purple-100 text-purple-700', 'bg-amber-100 text-amber-700', 'bg-pink-100 text-pink-700', 'bg-teal-100 text-teal-700']
const avatarColor  = (name) => avatarColors[(name?.charCodeAt(0) || 0) % avatarColors.length]
</script>

<template>
  <div class="flex flex-col lg:flex-row gap-6 h-[calc(100vh-6rem)]">
    <!-- Left Pane: List -->
    <div :class="{ 'hidden lg:flex': $route.name !== 'Clients', 'w-full lg:w-[35%] flex flex-col': true }">
      <div class="space-y-5 flex-1 overflow-y-auto pr-1 no-scrollbar">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <h1 class="page-title flex items-center gap-2">Customers <HelpIcon section="customers" /></h1>
        <p class="text-sm text-gray-400 mt-0.5 font-medium">All your contacts in one place</p>
      </div>
      <RouterLink to="/clients/new" class="btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
        Add Customer
      </RouterLink>
    </div>

    <div class="relative max-w-md animate-fade-in-up">
      <svg class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
      <input v-model="search" @input="onSearch" type="text" class="w-full bg-white border-0 shadow-soft text-gray-900 text-sm rounded-full focus:ring-2 focus:ring-primary-500 block pl-12 p-3.5 transition-shadow"
        placeholder="Search by name, phone, GST number…" />
    </div>

    <div class="bg-white rounded-[2rem] shadow-soft border-0 overflow-hidden animate-fade-in-up anim-delay-75">
      <div v-if="loading" class="p-12 text-center text-gray-400 text-sm">Loading…</div>

      <div v-else-if="!clients.length" class="p-12 text-center">
        <div class="w-16 h-16 rounded-full bg-primary-50 text-primary-600 flex items-center justify-center mx-auto mb-4">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </div>
        <p class="font-extrabold text-gray-900 text-lg">No customers yet</p>
        <p class="text-sm text-gray-500 mt-1">Add your first customer to start creating bills</p>
        <RouterLink to="/clients/new" class="btn bg-primary-600 text-white hover:bg-primary-700 shadow-soft-blue rounded-full px-6 py-2.5 mt-5 inline-flex items-center gap-2 font-bold">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Add First Customer
        </RouterLink>
      </div>

      <!-- Unified card list -->
      <div v-else class="divide-y divide-gray-50">
        <div v-for="c in clients" :key="c.id"
          class="flex items-center gap-4 px-5 py-4 hover:bg-gray-50 active:bg-gray-100 cursor-pointer transition-colors group"
          @click="router.push(`/clients/${c.id}`)">

          <!-- Avatar -->
          <div class="w-12 h-12 rounded-full flex items-center justify-center shrink-0 font-extrabold text-lg" :class="avatarColor(c.name)">
            {{ c.name?.charAt(0)?.toUpperCase() }}
          </div>

          <!-- Info -->
          <div class="flex-1 min-w-0">
            <p class="font-bold text-gray-900 text-base truncate group-hover:text-primary-700 transition-colors">{{ c.name }}</p>
            <div class="flex items-center gap-2 flex-wrap mt-0.5">
              <p v-if="c.mobile" class="text-xs text-gray-500 font-medium">{{ c.mobile }}</p>
              <span v-if="c.mobile && c.gstin" class="text-gray-300 text-xs">·</span>
              <p v-if="c.gstin" class="text-[11px] text-gray-400 font-mono tracking-tight">{{ c.gstin }}</p>
              <span v-if="(c.mobile || c.gstin) && c.city" class="text-gray-300 text-xs">·</span>
              <p v-if="c.city" class="text-xs text-gray-500">{{ c.city }}</p>
            </div>
          </div>

          <svg class="w-5 h-5 text-gray-300 shrink-0 group-hover:text-primary-500 group-hover:translate-x-1 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </div>
      </div>
    </div>
      </div>
    </div>

    <!-- Right Pane: Detail/Form -->
    <div v-if="$route.name !== 'Clients'" class="w-full lg:w-[65%] flex-1 overflow-y-auto no-scrollbar pb-10">
      <router-view v-slot="{ Component }">
        <component :is="Component" :key="$route.fullPath" @refresh="load" />
      </router-view>
    </div>
  </div>
</template>
