import { defineStore } from 'pinia'
import { ref } from 'vue'
import { item } from '../api/index'

export const useBusinessStore = defineStore('business', () => {
  const logo = ref('')

  function setLogo(url) {
    logo.value = url || ''
  }

  async function fetchLogo() {
    try {
      const res = await item('Business')
      logo.value = res.data?.data?.logo || ''
    } catch {}
  }

  return { logo, setLogo, fetchLogo }
})
