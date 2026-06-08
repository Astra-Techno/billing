import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { item, task } from '../api/index'

// Default features — core items always on, advanced off
const DEFAULT_FEATURES = {
  quotes: true,
  expenses: true,
  purchase_orders: false,
  delivery_challans: false,
  credit_notes: false,
  gst_returns: true,
  reports: true,
  payroll: false,
}

export const useBusinessStore = defineStore('business', () => {
  const logo     = ref('')
  const stateId  = ref(null)
  const features = ref({ ...DEFAULT_FEATURES })

  function setLogo(url) {
    logo.value = url || ''
  }

  function setStateId(id) {
    stateId.value = id ? parseInt(id) : null
  }

  function setFeatures(obj) {
    features.value = { ...DEFAULT_FEATURES, ...obj }
  }

  function isEnabled(key) {
    return features.value[key] !== false
  }

  async function fetchBusiness() {
    try {
      const res = await item('Business')
      logo.value = res.data?.data?.logo || ''
      if (res.data?.data?.state_id) stateId.value = parseInt(res.data.data.state_id)
    } catch {}
  }

  async function loadFeatures() {
    try {
      const res = await task('Settings', 'get', {})
      const settings = res.data?.data || {}
      if (settings.features) {
        try {
          const parsed = JSON.parse(settings.features)
          features.value = { ...DEFAULT_FEATURES, ...parsed }
        } catch {}
      }
    } catch {}
  }

  async function saveFeatures() {
    try {
      await task('Settings', 'save', { settings: { features: JSON.stringify(features.value) } })
    } catch {}
  }

  return { logo, stateId, features, setLogo, setStateId, setFeatures, isEnabled, fetchBusiness, loadFeatures, saveFeatures }
})
