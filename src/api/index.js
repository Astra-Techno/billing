import axios from 'axios'
import { useAuthStore } from '../stores/auth'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost/billing/api',
  headers: { 'Content-Type': 'application/json' },
})

// Attach token + business_id to every request
api.interceptors.request.use(config => {
  const auth = useAuthStore()
  if (auth.token)      config.headers['Authorization']  = `Bearer ${auth.token}`
  if (auth.businessId) config.headers['X-Business-ID']  = auth.businessId
  return config
})

// Handle responses globally
api.interceptors.response.use(
  res => res,
  err => {
    if (err.response?.status === 401) {
      const auth = useAuthStore()
      auth.logout()
      window.location.href = '/login'
    }
    // Log all API errors to console for debugging
    console.error(
      `[API] ${err.config?.method?.toUpperCase()} ${err.config?.url}`,
      err.response?.status,
      err.response?.data ?? err.message
    )
    return Promise.reject(err)
  }
)

export default api

// ── Convenience helpers ────────────────────────────────────────────────────────

export const task  = (name, method, data = {}) => api.post(`task/${name}/${method}`, data)
export const list  = (name, params = {})        => api.get(`list/${name}`,  { params })
export const item  = (name, params = {})        => api.get(`item/${name}`,  { params })
export const all   = (name, params = {})        => api.get(`all/${name}`,   { params })
export const count = (name, params = {})        => api.get(`count/${name}`, { params })
