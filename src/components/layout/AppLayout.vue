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
  <div class="h-screen w-screen flex flex-col bg-slate-50 overflow-hidden">
    <TopBar />
    <main class="flex-1 overflow-y-auto overflow-x-hidden max-w-2xl mx-auto lg:max-w-none w-full px-4 lg:px-8 pt-5 pb-24">
      <RouterView />
    </main>
    <Sidebar />
    <HelpPopup />
  </div>
</template>
