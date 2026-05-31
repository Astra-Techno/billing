<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { list, task } from '../../api'
import HelpIcon from '../../components/HelpIcon.vue'
import { inr } from '../../utils/currency'
import { fmtDateShort } from '../../utils/date'

const router = useRouter()
const route  = useRoute()

const creditNotes  = ref([])
const loading      = ref(true)
const acting       = ref(null)
const actError     = ref('')
const showFilters  = ref(false)
const filter       = ref({ search: '', status: '' })
let timer          = null

const tabs = [
  { label: 'All',      value: '' },
  { label: 'Draft',    value: 'draft' },
  { label: 'Issued',   value: 'issued' },
  { label: 'Adjusted', value: 'adjusted' },
]

const badgeClass  = s => ({ draft: 'badge-gray', issued: 'badge-blue', adjusted: 'badge-green' }[s] || 'badge-gray')
const statusLabel = s => ({ draft: 'Draft', issued: 'Issued', adjusted: 'Adjusted' }[s] || s)

const avatarColors = ['bg-blue-100 text-blue-700', 'bg-emerald-100 text-emerald-700', 'bg-purple-100 text-purple-700', 'bg-amber-100 text-amber-700', 'bg-pink-100 text-pink-700']
const avatarColor  = name => avatarColors[(name?.charCodeAt(0) || 0) % avatarColors.length]

async function load() {
  loading.value = true
  try {
    const p = { sort_by: 'cn.created_at', sort_order: 'desc' }
    if (filter.value.status) p['filter.status'] = filter.value.status
    if (filter.value.search) p['filter.search'] = `%${filter.value.search}%`
    const cnRes = await list('CreditNote', p)
    creditNotes.value = cnRes.data?.data || []
  } catch {}
  loading.value = false
}

function onSearch() { clearTimeout(timer); timer = setTimeout(load, 350) }

async function issueCN(cn) {
  acting.value   = cn.id + '_issue'
  actError.value = ''
  try {
    await task('CreditNote', 'issue', { id: cn.id })
    await load()
  } catch (e) {
    actError.value = e.response?.data?.message || 'Failed to issue.'
  } finally { acting.value = null }
}

async function adjustCN(cn) {
  acting.value   = cn.id + '_adjust'
  actError.value = ''
  try {
    await task('CreditNote', 'adjust', { id: cn.id })
    await load()
  } catch (e) {
    actError.value = e.response?.data?.message || 'Failed to adjust.'
  } finally { acting.value = null }
}

onMounted(load)
watch(() => route.name, name => { if (name === 'CreditNotes') load() })
</script>

