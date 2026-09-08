<script setup>
import { ref, computed, onMounted } from 'vue'
import { list, task } from '../../api'
import { useAuthStore } from '../../stores/auth'
import { useRole } from '../../composables/useRole'
import { fmtDateShort, today } from '../../utils/date'

const auth = useAuthStore()
const { can, role } = useRole()
const isOwnerAdmin = computed(() => ['owner', 'admin'].includes(auth.role))

const entries    = ref([])
const loading    = ref(true)
const searchQ    = ref('')
const statusTab  = ref('')
const showForm   = ref(false)
const editingId  = ref(null)

const form = ref({ work_date: today(), hours: '', description: '', project: '' })

// Filters
const filter = ref({ from_date: '', to_date: '', user_id: '' })

async function load() {
  loading.value = true
  try {
    const p = { sort_by: 't.work_date', sort_order: 'desc' }
    if (filter.value.from_date) p['filter.from_date'] = filter.value.from_date
    if (filter.value.to_date)   p['filter.to_date']   = filter.value.to_date
    if (statusTab.value)        p['filter.status']     = statusTab.value
    if (filter.value.user_id)   p['filter.user_id']    = filter.value.user_id

    // Owner/admin see all; staff sees own via Timesheet:my
    const sqlName = isOwnerAdmin.value ? 'Timesheet' : 'Timesheet:my'
    const res = await list(sqlName, p)
    entries.value = res.data?.data || []
  } catch {}
  loading.value = false
}

const filteredEntries = computed(() => {
  if (!searchQ.value) return entries.value
  const q = searchQ.value.toLowerCase()
  return entries.value.filter(e =>
    e.description?.toLowerCase().includes(q) ||
    e.project?.toLowerCase().includes(q) ||
    e.user_name?.toLowerCase().includes(q)
  )
})

const totalHours = computed(() =>
  filteredEntries.value.reduce((s, e) => s + parseFloat(e.hours || 0), 0).toFixed(1)
)

const pendingCount = computed(() =>
  entries.value.filter(e => e.status === 'pending').length
)

// ── Form ──────────────────────────────────────────────────────────────────────
function canStaffEdit(entry) {
  return isOwnerAdmin.value || entry.work_date === today()
}

function openAdd() {
  editingId.value = null
  form.value = { work_date: today(), hours: '', description: '', project: '' }
  showForm.value = true
}

function openEdit(entry) {
  editingId.value = entry.id
  form.value = {
    work_date:   entry.work_date,
    hours:       entry.hours,
    description: entry.description || '',
    project:     entry.project || '',
  }
  showForm.value = true
}

const saving = ref(false)
async function saveEntry() {
  saving.value = true
  try {
    if (editingId.value) {
      await task('Timesheet', 'update', { id: editingId.value, ...form.value })
    } else {
      await task('Timesheet', 'create', form.value)
    }
    showForm.value = false
    await load()
  } catch {}
  saving.value = false
}

// ── Actions ───────────────────────────────────────────────────────────────────
const actionLoading = ref(null)

async function approveEntry(entry) {
  actionLoading.value = entry.id
  try { await task('Timesheet', 'approve', { id: entry.id }); await load() } catch {}
  actionLoading.value = null
}

async function rejectEntry(entry) {
  actionLoading.value = entry.id
  try { await task('Timesheet', 'reject', { id: entry.id }); await load() } catch {}
  actionLoading.value = null
}

async function deleteEntry(entry) {
  if (!confirm('Delete this entry?')) return
  actionLoading.value = entry.id
  try { await task('Timesheet', 'delete', { id: entry.id }); await load() } catch {}
  actionLoading.value = null
}

async function bulkApproveAll() {
  const ids = entries.value.filter(e => e.status === 'pending').map(e => e.id)
  if (!ids.length) return
  if (!confirm(`Approve all ${ids.length} pending entries?`)) return
  loading.value = true
  try { await task('Timesheet', 'bulkApprove', { ids }); await load() } catch {}
  loading.value = false
}

const statusColor = s => ({
  pending:  'bg-yellow-100 text-yellow-700',
  approved: 'bg-green-100 text-green-700',
  rejected: 'bg-red-100 text-red-700',
}[s] || 'bg-gray-100 text-gray-600')

onMounted(load)
</script>

