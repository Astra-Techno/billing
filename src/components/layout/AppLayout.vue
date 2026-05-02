<script setup>
import { onMounted } from 'vue'
import Sidebar from './Sidebar.vue'
import TopBar from './TopBar.vue'
import HelpPopup from '../HelpPopup.vue'
import { useBusinessStore } from '../../stores/business'

const bizStore = useBusinessStore()
onMounted(() => bizStore.fetchLogo())
</script>

<template>
  <div class="h-[100dvh] w-full max-w-[100vw] flex flex-col bg-slate-50 overflow-hidden relative">
    <TopBar />
    <main class="flex-1 lg:overflow-hidden overflow-y-auto overflow-x-hidden max-w-2xl mx-auto lg:max-w-none w-full px-4 lg:px-8 pt-5 pb-24 lg:pb-6 flex flex-col min-h-0 app-main-scroll">
      <RouterView />
    </main>
    <Sidebar />
    <HelpPopup />
  </div>
</template>
