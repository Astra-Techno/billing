<script setup>
import { onMounted, computed, defineAsyncComponent, watch, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import Sidebar from './Sidebar.vue'
import TopBar from './TopBar.vue'
import DesktopSidebar from './DesktopSidebar.vue'
import DesktopHeader from './DesktopHeader.vue'
import Toast from '../Toast.vue'
import { useBusinessStore } from '../../stores/business'
import { attachAutoHideScrollbar } from '../../utils/autoHideScrollbar'

const HelpPopup = defineAsyncComponent(() => import('../HelpPopup.vue'))

const bizStore = useBusinessStore()
const route = useRoute()

onMounted(() => {
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
    <TopBar class="lg:hidden safe-area-pt relative z-30" />
    <DesktopHeader />

    <div class="flex-1 flex overflow-hidden relative z-[1]">
      <DesktopSidebar />

      <main 
        class="flex-1 lg:overflow-hidden overflow-y-auto overflow-x-hidden w-full lg:max-w-none pt-0 lg:pb-0 flex flex-col min-h-0 app-main-scroll"
        :class="showNavbar ? 'pb-[calc(4.75rem+env(safe-area-inset-bottom))]' : 'pb-0'"
      >
        <RouterView v-slot="{ Component, route: childRoute }">
          <KeepAlive :max="12">
            <component
              :is="Component"
              :key="childRoute.path.split('/')[1] || 'home'"
              class="flex-1 flex flex-col min-h-0 h-full"
            />
          </KeepAlive>
        </RouterView>
      </main>
    </div>

    <Sidebar />
    <HelpPopup />
    <Toast />
  </div>
</template>
