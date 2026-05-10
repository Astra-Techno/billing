<script setup>
import { ref, computed, onMounted } from 'vue'
import { list } from '../../api'
import { inr, inrCompact } from '../../utils/currency'
import { fmtDateShort } from '../../utils/date'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const stats   = ref({ total_due: 0, total_paid_month: 0, total_expenses_month: 0, overdue_count: 0, draft_count: 0, overdue_amount: 0 })
const recent  = ref([])
const overdue = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    const [sR, rR, oR] = await Promise.all([
      list('Dashboard:stats'),
      list('Invoice', { sort_by: 'i.created_at', sort_order: 'desc', limit: 8 }),
      list('Invoice:overdue', { limit: 10 }), // Fetched more for the actions list
    ])
    stats.value   = sR.data?.data?.[0] || {}
    recent.value  = rR.data?.data  || []
    overdue.value = oR.data?.data  || []
    
    // Calculate total overdue amount since it might not be in stats
    stats.value.overdue_amount = overdue.value.reduce((sum, inv) => sum + parseFloat(inv.amount_due || 0), 0)
    
  } catch {}
  loading.value = false
})

const avatarColors = [
  'bg-blue-50 text-blue-600 border-blue-100', 'bg-emerald-50 text-emerald-600 border-emerald-100',
  'bg-purple-50 text-purple-600 border-purple-100', 'bg-amber-50 text-amber-600 border-amber-100',
  'bg-pink-50 text-pink-600 border-pink-100', 'bg-teal-50 text-teal-600 border-teal-100',
]
const avatarColor = name => avatarColors[(name?.charCodeAt(0) || 0) % avatarColors.length]

const topClients = computed(() => {
  const map = new Map()
  for (const inv of recent.value) {
    if (!map.has(inv.client_id)) {
      map.set(inv.client_id, {
        id: inv.client_id,
        name: inv.client_name,
        count: 1,
        total: parseFloat(inv.total || 0)
      })
    } else {
      const client = map.get(inv.client_id)
      client.count++
      client.total += parseFloat(inv.total || 0)
    }
  }
  return Array.from(map.values()).sort((a,b) => b.total - a.total).slice(0, 5)
})

const draftInvoices = computed(() => {
    return recent.value.filter(inv => inv.status === 'draft')
})

const firstName = computed(() => {
    return authStore.user?.name?.split(' ')[0] || 'User'
})

</script>

