<script setup>
import { ref, computed, onMounted } from 'vue'
import { list, count, task } from '../../api'
import { useAuthStore } from '../../stores/auth'
import { useRole } from '../../composables/useRole'
import { fmtDateShort, today } from '../../utils/date'

const auth = useAuthStore()
const { can, role } = useRole()
const isOwnerAdmin = computed(() => ['owner', 'admin'].includes(auth.role))

function calcHours(from, to) {
  if (!from || !to) return 0
  const [fh, fm] = from.split(':').map(Number)
  const [th, tm] = to.split(':').map(Number)
  const diff = (th * 60 + tm) - (fh * 60 + fm)
  return diff > 0 ? Math.round(diff / 15) * 0.25 : 0
}

function fmtTime12(t) {
  if (!t) return ''
  const [h, m] = t.slice(0, 5).split(':').map(Number)
  const ampm = h >= 12 ? 'PM' : 'AM'
  const h12 = h % 12 || 12
  return `${String(h12).padStart(2, '0')}:${String(m).padStart(2, '0')} ${ampm}`
}

const entries    = ref([])
const loading    = ref(true)
const statusTab  = ref('')
const showForm   = ref(false)
const editingId  = ref(null)

// Pagination
const page       = ref(1)
const perPage    = 20
const totalCount = ref(0)
const totalPages = computed(() => Math.ceil(totalCount.value / perPage) || 1)

const form = ref({ work_date: today(), hours: '', description: '', project: '' })
const blankRow = () => ({ from_time: '09:00', to_time: '18:00', description: '', project: '' })
const multiRows = ref([blankRow()])

async function load() {
  loading.value = true
  try {
    const p = {
      sort_by: 't.work_date', sort_order: 'desc',
      limit: perPage, page: page.value,
    }
    if (statusTab.value) p['filter.status'] = statusTab.value

    const sqlName = isOwnerAdmin.value ? 'Timesheet' : 'Timesheet:my'
    const [res, cntRes] = await Promise.all([
      list(sqlName, p),
      count(sqlName, p),
    ])
    entries.value = res.data?.data || []
    totalCount.value = cntRes.data?.total || 0
  } catch {}
  loading.value = false
}

function goPage(p) {
  if (p < 1 || p > totalPages.value) return
  page.value = p
  load()
}

function changeStatus(s) {
  statusTab.value = s
  page.value = 1
  load()
}

// Group entries by date
const groupedEntries = computed(() => {
  const groups = {}
  for (const e of entries.value) {
    const d = e.work_date
    if (!groups[d]) groups[d] = { date: d, entries: [], hours: 0 }
    groups[d].entries.push(e)
    groups[d].hours += parseFloat(e.hours || 0)
  }
  return Object.values(groups).sort((a, b) => b.date.localeCompare(a.date))
})

// Within each date group, sub-group by user
function userGroupsForDate(dateEntries) {
  const groups = {}
  for (const e of dateEntries) {
    const uid = e.user_id || 'me'
    if (!groups[uid]) {
      groups[uid] = {
        user_id: uid,
        user_name: e.user_name || auth.user?.name || 'You',
        entries: [],
        totalHours: 0,
        minFrom: null,
        maxTo: null,
      }
    }
    const g = groups[uid]
    g.entries.push(e)
    g.totalHours += parseFloat(e.hours || 0)
    const ft = e.from_time?.slice(0, 5)
    const tt = e.to_time?.slice(0, 5)
    if (ft && (!g.minFrom || ft < g.minFrom)) g.minFrom = ft
    if (tt && (!g.maxTo || tt > g.maxTo)) g.maxTo = tt
  }
  return Object.values(groups)
}

const totalHours = computed(() =>
  entries.value.reduce((s, e) => s + parseFloat(e.hours || 0), 0)
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
  multiRows.value = [blankRow()]
  showForm.value = true
}

function addRow() {
  const prev = multiRows.value[multiRows.value.length - 1]
  const fromTime = prev?.to_time || '09:00'
  multiRows.value.push({ from_time: fromTime, to_time: '', description: '', project: '' })
}

function onToTimeChange(ri) {
  const next = multiRows.value[ri + 1]
  if (next) next.from_time = multiRows.value[ri].to_time
}

function removeRow(i) {
  if (multiRows.value.length > 1) multiRows.value.splice(i, 1)
}

