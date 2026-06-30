import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api, { invalidateApiCache } from '../api'

export const useAuthStore = defineStore('auth', () => {
  const token      = ref(localStorage.getItem('token') || null)
  const user       = ref(JSON.parse(localStorage.getItem('user') || 'null'))
  const businessId = ref(localStorage.getItem('business_id') || null)   // never stores 'null' string — see setSession
  const businesses = ref(JSON.parse(localStorage.getItem('businesses') || '[]'))

  const isSuperAdmin  = computed(() => !!(user.value?.is_super_admin))
  const isLoggedIn = computed(() => {
    if (!token.value) return false
    if (isSuperAdmin.value) return true
    if (businessId.value) return true
    // Logged in with token but business not scoped yet (multi-business edge case)
    return (businesses.value?.length ?? 0) > 0
  })

  // Role of current user in the active business
  const role = computed(() => {
    const biz = businesses.value?.find(b => String(b.id) === String(businessId.value))
    return biz?.role || 'staff'
  })

  function setSession(data) {
    token.value      = data.token
    user.value       = data.user
    businessId.value = data.business_id
    businesses.value = data.businesses || []

    localStorage.setItem('token',       data.token)
    localStorage.setItem('user',        JSON.stringify(data.user))
    localStorage.setItem('business_id', data.business_id ?? '')   // never write literal 'null'
    localStorage.setItem('businesses',  JSON.stringify(data.businesses || []))
  }

  function logout() {
    token.value      = null
    user.value       = null
    businessId.value = null
    businesses.value = []
    localStorage.clear()
    invalidateApiCache()
  }

  async function login(email, password) {
    const { data } = await api.post('login', { email, password })
    const session = data.data

    // API leaves business_id null when user has multiple businesses — pick first & scope token
    if (!session.business_id && session.businesses?.length > 0) {
      setSession({ ...session, business_id: session.businesses[0].id })
      try {
        await switchBusiness(session.businesses[0].id)
      } catch {
        // Token may already work; keep first business selected in storage
      }
      return data
    }

    setSession(session)
    return data
  }

  async function register(payload) {
    const { data } = await api.post('register', payload)
    setSession(data.data)
    return data
  }

  async function switchBusiness(id) {
    const { data } = await api.post('switch-business', { business_id: id })
    token.value      = data.data.token
    businessId.value = data.data.business_id
    localStorage.setItem('token',       data.data.token)
    localStorage.setItem('business_id', data.data.business_id)
    return data
  }

  return { token, user, businessId, businesses, role, isLoggedIn, isSuperAdmin, setSession, logout, login, register, switchBusiness }
})
