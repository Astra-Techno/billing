import axios from 'axios'
import { useAuthStore } from '../stores/auth'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost/billing/api',
  headers: {
    'Content-Type': 'application/json',
    'Cache-Control': 'no-cache, no-store, must-revalidate',
    Pragma: 'no-cache',
  },
})

/** @deprecated No-op — in-memory API cache removed. */
export function invalidateApiCache() {}

api.interceptors.request.use(config => {
  const auth = useAuthStore()
  if (auth.token)        config.headers['Authorization'] = `Bearer ${auth.token}`
  if (auth.businessId)   config.headers['X-Business-ID'] = auth.businessId
  if ((config.method || 'get').toLowerCase() === 'get') {
    config.params = { ...config.params, _t: Date.now() }
  }
  return config
})

api.interceptors.response.use(
  res => res,
  err => {
    if (err.response?.status === 401) {
      const auth = useAuthStore()
      const hadToken = !!auth.token
      auth.logout()
      if (hadToken) window.location.href = '/login'
    }
    return Promise.reject(err)
  }
)

export default api

export const task = (name, method, data = {}) =>
  api.post(`task/${name}/${method}`, data)

export const list = (name, params = {}) =>
  api.get(`list/${name}`, { params })

export const item = (name, params = {}) =>
  api.get(`item/${name}`, { params })

export const all = (name, params = {}) =>
  api.get(`all/${name}`, { params })

export const count = (name, params = {}) =>
  api.get(`count/${name}`, { params })
