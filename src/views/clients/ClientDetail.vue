<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { item, list, task } from '../../api'
import { inr } from '../../utils/currency'
import { fmtDateShort } from '../../utils/date'
import { statusBadge, statusLabel } from '../../utils/invoice'

const route  = useRoute()
const router = useRouter()
const client      = ref(null)
const invoices    = ref([])
const contacts    = ref([])
const loading     = ref(true)
const showDelete  = ref(false)
const deleting    = ref(false)

async function load() {
  try {
    const id = route.params.id
    const [cRes, iRes] = await Promise.all([
      item('Client', { id }),
      list('Invoice', { 'filter.client_id': id, sort_by: 'i.created_at', sort_order: 'desc' }),
    ])
    client.value   = cRes.data?.data
    invoices.value = iRes.data?.data || []
  } catch {}
  loading.value = false
}

onMounted(load)

const outstanding = () => invoices.value.filter(i => ['sent','partial','overdue'].includes(i.status))
                                         .reduce((s, i) => s + parseFloat(i.amount_due || 0), 0)

async function deleteClient() {
  deleting.value = true
  try {
    await task('Client', 'delete', { id: route.params.id })
    router.push('/clients')
  } catch {
    deleting.value = false
    showDelete.value = false
  }
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex items-center gap-3">
      <button @click="router.push('/clients')" class="p-2 rounded-lg hover:bg-gray-100">
        <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      </button>
      <h1 class="page-title">{{ client?.name || 'Customer' }}</h1>
      <div class="ml-auto flex gap-2">
        <RouterLink v-if="client" :to="`/clients/${client.id}/edit`" class="btn-outline text-sm px-3 py-1.5">Edit</RouterLink>
        <button v-if="client" @click="showDelete = true" class="btn-outline text-sm px-3 py-1.5 text-danger-600 border-danger-300 hover:bg-danger-50">Delete</button>
      </div>
    </div>

    <div v-if="loading" class="p-10 text-center text-gray-400">Loading…</div>

    <template v-else-if="client">
      <!-- Info cards -->
      <div class="grid sm:grid-cols-3 gap-4">
        <div class="card card-body">
          <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">Outstanding</p>
          <p class="text-2xl font-bold mt-1" :class="outstanding() > 0 ? 'text-danger-600' : 'text-success-700'">
            {{ inr(outstanding()) }}
          </p>
        </div>
        <div class="card card-body">
          <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">Total Bills</p>
          <p class="text-2xl font-bold text-gray-900 mt-1">{{ invoices.length }}</p>
        </div>
        <div class="card card-body">
          <p class="text-xs text-gray-500 uppercase tracking-wide font-medium">Credit Days</p>
          <p class="text-2xl font-bold text-gray-900 mt-1">{{ client.credit_days || 30 }}</p>
        </div>
      </div>

      <div class="grid lg:grid-cols-3 gap-5">
        <!-- Left: details -->
        <div class="space-y-4">
          <div class="card card-body space-y-3">
            <h2 class="section-title">Details</h2>
            <div v-if="client.mobile" class="flex items-center gap-2 text-sm text-gray-700">
              <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
              {{ client.mobile }}
            </div>
            <div v-if="client.email" class="flex items-center gap-2 text-sm text-gray-700">
              <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
              {{ client.email }}
            </div>
            <div v-if="client.gstin" class="text-sm">
              <p class="text-xs text-gray-400">GST Number</p>
              <p class="font-mono font-medium text-gray-700">{{ client.gstin }}</p>
            </div>
            <div v-if="client.city" class="text-sm text-gray-600">
              📍 {{ [client.city, client.pincode].filter(Boolean).join(' - ') }}
            </div>
          </div>

          <RouterLink :to="`/invoices/new?client_id=${client.id}`" class="btn-primary w-full justify-center">
            + Create Bill
          </RouterLink>
        </div>

        <!-- Right: invoices -->
        <div class="lg:col-span-2 card">
          <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="section-title mb-0">Bill History</h2>
          </div>
          <div v-if="!invoices.length" class="p-8 text-center text-gray-400 text-sm">No bills yet</div>
          <div v-else class="divide-y divide-gray-100">
            <div v-for="inv in invoices" :key="inv.id"
              class="flex items-center justify-between px-5 py-3.5 hover:bg-blue-50/40 cursor-pointer transition-colors"
              @click="router.push(`/invoices/${inv.id}`)">
              <div>
                <p class="text-sm font-medium text-gray-800">{{ inv.number }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ fmtDateShort(inv.issue_date) }}</p>
              </div>
              <div class="text-right">
                <p class="text-sm font-semibold text-gray-900">{{ inr(inv.total) }}</p>
                <span :class="statusBadge(inv.status)">{{ statusLabel(inv.status) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- Delete confirmation modal -->
    <div v-if="showDelete" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="bg-white rounded-xl shadow-lg max-w-sm w-full p-6 space-y-4">
        <h3 class="text-lg font-semibold text-gray-900">Delete Customer?</h3>
        <p class="text-sm text-gray-600">This will permanently delete <strong>{{ client?.name }}</strong> and cannot be undone.</p>
        <div class="flex gap-3 pt-2">
          <button @click="showDelete = false" class="btn-outline flex-1" :disabled="deleting">Cancel</button>
          <button @click="deleteClient" class="btn-primary flex-1 bg-danger-600 hover:bg-danger-700 border-danger-600" :disabled="deleting">
            {{ deleting ? 'Deleting…' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
