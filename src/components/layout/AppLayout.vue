<script setup>
import { onMounted } from 'vue'
import Sidebar from './Sidebar.vue'
import TopBar from './TopBar.vue'
import DesktopSidebar from './DesktopSidebar.vue'
import DesktopHeader from './DesktopHeader.vue'
import HelpPopup from '../HelpPopup.vue'
import Toast from '../Toast.vue'
import { useBusinessStore } from '../../stores/business'

const bizStore = useBusinessStore()
onMounted(() => bizStore.fetchLogo())
</script>

<template>
  <div class="h-[100dvh] w-full max-w-[100vw] flex flex-col bg-[#FAFAFA] overflow-hidden relative font-sans">
    
    <!-- Mobile Header -->
    <TopBar class="lg:hidden" />
    
    <!-- Desktop Header -->
    <DesktopHeader />

    <div class="flex-1 flex overflow-hidden">
      <!-- Desktop Sidebar -->
      <DesktopSidebar />
      
      <!-- Main Content Container -->
      <main class="flex-1 lg:overflow-hidden overflow-y-auto overflow-x-hidden max-w-2xl mx-auto lg:max-w-none w-full px-4 lg:px-0 pt-5 lg:pt-0 pb-24 lg:pb-0 flex flex-col min-h-0 app-main-scroll">
        <RouterView v-slot="{ Component, route }">
          <Transition name="page-fade" mode="out-in">
            <component :is="Component" :key="route.path.split('/')[1]" />
          </Transition>
        </RouterView>
      </main>
    </div>

    <!-- Mobile Bottom Nav -->
    <Sidebar />
    
    <HelpPopup />
    <Toast />
  </div>
</template>
