import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useUiStore = defineStore('ui', () => {
  const sidebarOpen = ref(false)
  const loading     = ref(false)

  const toggleSidebar = ()    => sidebarOpen.value = !sidebarOpen.value
  const closeSidebar  = ()    => sidebarOpen.value = false
  const setLoading    = (val) => loading.value = val

  return { sidebarOpen, loading, toggleSidebar, closeSidebar, setLoading }
})