<template>
  <div class="flex flex-col lg:flex-row h-full min-h-0 w-full overflow-hidden">

    <!-- Left Pane -->
    <div id="c3-left-panel" :class="{ 'hidden lg:flex': $route.name !== 'CreditNotes', 'split-pane-left transition-all duration-300 relative z-30 h-full': true }">

      <!-- Sticky Header -->
      <div class="px-5 py-4 border-b border-gray-200/60 bg-white/60 backdrop-blur-md sticky top-0 z-10">
        <div class="flex justify-between items-center mb-4">
          <h2 class="font-bold text-gray-900 text-sm tracking-tight flex items-center gap-2">Credit Notes <HelpIcon section="returns" class="w-3.5 h-3.5" /></h2>
          <div class="flex gap-2">
            <button @click="showFilters = !showFilters"
              class="w-7 h-7 bg-white border border-gray-200/80 shadow-sm hover:shadow hover:border-gray-300 rounded-lg flex items-center justify-center transition-all"
              :class="showFilters ? 'text-primary-600 border-primary-200 bg-primary-50' : 'text-gray-600'">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </button>
            <RouterLink to="/credit-notes/new"
              class="w-7 h-7 bg-white border border-gray-200/80 shadow-sm hover:shadow hover:border-gray-300 rounded-lg flex items-center justify-center text-gray-600 transition-all">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            </RouterLink>
          </div>
        </div>

        <!-- Search -->
        <div v-show="showFilters" class="mb-3 animate-fade-in-up">
          <input v-model="filter.search" @input="onSearch" type="text"
            class="w-full bg-white border border-gray-200 shadow-sm text-gray-900 text-xs font-semibold rounded-lg focus:ring-2 focus:ring-primary-500/20 focus:border-primary-500 block px-3 py-2 transition-all"
            placeholder="Search by number, invoice, reason…" />
        </div>

        <!-- Status Tabs -->
        <div class="flex gap-1 bg-gray-100/80 p-1 rounded-[10px] ring-1 ring-inset ring-gray-200/50 overflow-x-auto hide-scrollbar">
          <button v-for="t in tabs" :key="t.value"
            @click="filter.status = t.value; load()"
            class="flex-1 text-[11px] font-semibold rounded-md py-1.5 transition-all whitespace-nowrap px-2"
            :class="filter.status === t.value ? 'bg-white shadow-sm text-gray-900 font-bold' : 'text-gray-500 hover:text-gray-700'">
            {{ t.label }}
          </button>
        </div>
      </div>

      <!-- Error -->
      <div v-if="actError" class="mx-3 mt-2 text-xs text-danger-600 bg-danger-50 rounded-lg px-3 py-2">{{ actError }}</div>

      <!-- Scrollable List -->
      <div class="flex-1 overflow-y-auto px-3 py-3 space-y-1.5 custom-scrollbar min-h-0">

        <div v-if="loading" class="space-y-1.5">
          <div v-for="i in 5" :key="i" class="p-4 rounded-xl border border-gray-100 bg-white/40 animate-pulse flex justify-between items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-gray-200 shrink-0"></div>
            <div class="space-y-2 flex-1"><div class="h-3.5 bg-gray-200 rounded w-24"></div><div class="h-2.5 bg-gray-100 rounded w-16"></div></div>
            <div class="h-3.5 bg-gray-200 rounded w-16 shrink-0"></div>
          </div>
        </div>

        <div v-else-if="!creditNotes.length" class="p-8 text-center">
          <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
            <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l-4-4 4-4m6 8l4-4-4-4"/></svg>
          </div>
          <p class="font-bold text-gray-900 text-[13px]">No credit notes yet</p>
          <p class="text-[11px] text-gray-500 mt-1">Issue credit notes to reverse or reduce invoices</p>
          <RouterLink to="/credit-notes/new" class="btn bg-primary-600 text-white hover:bg-primary-700 shadow-gpay rounded-full px-5 py-2 mt-4 inline-flex items-center gap-2 font-bold text-xs">New Credit Note</RouterLink>
        </div>

        <div v-else>
          <div v-for="(cn, idx) in creditNotes" :key="cn.id"
            class="p-4 rounded-xl border border-transparent transition-all group relative overflow-hidden list-item-1"
            :style="{ animationDelay: (idx * 0.05) + 's' }"
            :class="[$route.params.id == cn.id ? 'bg-white border-gray-200 shadow-[0_2px_8px_rgba(0,0,0,0.03)]' : 'hover:border-gray-200/60 hover:bg-white hover:shadow-[0_2px_8px_rgba(0,0,0,0.02)]']">
            <div v-if="$route.params.id == cn.id" class="absolute left-0 top-0 bottom-0 w-[3px] bg-gray-900 rounded-l-xl"></div>
            <div class="flex gap-3 items-start">
              <div class="w-9 h-9 rounded-lg flex items-center justify-center font-bold text-xs border shrink-0 mt-0.5"
                :class="avatarColor(cn.client_name || cn.number)">
                {{ (cn.client_name || cn.number)?.charAt(0)?.toUpperCase() || 'C' }}
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-1 mb-0.5">
                  <p class="font-bold text-gray-900 text-[13px] truncate">{{ cn.client_name || cn.number }}</p>
                  <p class="font-bold text-gray-900 text-[13px] shrink-0 tabular-nums">{{ inr(cn.total) }}</p>
                </div>
                <div class="flex items-center justify-between mb-2">
                  <p class="text-[11px] text-gray-500">{{ cn.number }} · {{ fmtDateShort(cn.issue_date) }}</p>
                  <span :class="badgeClass(cn.status)" class="text-[10px]">{{ statusLabel(cn.status) }}</span>
                </div>
                <!-- Inline actions -->
                <div class="flex gap-1.5">
                  <button v-if="cn.status === 'draft'" @click.stop="issueCN(cn)"
                    :disabled="acting === cn.id + '_issue'"
                    class="text-[11px] font-semibold px-2.5 py-1 rounded-md bg-primary-50 text-primary-700 hover:bg-primary-100 transition-colors disabled:opacity-50">
                    {{ acting === cn.id + '_issue' ? '…' : 'Issue' }}
                  </button>
                  <button v-if="cn.status === 'issued'" @click.stop="adjustCN(cn)"
                    :disabled="acting === cn.id + '_adjust'"
                    class="text-[11px] font-semibold px-2.5 py-1 rounded-md bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition-colors disabled:opacity-50">
                    {{ acting === cn.id + '_adjust' ? '…' : 'Adjust Invoice' }}
                  </button>
                  <RouterLink :to="`/credit-notes/${cn.id}/edit`"
                    class="text-[11px] font-semibold px-2.5 py-1 rounded-md bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">
                    Edit
                  </RouterLink>
                </div>
              </div>
            </div>
          </div>

          <div class="pt-3 pb-1 text-center">
            <span class="text-[10px] text-gray-400 font-semibold uppercase tracking-widest">{{ creditNotes.length }} note{{ creditNotes.length !== 1 ? 's' : '' }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Right Pane -->
    <div v-if="$route.name !== 'CreditNotes'" id="c3-right-view" class="split-pane-right relative z-20">
      <router-view v-slot="{ Component }">
        <component :is="Component" :key="$route.fullPath" @refresh="load" />
      </router-view>
    </div>

  </div>
</template>