function openEdit(entry) {
  editingId.value = entry.id
  form.value = {
    work_date:   entry.work_date,
    from_time:   entry.from_time?.slice(0, 5) || '09:00',
    to_time:     entry.to_time?.slice(0, 5) || '18:00',
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
      const hours = calcHours(form.value.from_time, form.value.to_time)
      await task('Timesheet', 'update', { id: editingId.value, ...form.value, hours })
    } else {
      const rows = multiRows.value.filter(r => r.from_time && r.to_time && r.description?.trim())
      if (!rows.length) { saving.value = false; return }
      for (const r of rows) {
        const hours = calcHours(r.from_time, r.to_time)
        if (hours <= 0) continue
        await task('Timesheet', 'create', {
          work_date: form.value.work_date, hours,
          description: r.description, project: r.project,
          from_time: r.from_time, to_time: r.to_time,
        })
      }
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

      <!-- Status tabs -->
      <div class="flex gap-1 px-4 pb-3 overflow-x-auto no-scrollbar">
        <button @click="changeStatus('')" :class="['tab-chip', !statusTab ? 'tab-chip--active' : '']">All</button>
        <button @click="changeStatus('pending')" :class="['tab-chip', statusTab === 'pending' ? 'tab-chip--active' : '']">Pending</button>
        <button @click="changeStatus('approved')" :class="['tab-chip', statusTab === 'approved' ? 'tab-chip--active' : '']">Approved</button>
        <button @click="changeStatus('rejected')" :class="['tab-chip', statusTab === 'rejected' ? 'tab-chip--active' : '']">Rejected</button>
      </div>
    </div>

    <!-- Summary bar -->
    <div class="px-4 py-2 bg-gray-50 border-b border-gray-100 flex items-center justify-between text-xs text-gray-500">
      <span>{{ totalCount }} entries</span>
      <span class="font-semibold text-gray-700">{{ totalHours.toFixed(1) }} hrs (this page)</span>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-16">
      <div class="w-8 h-8 border-4 border-primary-100 border-t-primary-600 rounded-full animate-spin"></div>
    </div>

    <!-- Empty state -->
    <div v-else-if="!entries.length" class="text-center py-16 px-6">
      <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-gray-100 flex items-center justify-center">
        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      </div>
      <p class="text-gray-500 font-medium">No timesheet entries</p>
      <p class="text-gray-400 text-sm mt-1">Click "+ Log Time" to add an entry</p>
    </div>

    <!-- Entries grouped by date, then by user -->
    <div v-else>
      <div v-for="group in groupedEntries" :key="group.date">
        <!-- Date header -->
        <div class="px-4 py-2 bg-primary-600 text-white flex items-center justify-between">
          <span class="text-sm font-semibold">{{ fmtDateShort(group.date) }}</span>
          <span class="text-sm font-bold">{{ group.hours.toFixed(1) }} hrs</span>
        </div>

        <!-- Desktop table -->
        <div class="hidden sm:block overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-gray-50 border-b border-gray-200">
                <th class="text-left px-4 py-2 font-semibold text-gray-600 w-44">User</th>
                <th class="text-left px-4 py-2 font-semibold text-gray-600">Task Description</th>
                <th class="text-center px-4 py-2 font-semibold text-gray-600 w-20">Time</th>
                <th class="text-center px-4 py-2 font-semibold text-gray-600 w-24">Status</th>
                <th class="text-center px-4 py-2 font-semibold text-gray-600 w-28">Actions</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="ug in userGroupsForDate(group.entries)" :key="ug.user_id">
                <tr class="border-b border-gray-100 hover:bg-gray-50/50">
                  <td class="px-4 py-3 align-top" :rowspan="ug.entries.length">
                    <div class="font-semibold text-gray-800">{{ ug.user_name }}</div>
                    <div class="text-xs text-gray-400 mt-0.5" v-if="ug.minFrom && ug.maxTo">
                      {{ fmtTime12(ug.minFrom) }} - {{ fmtTime12(ug.maxTo) }}
                    </div>
                  </td>
                  <td class="px-4 py-3">
                    <span class="text-xs text-gray-400 mr-2">{{ fmtTime12(ug.entries[0].from_time) }} - {{ fmtTime12(ug.entries[0].to_time) }}</span>
                    <span v-if="ug.entries[0].project" class="text-xs font-medium text-primary-600 mr-1">{{ ug.entries[0].project }} -</span>
                    <span class="text-gray-700">{{ ug.entries[0].description }}</span>
                  </td>
                  <td class="px-4 py-3 text-center font-bold text-gray-700 align-top" :rowspan="ug.entries.length">
                    {{ ug.totalHours.toFixed(2) }}
                  </td>
                  <td class="px-4 py-3 text-center align-top" :rowspan="ug.entries.length">
                    <span :class="['inline-block px-2 py-0.5 rounded text-[11px] font-semibold', statusColor(ug.entries[0].status)]">
                      {{ ug.entries[0].status }}
                    </span>
                  </td>
                  <td class="px-4 py-3 align-top" :rowspan="ug.entries.length">
                    <div class="flex items-center justify-center gap-1">
                      <template v-if="isOwnerAdmin && ug.entries.some(e => e.status === 'pending')">
                        <button @click="ug.entries.filter(e => e.status === 'pending').forEach(e => approveEntry(e))"
                          class="w-7 h-7 rounded-lg bg-green-50 text-green-600 hover:bg-green-100 flex items-center justify-center transition" title="Approve">
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </button>
                        <button @click="ug.entries.filter(e => e.status === 'pending').forEach(e => rejectEntry(e))"
                          class="w-7 h-7 rounded-lg bg-red-50 text-red-500 hover:bg-red-100 flex items-center justify-center transition" title="Reject">
                          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                      </template>
                    </div>
                  </td>
                </tr>
                <tr v-for="e in ug.entries.slice(1)" :key="e.id"
                  class="border-b border-gray-50 hover:bg-gray-50/50">
                  <td class="px-4 py-2">
                    <span class="text-xs text-gray-400 mr-2">{{ fmtTime12(e.from_time) }} - {{ fmtTime12(e.to_time) }}</span>
                    <span v-if="e.project" class="text-xs font-medium text-primary-600 mr-1">{{ e.project }} -</span>
                    <span class="text-gray-700">{{ e.description }}</span>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Mobile card view -->
        <div class="sm:hidden">
          <div v-for="ug in userGroupsForDate(group.entries)" :key="ug.user_id"
            class="border-b border-gray-200">
            <div class="px-4 py-2.5 bg-gray-50 flex items-center justify-between">
              <div>
                <div class="font-semibold text-gray-800 text-sm">{{ ug.user_name }}</div>
                <div class="text-xs text-gray-400" v-if="ug.minFrom && ug.maxTo">
                  {{ fmtTime12(ug.minFrom) }} - {{ fmtTime12(ug.maxTo) }}
                </div>
              </div>
              <div class="text-right">
                <div class="text-base font-bold text-primary-700">{{ ug.totalHours.toFixed(2) }}</div>
                <div class="text-[10px] text-gray-400">hours</div>
              </div>
            </div>
            <div class="divide-y divide-gray-50">
              <div v-for="e in ug.entries" :key="e.id" class="px-4 py-2.5 flex items-start gap-3">
                <div class="flex-1 min-w-0">
                  <div class="text-xs text-gray-400 mb-0.5">
                    {{ fmtTime12(e.from_time) }} - {{ fmtTime12(e.to_time) }}
                    <span class="ml-1 font-medium" :class="statusColor(e.status)" style="padding: 1px 6px; border-radius: 4px; font-size: 10px;">{{ e.status }}</span>
                  </div>
                  <div class="text-sm text-gray-700">
                    <span v-if="e.project" class="font-medium text-primary-600">{{ e.project }} - </span>{{ e.description }}
                  </div>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                  <template v-if="isOwnerAdmin && e.status === 'pending'">
                    <button @click="approveEntry(e)" :disabled="actionLoading === e.id"
                      class="w-7 h-7 rounded-lg bg-green-50 text-green-600 hover:bg-green-100 flex items-center justify-center" title="Approve">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    </button>
                    <button @click="rejectEntry(e)" :disabled="actionLoading === e.id"
                      class="w-7 h-7 rounded-lg bg-red-50 text-red-500 hover:bg-red-100 flex items-center justify-center" title="Reject">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                  </template>
                  <button v-if="(e.status === 'pending' && canStaffEdit(e)) || isOwnerAdmin" @click="openEdit(e)"
                    class="w-7 h-7 rounded-lg bg-gray-50 text-gray-500 hover:bg-gray-100 flex items-center justify-center" title="Edit">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                  </button>
                  <button v-if="(e.status === 'pending' && canStaffEdit(e)) || isOwnerAdmin" @click="deleteEntry(e)"
                    class="w-7 h-7 rounded-lg bg-gray-50 text-gray-400 hover:bg-red-50 hover:text-red-500 flex items-center justify-center" title="Delete">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="px-4 py-3 border-t border-gray-200 flex items-center justify-between bg-white">
        <button @click="goPage(page - 1)" :disabled="page <= 1"
          class="px-3 py-1.5 text-sm font-medium rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition">
          Previous
        </button>
        <div class="flex items-center gap-1">
          <template v-for="p in totalPages" :key="p">
            <button v-if="p === 1 || p === totalPages || (p >= page - 1 && p <= page + 1)"
              @click="goPage(p)"
              :class="['w-8 h-8 rounded-lg text-sm font-medium transition',
                p === page ? 'bg-primary-600 text-white' : 'text-gray-600 hover:bg-gray-100']">
              {{ p }}
            </button>
            <span v-else-if="p === 2 && page > 3" class="text-gray-400 text-xs px-1">...</span>
            <span v-else-if="p === totalPages - 1 && page < totalPages - 2" class="text-gray-400 text-xs px-1">...</span>
          </template>
        </div>
        <button @click="goPage(page + 1)" :disabled="page >= totalPages"
          class="px-3 py-1.5 text-sm font-medium rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition">
          Next
        </button>
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <div v-if="showForm" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/40" @click.self="showForm = false">
      <div class="bg-white rounded-2xl w-full shadow-xl flex flex-col overflow-hidden" :class="editingId ? 'max-w-md' : 'max-w-2xl max-h-[90vh]'">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between shrink-0">
          <h3 class="font-semibold text-gray-800">{{ editingId ? 'Edit Entry' : 'Log Time' }}</h3>
          <button @click="showForm = false" class="text-gray-400 hover:text-gray-600 text-lg">&times;</button>
        </div>

        <!-- Edit single entry -->
        <form v-if="editingId" @submit.prevent="saveEntry" class="p-5 space-y-4">
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Date *</label>
            <input v-model="form.work_date" type="date" required class="inv-input w-full"
              :disabled="!isOwnerAdmin" :min="isOwnerAdmin ? undefined : today()" :max="isOwnerAdmin ? undefined : today()" />
          </div>
          <div class="flex gap-3">
            <div class="flex-1">
              <label class="block text-xs font-medium text-gray-500 mb-1">From *</label>
              <input v-model="form.from_time" type="time" required class="inv-input w-full" />
            </div>
            <div class="flex-1">
              <label class="block text-xs font-medium text-gray-500 mb-1">To *</label>
              <input v-model="form.to_time" type="time" required class="inv-input w-full" />
            </div>
            <div class="w-14 pt-5 text-center">
              <span class="text-sm font-bold text-primary-600">{{ calcHours(form.from_time, form.to_time) }}h</span>
            </div>
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
            {{ saving ? 'Saving...' : 'Update' }}
          </button>
        </form>

        <!-- Add multiple entries -->
        <form v-else @submit.prevent="saveEntry" class="flex flex-col flex-1 overflow-hidden">
          <div class="px-5 pt-4 pb-2 shrink-0">
            <label class="block text-xs font-medium text-gray-500 mb-1">Date *</label>
            <input v-model="form.work_date" type="date" required class="inv-input w-full"
              :disabled="!isOwnerAdmin" :min="isOwnerAdmin ? undefined : today()" :max="isOwnerAdmin ? undefined : today()" />
          </div>
          <div class="px-5 overflow-y-auto flex-1 pb-2 pt-2 space-y-3">
            <div v-for="(row, ri) in multiRows" :key="ri" class="rounded-xl bg-gray-50 p-3 space-y-2.5 relative group">
              <button type="button" @click="removeRow(ri)" v-if="multiRows.length > 1"
                class="absolute top-2 right-2 w-6 h-6 flex items-center justify-center rounded-full text-gray-300 hover:text-red-500 hover:bg-red-50 transition opacity-0 group-hover:opacity-100">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
              </button>
              <div class="grid grid-cols-[auto_1fr_auto_1fr_auto_1fr] items-center gap-x-2 gap-y-1">
                <label class="text-[10px] font-semibold text-gray-400 uppercase">From</label>
                <input v-model="row.from_time" type="time" class="inv-input text-sm"
                  :min="ri > 0 ? multiRows[ri - 1].to_time : undefined" :disabled="ri > 0" />
                <label class="text-[10px] font-semibold text-gray-400 uppercase">To</label>
                <input v-model="row.to_time" type="time" class="inv-input text-sm"
                  :min="row.from_time" @change="onToTimeChange(ri)" />
                <span class="text-xs font-bold text-primary-600 tabular-nums whitespace-nowrap">{{ calcHours(row.from_time, row.to_time) }}h</span>
                <input v-model="row.project" type="text"
                  class="inv-input text-sm" placeholder="Project (optional)" />
              </div>
              <textarea v-model="row.description" rows="2"
                class="inv-input w-full text-sm resize-none" placeholder="What did you work on?"></textarea>
            </div>
          </div>
          <div class="px-5 py-3 border-t border-gray-100 flex items-center gap-3 shrink-0">
            <button type="button" @click="addRow" class="text-xs font-semibold text-primary-600 hover:text-primary-700 transition">+ Add Row</button>
            <div class="flex-1"></div>
            <button type="submit" :disabled="saving" class="btn-primary px-6 py-2">
              {{ saving ? 'Saving...' : 'Save All' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
