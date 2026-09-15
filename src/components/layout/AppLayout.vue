<script setup>
import { onMounted, onUnmounted, ref, computed, defineAsyncComponent, watch, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import Sidebar from './Sidebar.vue'
import TopBar from './TopBar.vue'
import DesktopSidebar from './DesktopSidebar.vue'
import DesktopHeader from './DesktopHeader.vue'
import Toast from '../Toast.vue'
import { useBusinessStore } from '../../stores/business'
import { attachAutoHideScrollbar } from '../../utils/autoHideScrollbar'
import { task } from '../../api'
import { useAuthStore } from '../../stores/auth'

const HelpPopup = defineAsyncComponent(() => import('../HelpPopup.vue'))

const bizStore = useBusinessStore()
const route = useRoute()
const desktopMode = !!window.__BILLING_DESKTOP__
const backupWarning = ref('')
let backupTimer
async function checkBackup() {
  if (!['owner', 'admin'].includes(useAuthStore().role)) return
  try {
    const { data } = await task('Desktop', 'status')
    backupWarning.value = data.data.warning || ''
  } catch { backupWarning.value = 'Backup check failed. Open Backup & Restore.' }
}
onUnmounted(() => clearInterval(backupTimer))

onMounted(() => {
  if (desktopMode) {
    checkBackup(); backupTimer = setInterval(checkBackup, 60000)
    const last = Number(localStorage.getItem('desktop_license_refresh') || 0)
    if (Date.now() - last > 86400000) task('DesktopLicense', 'localRefresh').then(({ data }) => {
      localStorage.setItem('desktop_license_refresh', String(Date.now()))
      if (!data.data?.active) location.assign('/activation')
    }).catch(() => {})
  }
  bizStore.ensureLoaded()

  if (localStorage.getItem('darkMode') === 'true') {
    document.documentElement.classList.add('dark')
  }

  attachAutoHideScrollbar()
})

watch(() => route.fullPath, () => nextTick(() => attachAutoHideScrollbar()), { immediate: true })

const showNavbar = computed(() => {
  const regular = ['Dashboard', 'Invoices', 'Quotes', 'Expenses', 'Products', 'CreditNotes', 'PurchaseOrders', 'DeliveryChallans', 'GstReturns', 'Reports', 'Settings', 'Help', 'More', 'Clients', 'Payroll', 'StaffNew', 'StaffEdit', 'PayrollRun'].includes(route.name)
  const admin = route.meta.superAdmin && ['AdminDashboard', 'AdminBusinesses', 'AdminUsers'].includes(route.name)
  return regular || admin
})
</script>

<template>
  <div class="app-shell">
    <div v-if="desktopMode" class="flex items-center justify-between bg-blue-50 dark:bg-slate-800 px-4 py-2 text-sm shrink-0">
      <span>Offline edition · Data saved on this PC</span>
      <RouterLink to="/offline-backups" class="font-semibold text-blue-600">Backup &amp; Restore</RouterLink>
    </div>
    <p v-if="backupWarning" role="alert" class="bg-amber-50 text-amber-800 px-4 py-2 shrink-0">{{ backupWarning }}</p>
    <TopBar class="lg:hidden safe-area-pt relative z-30" />
    <DesktopHeader />

    <div class="flex-1 flex overflow-hidden relative z-[1]">
      <DesktopSidebar />

      <main 
        class="flex-1 lg:overflow-hidden overflow-y-auto overflow-x-hidden w-full lg:max-w-none pt-0 lg:pb-0 flex flex-col min-h-0 app-main-scroll"
        :class="showNavbar ? 'pb-[calc(4.75rem+env(safe-area-inset-bottom))]' : 'pb-0'"
      >
        <RouterView v-slot="{ Component, route: childRoute }">
            <component
              v-if="Component"
              :is="Component"
              :key="childRoute.matched[1]?.name || 'home'"
              class="flex-1 flex flex-col min-h-0 h-full"
            />
        </RouterView>
      </main>
    </div>

    <Sidebar />
    <HelpPopup />
    <Toast />
  </div>
</template>
