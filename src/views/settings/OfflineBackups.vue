<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import api, { task } from '../../api'
import { useAuthStore } from '../../stores/auth'

const backups = ref([]), busy = ref(false), error = ref(''), message = ref('')
const loading = ref(true)
const selectedFile = ref(null), confirmation = ref('')
let timer
async function load() {
  try {
    const { data } = await task('Desktop', 'status')
    backups.value = data.data.backups; error.value = data.data.warning || ''
  } catch (e) { error.value = e.response?.data?.message || 'Cannot reach the local backup service.' }
  finally { loading.value = false }
}
async function download(name) {
  const response = await api.post('task/Desktop/download', { name }, { responseType: 'blob' })
  const url = URL.createObjectURL(response.data)
  const link = document.createElement('a'); link.href = url; link.download = name; link.click()
  setTimeout(() => URL.revokeObjectURL(url), 10000)
}
async function create() {
  busy.value = true; error.value = ''; message.value = ''
  try {
    const { data } = await task('Desktop', 'backup')
    await load(); message.value = 'Backup saved on this PC. Download a copy to your USB drive.'
    await download(data.data.name)
  } catch (e) { error.value = e.response?.data?.message || 'Backup failed. Check disk space and try again.' }
  finally { busy.value = false }
}
async function restore() {
  if (!selectedFile.value || confirmation.value !== 'RESTORE') return
  busy.value = true; error.value = ''; message.value = ''
  const body = new FormData(); body.append('backup', selectedFile.value); body.append('confirmation', confirmation.value)
  try {
    await api.post('task/Desktop/restore', body, { headers: { 'Content-Type': 'multipart/form-data' }, timeout: 600000 })
    useAuthStore().logout(); window.location.replace('/login?restored=1')
  } catch (e) { error.value = e.response?.data?.message || 'Restore failed. Your previous data remains available.' }
  finally { busy.value = false }
}
onMounted(() => { load(); timer = setInterval(load, 60000) })
onUnmounted(() => clearInterval(timer))
</script>

<template>
  <div class="p-6 overflow-y-auto space-y-6">
    <h1 class="text-2xl font-bold">Backup &amp; Restore</h1>
    <p>Your invoices, customers, payments, settings and uploaded images are included. Keep a copy on a USB drive so you can recover if this PC fails.</p>
    <p v-if="error" role="alert" class="text-red-600">{{ error }}</p>
    <p v-if="message" role="status" class="text-green-600">{{ message }}</p>
    <section class="card p-5 space-y-3">
      <h2 class="text-lg font-semibold">Back up this PC</h2>
      <p>A backup is created when the app starts each day and checked while the app is open. Existing backups are retained.</p>
      <button class="btn-primary" :disabled="busy" @click="create">{{ busy ? 'Please wait…' : 'Create & download backup' }}</button>
      <p v-if="loading">Loading backups…</p>
      <p v-else-if="!backups.length">No backups yet.</p>
      <ul class="space-y-2">
        <li v-for="backup in backups" :key="backup.name" class="flex justify-between gap-4 flex-wrap">
          <span>{{ backup.name }} · {{ (backup.size / 1024 / 1024).toFixed(1) }} MB</span>
          <button class="text-blue-600 font-semibold" :disabled="busy" @click="download(backup.name).catch(() => error = 'Download failed. Try again.')">Download</button>
        </li>
      </ul>
    </section>
    <section class="card p-5 space-y-3">
      <h2 class="text-lg font-semibold">Restore a backup</h2>
      <p>Restoring replaces the active data on this PC. A safety backup is created first. After restoring, sign in with the account and password saved in the backup.</p>
      <label class="block">Backup file (.aibackup)
        <input type="file" accept=".aibackup" :disabled="busy" class="block mt-2" @change="selectedFile = $event.target.files[0]" />
      </label>
      <label class="block">Type RESTORE to confirm
        <input v-model="confirmation" :disabled="busy" class="input block mt-2 max-w-xs" autocomplete="off" />
      </label>
      <button class="btn-primary" :disabled="busy || !selectedFile || confirmation !== 'RESTORE'" @click="restore">Restore selected backup</button>
      <p v-if="busy">Keep AI Billing open until this operation finishes.</p>
    </section>
  </div>
</template>