<template>
  <div class="gpay-screen pb-28 lg:pb-8">
    <!-- Header -->
    <div class="sticky top-0 z-30 bg-white/95 backdrop-blur-md border-b border-google-divider/30">
      <div class="flex items-center justify-between px-4 py-3">
        <h1 class="page-title !mb-0">Timesheets</h1>
        <div class="flex items-center gap-2">
          <button v-if="isOwnerAdmin && pendingCount > 0" @click="bulkApproveAll"
            class="btn-outline btn-sm text-green-600 border-green-300 hover:bg-green-50">
            Approve All ({{ pendingCount }})
          </button>
          <button @click="openAdd" class="btn-primary btn-sm">+ Log Time</button>
        </div>
      </div>

      <!-- Tabs -->
      <div class="flex gap-1 px-4 pb-2 overflow-x-auto no-scrollbar">
        <button @click="statusTab = ''; load()" :class="['tab-chip', !statusTab ? 'tab-chip--active' : '']">All</button>
        <button @click="statusTab = 'pending'; load()" :class="['tab-chip', statusTab === 'pending' ? 'tab-chip--active' : '']">Pending</button>
        <button @click="statusTab = 'approved'; load()" :class="['tab-chip', statusTab === 'approved' ? 'tab-chip--active' : '']">Approved</button>
        <button @click="statusTab = 'rejected'; load()" :class="['tab-chip', statusTab === 'rejected' ? 'tab-chip--active' : '']">Rejected</button>
      </div>

      <!-- Search -->
      <div class="px-4 pb-3">
        <input v-model="searchQ" type="search" placeholder="Search entries..." class="inv-input w-full text-sm" />
      </div>
    </div>

    <!-- Summary bar -->
    <div class="px-4 py-2 bg-gray-50 border-b border-gray-100 flex items-center justify-between text-xs text-gray-500">
      <span>{{ filteredEntries.length }} entries</span>
      <span class="font-semibold text-gray-700">{{ totalHours }} hrs total</span>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-16">
      <div class="w-8 h-8 border-4 border-primary-100 border-t-primary-600 rounded-full animate-spin"></div>
    </div>

    <!-- Empty state -->
    <div v-else-if="!filteredEntries.length" class="text-center py-16 px-6">
      <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gray-100 flex items-center justify-center">
        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      </div>
      <p class="text-gray-500 font-medium">No timesheet entries</p>
      <p class="text-gray-400 text-sm mt-1">Click "Log Time" to add your first entry</p>
    </div>

    <!-- Entries list -->
    <div v-else class="divide-y divide-gray-100">
      <div v-for="e in filteredEntries" :key="e.id"
        class="px-4 py-3 flex items-start gap-3 hover:bg-gray-50/50 transition-colors">

        <!-- Hours badge -->
        <div class="w-12 h-12 rounded-xl bg-primary-50 flex flex-col items-center justify-center shrink-0">
          <span class="text-base font-bold text-primary-700 leading-none">{{ parseFloat(e.hours).toFixed(1) }}</span>
          <span class="text-[9px] text-primary-500 font-medium">hrs</span>
        </div>

        <!-- Content -->
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 mb-0.5">
            <p class="text-sm font-semibold text-gray-800 truncate">{{ e.description }}</p>
            <span :class="['inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold shrink-0', statusColor(e.status)]">
              {{ e.status }}
            </span>
          </div>
          <div class="flex items-center gap-2 text-xs text-gray-400">
            <span>{{ fmtDateShort(e.work_date) }}</span>
            <span v-if="e.project" class="text-gray-500">· {{ e.project }}</span>
            <span v-if="e.user_name && isOwnerAdmin" class="text-primary-500">· {{ e.user_name }}</span>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-1 shrink-0">
          <!-- Approve/Reject for owner/admin on pending entries -->
          <template v-if="isOwnerAdmin && e.status === 'pending'">
            <button @click="approveEntry(e)" :disabled="actionLoading === e.id"
              class="w-8 h-8 rounded-lg bg-green-50 text-green-600 hover:bg-green-100 flex items-center justify-center transition" title="Approve">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </button>
            <button @click="rejectEntry(e)" :disabled="actionLoading === e.id"
              class="w-8 h-8 rounded-lg bg-red-50 text-red-500 hover:bg-red-100 flex items-center justify-center transition" title="Reject">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </template>

          <!-- Edit (own pending today or owner/admin) -->
          <button v-if="(e.status === 'pending' && canStaffEdit(e)) || isOwnerAdmin" @click="openEdit(e)"
            class="w-8 h-8 rounded-lg bg-gray-50 text-gray-500 hover:bg-gray-100 flex items-center justify-center transition" title="Edit">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
          </button>

          <!-- Delete -->
          <button v-if="(e.status === 'pending' && canStaffEdit(e)) || isOwnerAdmin" @click="deleteEntry(e)"
            class="w-8 h-8 rounded-lg bg-gray-50 text-gray-400 hover:bg-red-50 hover:text-red-500 flex items-center justify-center transition" title="Delete">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
          </button>
        </div>
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <div v-if="showForm" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/40" @click.self="showForm = false">
      <div class="bg-white rounded-2xl w-full max-w-md shadow-xl">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
          <h3 class="font-semibold text-gray-800">{{ editingId ? 'Edit Entry' : 'Log Time' }}</h3>
          <button @click="showForm = false" class="text-gray-400 hover:text-gray-600 text-lg">&times;</button>
        </div>
        <form @submit.prevent="saveEntry" class="p-5 space-y-4">
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Date *</label>
            <input v-model="form.work_date" type="date" required class="inv-input w-full"
              :disabled="!isOwnerAdmin" :min="isOwnerAdmin ? undefined : today()" :max="isOwnerAdmin ? undefined : today()" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Hours *</label>
            <input v-model="form.hours" type="number" min="0.25" max="24" step="0.25" required
              class="inv-input w-full" placeholder="e.g. 8" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Description *</label>
            <textarea v-model="form.description" required rows="2" class="inv-input w-full" placeholder="What did you work on?"></textarea>
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Project</label>
            <input v-model="form.project" type="text" class="inv-input w-full" placeholder="Project name (optional)" />
          </div>
          <button type="submit" :disabled="saving" class="btn-primary w-full py-2.5">
            {{ saving ? 'Saving...' : (editingId ? 'Update' : 'Save Entry') }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>
