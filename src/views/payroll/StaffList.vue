<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { list, task } from '../../api'
import { inr } from '../../utils/currency'
import { useListRefresh } from '../../composables/useListRefresh'

const route  = useRoute()
const router = useRouter()

const staff   = ref([])
const loading = ref(true)
const search  = ref('')
const deleting = ref(null)

async function load() {
  loading.value = true
  try {
    const { data } = await list('StaffMember')
    staff.value = data.data || []
  } catch {}
  loading.value = false
}

async function deleteStaff(id) {
  if (!confirm('Delete this staff member?')) return
  deleting.value = id
  try {
    await task('StaffMember', 'delete', { id })
    await load()
  } catch {}
  deleting.value = null
}

const filtered = () => {
  if (!search.value) return staff.value
  const q = search.value.toLowerCase()
  return staff.value.filter(s =>
    s.name?.toLowerCase().includes(q) ||
    s.role?.toLowerCase().includes(q)
  )
}

useListRefresh(load, { listRouteName: 'Payroll' })
</script>

<template>
  <div class="flex flex-col lg:flex-row h-full min-h-0 w-full overflow-hidden">

    <!-- Left Pane: Staff List -->
    <div :class="[
      'split-pane-left transition-all duration-300 relative z-30 h-full',
      $route.name !== 'Payroll' ? 'hidden lg:flex' : '',
    ]">

      <!-- Sticky Header -->
      <div class="px-5 py-4 border-b border-gray-200/60 bg-white/60 backdrop-blur-md sticky top-0 z-10">
        <div class="flex justify-between items-center mb-4">
          <h2 class="font-bold text-gray-900 text-sm tracking-tight">Staff</h2>
          <div class="flex gap-2">
            <!-- Payroll Run -->
            <button @click="router.push('/payroll/run')"
              class="w-7 h-7 bg-white border border-gray-200/80 shadow-sm hover:shadow hover:border-gray-300 rounded-lg flex items-center justify-center text-gray-600 transition-all"
              title="Run Payroll">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
              </svg>
            </button>
            <!-- Add Staff -->
            <button @click="router.push('/payroll/staff/new')"
              class="w-7 h-7 bg-white border border-gray-200/80 shadow-sm hover:shadow hover:border-gray-300 rounded-lg flex items-center justify-center text-gray-600 transition-all"
              title="Add Staff">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
              </svg>
            </button>
          </div>
        </div>

        <!-- Search -->
        <input v-model="search" type="text"
          class="w-full bg-white border border-gray-200 shadow-sm text-gray-900 text-xs font-semibold rounded-lg focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 block px-3 py-2 transition-all"
          placeholder="Search staff..." />
      </div>

      <!-- Scrollable List -->
      <div class="flex-1 overflow-y-auto px-3 py-3 space-y-1.5 custom-scrollbar min-h-0">

        <div v-if="loading" class="space-y-1.5">
          <div v-for="i in 5" :key="i"
            class="p-4 rounded-xl border border-gray-100 bg-white/40 animate-pulse flex justify-between">
            <div class="space-y-2">
              <div class="h-3.5 bg-gray-200 rounded w-28"></div>
              <div class="h-2.5 bg-gray-100 rounded w-20"></div>
            </div>
            <div class="h-3.5 bg-gray-200 rounded w-16"></div>
          </div>
        </div>

        <div v-else-if="!filtered().length" class="p-8 text-center">
          <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
          </div>
          <p class="font-bold text-gray-900 text-[13px]">No staff members</p>
          <p class="text-[11px] text-gray-500 mt-1">Add your first staff member to get started</p>
        </div>

        <div v-else v-for="(member, idx) in filtered()" :key="member.id"
          class="p-4 rounded-xl border cursor-pointer transition-all group relative overflow-hidden list-item-1"
          :style="{ animationDelay: (idx * 0.05) + 's' }"
          :class="[
            $route.params.id == member.id
              ? 'bg-white border-gray-200 shadow-[0_2px_8px_rgba(0,0,0,0.03)]'
              : 'border-transparent hover:border-gray-200/60 hover:bg-white hover:shadow-[0_2px_8px_rgba(0,0,0,0.02)]'
          ]"
          @click="router.push(`/payroll/staff/${member.id}/edit`)">

          <!-- Active indicator -->
          <div v-if="$route.params.id == member.id"
            class="absolute left-0 top-0 bottom-0 w-[3px] bg-gray-900 rounded-l-xl"></div>

          <div class="flex items-center gap-3">
            <!-- Avatar -->
            <div class="w-9 h-9 rounded-full bg-primary-100 text-primary-700 font-extrabold text-sm flex items-center justify-center shrink-0">
              {{ member.name?.charAt(0)?.toUpperCase() }}
            </div>

            <div class="flex-1 min-w-0">
              <div class="flex justify-between mb-0.5">
                <span class="text-[14px] font-bold truncate pr-2 tracking-tight transition-colors"
                  :class="$route.params.id == member.id ? 'text-primary-600' : 'text-gray-900 group-hover:text-primary-600'">
                  {{ member.name }}
                </span>
                <span class="text-[14px] font-bold tabular-nums text-gray-700">
                  {{ inr(member.monthly_salary) }}
                </span>
              </div>
              <div class="flex justify-between items-center mt-1">
                <span class="text-[12px] font-semibold text-gray-400">{{ member.role || 'Staff' }}</span>
                <span class="text-[10px] text-gray-400">/month</span>
              </div>
            </div>

            <!-- Delete -->
            <button @click.stop="deleteStaff(member.id)"
              :disabled="deleting === member.id"
              class="opacity-0 group-hover:opacity-100 ml-1 w-7 h-7 rounded-lg hover:bg-red-50 flex items-center justify-center text-gray-300 hover:text-red-500 transition-all shrink-0">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
              </svg>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Pane: Child route -->
    <div v-if="$route.name !== 'Payroll'" class="split-pane-right relative z-20">
      <router-view v-slot="{ Component }">
        <component :is="Component" :key="$route.fullPath" @refresh="load" />
      </router-view>
    </div>

  </div>
</template>
