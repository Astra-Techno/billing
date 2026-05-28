import { defineStore } from 'pinia'
import { ref } from 'vue'
import { item } from '../api/index'

export const useBusinessStore = defineStore('business', () => {
  const logo    = ref('')
  const stateId = ref(null)

  function setLogo(url) {
    logo.value = url || ''
  }

  function setStateId(id) {
    stateId.value = id ? parseInt(id) : null
  }

  async function fetchLogo() {
    try {
      const res = await item('Business')
      logo.value = res.data?.data?.logo || ''
    } catch {}
  }

  return { logo, stateId, setLogo, setStateId, fetchLogo }
})