<template>
  <div class="flex-1 bg-[#FAFAFA] overflow-y-auto flex-col relative z-20 min-h-full">
      <!-- Background Noise -->
      <div class="absolute inset-0 opacity-[0.02] pointer-events-none mix-blend-multiply" style="background-image: url('data:image/svg+xml,%3Csvg viewBox=%220 0 200 200%22 xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cfilter id=%22noiseFilter%22%3E%3CfeTurbulence type=%22fractalNoise%22 baseFrequency=%220.65%22 numOctaves=%223%22 stitchTiles=%22stitch%22/%3E%3C/filter%3E%3Crect width=%22100%25%22 height=%22100%25%22 filter=%22url(%23noiseFilter)%22/%3E%3C/svg%3E');"></div>
      
      <!-- Dashboard Container -->
      <div class="px-5 lg:px-8 pt-6 lg:pt-8 pb-10 max-w-[1300px] mx-auto w-full animate-doc relative z-10">
          
          <!-- Header Row -->
          <div class="flex flex-col lg:flex-row justify-between items-start lg:items-end mb-6 gap-4">
              <div>
                  <h1 class="text-2xl lg:text-[26px] font-black text-gray-900 tracking-tight mb-1">Good morning, {{ firstName }}!</h1>
                  <p class="text-gray-500 font-medium text-[13px] lg:text-[14px]">Here's your business overview for today.</p>
              </div>
              <div class="flex gap-2.5 w-full lg:w-auto overflow-x-auto pb-1 lg:pb-0 hide-scrollbar shrink-0">
                  <button @click="router.push('/expenses/new')" class="px-4 py-2 bg-white border border-gray-200 shadow-sm rounded-xl text-[13px] font-bold text-gray-700 hover:bg-gray-50 hover:border-gray-300 transition-colors flex items-center gap-2 whitespace-nowrap">
                      <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg> Add Expense
                  </button>
                  <button @click="router.push('/invoices/new')" class="px-4 py-2 bg-indigo-600 text-white shadow-md shadow-indigo-600/20 border border-indigo-600 rounded-xl text-[13px] font-bold hover:bg-indigo-700 hover:border-indigo-700 transition-colors flex items-center gap-2 whitespace-nowrap">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg> Create Invoice
                  </button>
              </div>
          </div>
          
          <!-- Stat Cards -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
              <!-- Primary Stat Card -->
              <div class="bg-gray-900 rounded-2xl p-5 text-white shadow-lg shadow-gray-900/10 border border-gray-800 relative overflow-hidden group cursor-pointer" @click="router.push('/invoices?status=paid')">
                  <div class="absolute -right-8 -top-8 w-24 h-24 bg-indigo-500/30 rounded-full blur-2xl group-hover:bg-indigo-400/40 transition-colors duration-700"></div>
                  <div class="relative z-10">
                      <div class="text-gray-400 text-[11px] font-bold uppercase tracking-widest mb-2 flex justify-between">
                          Collected (Month) <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                      </div>
                      <div class="text-2xl font-black tabular-nums tracking-tight">{{ inr(stats.total_paid_month || 0) }}</div>
                      <div class="mt-3 text-gray-400 text-[12px] font-medium">
                          Total received this month
                      </div>
                  </div>
              </div>
              <!-- Second Stat -->
              <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-200/60 flex flex-col justify-between group hover:border-gray-300 transition-colors cursor-pointer" @click="router.push('/invoices')">
                  <div>
                      <div class="text-gray-400 text-[11px] font-bold uppercase tracking-widest mb-2">Outstanding</div>
                      <div class="text-2xl font-black tabular-nums tracking-tight text-gray-900">{{ inr(stats.total_due || 0) }}</div>
                  </div>
                  <div class="mt-3 text-[12px] font-medium text-gray-500">
                      Total pending across invoices
                  </div>
              </div>
              <!-- Third Stat -->
              <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-200/60 flex flex-col justify-between group hover:border-gray-300 transition-colors cursor-pointer" @click="router.push('/expenses')">
                  <div>
                      <div class="text-gray-400 text-[11px] font-bold uppercase tracking-widest mb-2">Total Expenses</div>
                      <div class="text-2xl font-black tabular-nums tracking-tight text-gray-900">{{ inr(stats.total_expenses_month || 0) }}</div>
                  </div>
                  <div class="mt-3 text-[12px] font-medium text-gray-500">
                      Expenses recorded this month
                  </div>
              </div>
              <!-- Fourth Stat (Alert) -->
              <div class="rounded-2xl p-5 shadow-sm flex flex-col justify-between group transition-colors cursor-pointer"
                   :class="stats.overdue_amount > 0 ? 'bg-red-50/50 border border-red-100 hover:bg-red-50' : 'bg-white border border-gray-200/60 hover:border-gray-300'"
                   @click="router.push('/invoices?status=overdue')">
                  <div>
                      <div class="text-[11px] font-bold uppercase tracking-widest mb-2 flex justify-between"
                           :class="stats.overdue_amount > 0 ? 'text-red-400' : 'text-gray-400'">
                          Overdue Amount <svg v-if="stats.overdue_amount > 0" class="w-3.5 h-3.5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                      </div>
                      <div class="text-2xl font-black tabular-nums tracking-tight"
                           :class="stats.overdue_amount > 0 ? 'text-red-600' : 'text-gray-900'">
                          {{ inr(stats.overdue_amount || 0) }}
                      </div>
                  </div>
                  <div class="mt-3 text-[12px] font-medium"
                       :class="stats.overdue_amount > 0 ? 'text-red-700 font-semibold' : 'text-gray-500'">
                      {{ stats.overdue_amount > 0 ? 'Immediate action needed' : 'No overdue invoices' }}
                  </div>
              </div>
          </div>

          <!-- Chart & Actions Row -->
          <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mt-5">
              <!-- Chart Placeholder (Mocked Visuals for Premium Feel) -->
              <div class="col-span-1 lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-200/60 p-6 h-[340px] flex flex-col hidden sm:flex">
                  <div class="flex justify-between items-center mb-6">
                      <h2 class="font-bold text-gray-900 text-[15px]">Revenue & Expenses</h2>
                      <div class="flex gap-2 bg-gray-50 p-1 rounded-lg border border-gray-200">
                          <button class="px-3 py-1 bg-white shadow-sm rounded-md text-[11px] font-bold text-gray-900">6 Months</button>
                          <button class="px-3 py-1 rounded-md text-[11px] font-semibold text-gray-500 hover:text-gray-700">1 Year</button>
                      </div>
                  </div>
                  <div class="flex-1 relative w-full h-full border-b border-gray-100 flex items-end justify-around px-2 pt-2">
                      <div class="absolute inset-0 flex flex-col justify-between pointer-events-none pb-0">
                          <div class="w-full border-t border-gray-100 border-dashed"></div>
                          <div class="w-full border-t border-gray-100 border-dashed"></div>
                          <div class="w-full border-t border-gray-100 border-dashed"></div>
                          <div class="w-full border-t border-gray-100 border-dashed"></div>
                      </div>
                      <div class="flex gap-1 h-full items-end w-8 sm:w-12 group relative z-10">
                          <div class="w-full bg-indigo-100 hover:bg-indigo-200 rounded-t-[6px] transition-colors h-[40%]"></div>
                          <div class="w-full bg-gray-200 hover:bg-gray-300 rounded-t-[6px] transition-colors h-[25%]"></div>
                      </div>
                      <div class="flex gap-1 h-full items-end w-8 sm:w-12 group relative z-10">
                          <div class="w-full bg-indigo-100 hover:bg-indigo-200 rounded-t-[6px] transition-colors h-[55%]"></div>
                          <div class="w-full bg-gray-200 hover:bg-gray-300 rounded-t-[6px] transition-colors h-[30%]"></div>
                      </div>
                      <div class="flex gap-1 h-full items-end w-8 sm:w-12 group relative z-10">
                          <div class="w-full bg-indigo-100 hover:bg-indigo-200 rounded-t-[6px] transition-colors h-[30%]"></div>
                          <div class="w-full bg-gray-200 hover:bg-gray-300 rounded-t-[6px] transition-colors h-[40%]"></div>
                      </div>
                      <div class="flex gap-1 h-full items-end w-8 sm:w-12 group relative z-10">
                          <div class="w-full bg-indigo-100 hover:bg-indigo-200 rounded-t-[6px] transition-colors h-[80%]"></div>
                          <div class="w-full bg-gray-200 hover:bg-gray-300 rounded-t-[6px] transition-colors h-[35%]"></div>
                      </div>
                      <div class="flex gap-1 h-full items-end w-8 sm:w-12 group relative z-10">
                          <div class="w-full bg-indigo-100 hover:bg-indigo-200 rounded-t-[6px] transition-colors h-[65%]"></div>
                          <div class="w-full bg-gray-200 hover:bg-gray-300 rounded-t-[6px] transition-colors h-[20%]"></div>
                      </div>
                      <div class="flex gap-1 h-full items-end w-8 sm:w-12 group relative z-10">
                          <div class="w-full bg-indigo-500 shadow-[0_0_15px_rgba(79,70,229,0.2)] rounded-t-[6px] transition-colors h-[95%]"></div>
                          <div class="w-full bg-gray-400 rounded-t-[6px] transition-colors h-[45%]"></div>
                      </div>
                  </div>
                  <div class="flex justify-around px-2 mt-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest">
                      <span>May</span><span>Jun</span><span>Jul</span><span>Aug</span><span>Sep</span><span class="text-indigo-500">Oct</span>
                  </div>
              </div>
              
              <!-- Action Required -->
              <div class="col-span-1 bg-white rounded-2xl shadow-sm border border-gray-200/60 p-6 flex flex-col h-[340px]">
                  <div class="flex justify-between items-center mb-4">
                      <h2 class="font-bold text-gray-900 text-[15px]">Action Required</h2>
                      <span v-if="overdue.length + draftInvoices.length > 0" class="bg-red-100 text-red-600 text-[10px] font-bold px-2 py-0.5 rounded-full">{{ overdue.length + draftInvoices.length }}</span>
                  </div>
                  <div class="space-y-3 flex-1 overflow-y-auto pr-1 custom-scrollbar">
                      
                      <!-- Empty State -->
                      <div v-if="!loading && overdue.length === 0 && draftInvoices.length === 0" class="h-full flex flex-col items-center justify-center text-center px-4">
                          <div class="w-12 h-12 bg-emerald-50 rounded-full flex items-center justify-center mb-2">
                              <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                          </div>
                          <p class="text-sm font-bold text-gray-900">You're all caught up!</p>
                          <p class="text-xs text-gray-500 mt-1">No pending actions required.</p>
                      </div>

                      <!-- Overdue Items -->
                      <div v-for="inv in overdue" :key="inv.id" class="p-4 rounded-xl border border-red-100 bg-red-50/30 flex flex-col gap-2 group hover:bg-red-50/80 transition-all cursor-pointer" @click="router.push(`/invoices/${inv.id}`)">
                          <div class="flex justify-between items-center">
                              <span class="font-bold text-gray-900 text-[13px] truncate pr-2">{{ inv.client_name }}</span>
                              <span class="font-bold text-red-600 text-[13px] tabular-nums shrink-0">{{ inr(inv.amount_due) }}</span>
                          </div>
                          <span class="text-[12px] font-medium text-gray-500">{{ inv.number }} • Overdue</span>
                      </div>
                      
                      <!-- Draft Items -->
                      <div v-for="inv in draftInvoices" :key="inv.id" class="p-4 rounded-xl border border-gray-200/60 bg-[#FAFAFA] flex flex-col gap-1.5 group hover:bg-white hover:border-gray-300 transition-all cursor-pointer" @click="router.push(`/invoices/${inv.id}`)">
                          <div class="flex justify-between items-center">
                              <span class="font-bold text-gray-900 text-[13px]">Draft Bill</span>
                              <span class="font-bold text-gray-900 text-[13px] tabular-nums">{{ inr(inv.total) }}</span>
                          </div>
                          <span class="text-[12px] font-medium text-gray-500 truncate">{{ inv.client_name }}</span>
                      </div>
                  </div>
              </div>
          </div>

          <!-- Data Tables Row -->
          <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mt-5">
              <!-- Recent Invoices Table -->
              <div class="col-span-1 lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-200/60 p-6 flex flex-col">
                  <div class="flex justify-between items-center mb-5">
                      <h2 class="font-bold text-gray-900 text-[15px]">Recent Invoices</h2>
                      <RouterLink to="/invoices" class="text-[12px] font-bold text-indigo-600 hover:text-indigo-700">View All</RouterLink>
                  </div>
                  
                  <div v-if="loading" class="flex justify-center p-8">
                      <div class="w-6 h-6 border-2 border-indigo-200 border-t-indigo-600 rounded-full animate-spin"></div>
                  </div>
                  
                  <div v-else-if="!recent.length" class="text-center p-8 text-gray-500 text-sm font-medium border-t border-gray-50">
                      No recent invoices found.
                  </div>
                  
                  <div v-else class="flex-1 overflow-x-auto">
                      <table class="w-full text-left">
                          <thead class="border-b border-gray-100">
                              <tr>
                                  <th class="pb-3 px-1 text-[11px] font-bold text-gray-400 uppercase tracking-widest whitespace-nowrap">Client Name</th>
                                  <th class="pb-3 px-1 text-[11px] font-bold text-gray-400 uppercase tracking-widest whitespace-nowrap">Amount</th>
                                  <th class="pb-3 px-1 text-[11px] font-bold text-gray-400 uppercase tracking-widest whitespace-nowrap">Status</th>
                                  <th class="pb-3 px-1 text-[11px] font-bold text-gray-400 uppercase tracking-widest text-right whitespace-nowrap">Date</th>
                              </tr>
                          </thead>
                          <tbody class="divide-y divide-gray-50/80">
                              <tr v-for="inv in recent.slice(0, 5)" :key="inv.id" class="hover:bg-gray-50/50 transition-colors cursor-pointer" @click="router.push(`/invoices/${inv.id}`)">
                                  <td class="py-3.5 px-1">
                                      <div class="text-[13px] font-bold text-gray-900 truncate max-w-[150px] sm:max-w-[200px]">{{ inv.client_name }}</div>
                                      <div class="text-[11px] text-gray-500 font-medium mt-0.5">{{ inv.number }}</div>
                                  </td>
                                  <td class="py-3.5 px-1 text-[13px] font-semibold text-gray-700 tabular-nums whitespace-nowrap">{{ inr(inv.total) }}</td>
                                  <td class="py-3.5 px-1">
                                      <span class="text-[10px] font-bold px-2 py-0.5 rounded-[5px] uppercase tracking-wider border whitespace-nowrap"
                                          :class="{
                                              'text-emerald-600 bg-emerald-50 border-emerald-100': inv.status === 'paid',
                                              'text-amber-600 bg-amber-50 border-amber-100': inv.status === 'draft',
                                              'text-blue-600 bg-blue-50 border-blue-100': inv.status === 'sent',
                                              'text-red-600 bg-red-50 border-red-100': inv.status === 'overdue',
                                              'text-purple-600 bg-purple-50 border-purple-100': inv.status === 'partial',
                                          }">
                                          {{ inv.status }}
                                      </span>
                                  </td>
                                  <td class="py-3.5 px-1 text-right text-[12px] font-medium text-gray-400 whitespace-nowrap">{{ fmtDateShort(inv.issue_date) }}</td>
                              </tr>
                          </tbody>
                      </table>
                  </div>
              </div>
              
              <!-- Top Clients List -->
              <div class="col-span-1 bg-white rounded-2xl shadow-sm border border-gray-200/60 p-6 flex flex-col">
                  <div class="flex justify-between items-center mb-5">
                      <h2 class="font-bold text-gray-900 text-[15px]">Top Clients</h2>
                      <RouterLink to="/clients" class="text-[12px] font-bold text-indigo-600 hover:text-indigo-700">View All</RouterLink>
                  </div>
                  
                  <div v-if="loading" class="flex justify-center p-8">
                      <div class="w-6 h-6 border-2 border-indigo-200 border-t-indigo-600 rounded-full animate-spin"></div>
                  </div>
                  
                  <div v-else-if="!topClients.length" class="text-center p-8 text-gray-500 text-sm font-medium border-t border-gray-50">
                      No client data yet.
                  </div>
                  
                  <div v-else class="space-y-4 flex-1">
                      <div v-for="client in topClients" :key="client.id" class="flex items-center gap-3 group cursor-pointer" @click="router.push(`/clients/${client.id}`)">
                          <div class="w-10 h-10 rounded-[10px] flex items-center justify-center font-bold text-sm border group-hover:scale-105 transition-transform" :class="avatarColor(client.name)">
                              {{ client.name?.charAt(0)?.toUpperCase() }}
                          </div>
                          <div class="flex-1 min-w-0">
                              <div class="text-[13px] font-bold text-gray-900 group-hover:text-indigo-600 transition-colors truncate">{{ client.name }}</div>
                              <div class="text-[11px] font-medium text-gray-500 mt-0.5">{{ client.count }} Invoice{{ client.count !== 1 ? 's' : '' }}</div>
                          </div>
                          <div class="text-[13px] font-bold text-gray-900 tabular-nums shrink-0">{{ inrCompact(client.total) }}</div>
                      </div>
                  </div>
              </div>
          </div>
      </div>
  </div>
</template>
