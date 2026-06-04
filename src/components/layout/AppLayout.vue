<script setup>
import { onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import Sidebar from './Sidebar.vue'
import TopBar from './TopBar.vue'
import DesktopSidebar from './DesktopSidebar.vue'
import DesktopHeader from './DesktopHeader.vue'
import HelpPopup from '../HelpPopup.vue'
import Toast from '../Toast.vue'
import { useBusinessStore } from '../../stores/business'

const bizStore = useBusinessStore()
const route = useRoute()

onMounted(() => { bizStore.fetchBusiness(); bizStore.loadFeatures() })

const showNavbar = computed(() => {
  return ['Dashboard', 'Invoices', 'Quotes', 'Expenses', 'Products', 'CreditNotes', 'PurchaseOrders', 'DeliveryChallans', 'GstReturns', 'Reports', 'Settings', 'Help', 'More', 'Clients'].includes(route.name)
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
        <RouterView v-slot="{ Component, route }">
          <Transition name="page-fade" mode="out-in">
            <div :key="route.path.split('/')[1]" class="flex-1 flex flex-col min-h-0 h-full">
              <component :is="Component" />
            </div>
          </Transition>
        </RouterView>
      </main>
    </div>

    <Sidebar />
    <HelpPopup />
    <Toast />
  </div>
</template>
