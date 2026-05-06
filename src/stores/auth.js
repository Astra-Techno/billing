import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '../api'

export const useAuthStore = defineStore('auth', () => {
  const token      = ref(localStorage.getItem('token') || null)
  const user       = ref(JSON.parse(localStorage.getItem('user') || 'null'))
  const businessId = ref(localStorage.getItem('business_id') || null)
  const businesses = ref(JSON.parse(localStorage.getItem('businesses') || '[]'))

  const isLoggedIn = computed(() => !!token.value && !!businessId.value)

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
    localStorage.setItem('business_id', data.business_id)
    localStorage.setItem('businesses',  JSON.stringify(data.businesses || []))
  }

  function logout() {
    token.value      = null
    user.value       = null
    businessId.value = null
    businesses.value = []
    localStorage.clear()
  }

  async function login(email, password) {
    const { data } = await api.post('login', { email, password })
    setSession(data.data)
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

  return { token, user, businessId, businesses, role, isLoggedIn, setSession, logout, login, register, switchBusiness }
})
