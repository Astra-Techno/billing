<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { list, task } from '../../api'
import HelpIcon from '../../components/HelpIcon.vue'
import { inr } from '../../utils/currency'
import { fmtDateShort } from '../../utils/date'

const router = useRouter()

const creditNotes = ref([])
const loading     = ref(true)
const acting      = ref(null)
const actError    = ref('')

const statusBadge = (s) => ({
  draft:    'badge-gray',
  issued:   'badge-blue',
  adjusted: 'badge-green',
}[s] || 'badge-gray')

async function load() {
  loading.value = true
  try {
    const cnRes = await list('CreditNote', { sort_by: 'cn.created_at', sort_order: 'desc' })
    creditNotes.value = cnRes.data?.data  || []
  } catch {}
  loading.value = false
}

function openCreate() {
  router.push('/credit-notes/new')
}

async function issueCN(cn) {
  acting.value  = cn.id + '_issue'
  actError.value = ''
  try {
    await task('CreditNote', 'issue', { id: cn.id })
    await load()
  } catch (e) {
    actError.value = e.response?.data?.message || 'Failed to issue.'
  } finally { acting.value = null }
}

async function adjustCN(cn) {
  acting.value  = cn.id + '_adjust'
  actError.value = ''
  try {
    await task('CreditNote', 'adjust', { id: cn.id })
    await load()
  } catch (e) {
    actError.value = e.response?.data?.message || 'Failed to adjust.'
  } finally { acting.value = null }
}

onMounted(load)
</script>

<template>
  <div class="flex flex-col lg:flex-row gap-6 h-[calc(100vh-6rem)]">
    <!-- Left Pane: List -->
    <div :class="{ 'hidden lg:flex': $route.name !== 'CreditNotes', 'w-full lg:w-[35%] flex flex-col': true }">
      <div class="space-y-5 flex-1 overflow-y-auto pr-1 no-scrollbar">
    <div class="flex items-center justify-between">
      <h1 class="page-title flex items-center gap-2">Credit Notes <HelpIcon section="returns" /></h1>
      <button @click="openCreate" class="btn-primary">+ New Credit Note</button>
    </div>

    <div v-if="actError" class="text-sm text-danger-600 bg-danger-50 rounded-lg px-4 py-3">{{ actError }}</div>

    <div class="bg-white rounded-[2rem] shadow-soft border-0 overflow-hidden animate-fade-in-up">
      <div v-if="loading" class="p-12 text-center text-gray-400 text-sm">Loading…</div>
      <div v-else-if="!creditNotes.length" class="p-12 text-center">
        <p class="text-gray-500 font-bold text-lg">No credit notes yet</p>
        <p class="text-sm text-gray-400 mt-1">Issue credit notes to reverse or reduce invoice amounts</p>
        <button @click="openCreate" class="btn bg-primary-600 text-white hover:bg-primary-700 shadow-soft-blue rounded-full px-6 py-2.5 mt-5 inline-flex items-center gap-2 font-bold">New Credit Note</button>
      </div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wider font-semibold">
            <tr>
              <th class="px-5 py-4 text-left">Number</th>
              <th class="px-5 py-4 text-left hidden sm:table-cell">Invoice</th>
              <th class="px-5 py-4 text-left hidden md:table-cell">Date</th>
              <th class="px-5 py-4 text-left hidden md:table-cell">Reason</th>
              <th class="px-5 py-4 text-right">Amount</th>
              <th class="px-5 py-4 text-center">Status</th>
              <th class="px-5 py-4"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr v-for="cn in creditNotes" :key="cn.id" class="hover:bg-gray-50 transition-colors">
              <td class="px-5 py-4 font-bold text-gray-900">{{ cn.number }}</td>
              <td class="px-5 py-4 text-gray-500 hidden sm:table-cell">{{ cn.invoice_number || '—' }}</td>
              <td class="px-5 py-4 text-gray-500 hidden md:table-cell">{{ fmtDateShort(cn.issue_date) }}</td>
              <td class="px-5 py-4 text-gray-500 hidden md:table-cell capitalize">{{ cn.reason }}</td>
              <td class="px-5 py-4 text-right font-extrabold text-gray-900">{{ inr(cn.total) }}</td>
              <td class="px-5 py-4 text-center">
                <span :class="statusBadge(cn.status)" class="text-[10px] px-2 py-0.5 rounded-full uppercase tracking-wider font-bold">{{ cn.status }}</span>
              </td>
              <td class="px-5 py-4">
                <div class="flex items-center gap-2 justify-end">
                  <button v-if="cn.status === 'draft'" @click="issueCN(cn)"
                    :disabled="acting === cn.id + '_issue'"
                    class="text-xs btn bg-primary-50 text-primary-700 hover:bg-primary-100 rounded-full px-3 py-1 font-bold">
                    {{ acting === cn.id + '_issue' ? '…' : 'Issue' }}
                  </button>
                  <button v-if="cn.status === 'issued'" @click="adjustCN(cn)"
                    :disabled="acting === cn.id + '_adjust'"
                    class="text-xs btn bg-emerald-600 text-white hover:bg-emerald-700 rounded-full px-3 py-1 shadow-soft font-bold">
                    {{ acting === cn.id + '_adjust' ? '…' : 'Adjust Invoice' }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
      </div>
    </div>

    <!-- Right Pane: Detail/Form -->
    <div v-if="$route.name !== 'CreditNotes'" class="w-full lg:w-[65%] flex-1 overflow-y-auto no-scrollbar pb-10">
      <router-view v-slot="{ Component }">
        <component :is="Component" :key="$route.fullPath" @refresh="load" />
      </router-view>
    </div>
  </div>
</template>
