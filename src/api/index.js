import axios from 'axios'
import { useAuthStore } from '../stores/auth'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost/billing/api',
  headers: { 'Content-Type': 'application/json' },
})

const _inflight = new Map()
const _cache = new Map()
const DEFAULT_TTL = 45_000

function cacheKey(kind, name, params = {}) {
  const sorted = Object.keys(params).sort().reduce((o, k) => { o[k] = params[k]; return o }, {})
  return `${kind}:${name}:${JSON.stringify(sorted)}`
}

function cachedGet(kind, name, params, fetcher, { ttl = DEFAULT_TTL, bust = false } = {}) {
  const key = cacheKey(kind, name, params)
  if (!bust) {
    const hit = _cache.get(key)
    if (hit && Date.now() - hit.t < ttl) return Promise.resolve(hit.data)
  } else {
    _cache.delete(key)
  }
  if (_inflight.has(key)) return _inflight.get(key)
  const p = fetcher().then(res => {
    _cache.set(key, { data: res, t: Date.now() })
    _inflight.delete(key)
    return res
  }).catch(err => {
    _inflight.delete(key)
    return Promise.reject(err)
  })
  _inflight.set(key, p)
  return p
}

export function invalidateApiCache(prefix = '') {
  for (const key of _cache.keys()) {
    if (!prefix || key.startsWith(prefix)) _cache.delete(key)
  }
}

api.interceptors.request.use(config => {
  const auth = useAuthStore()
  if (auth.token)      config.headers['Authorization'] = `Bearer ${auth.token}`
  if (auth.businessId) config.headers['X-Business-ID'] = auth.businessId
  return config
})

api.interceptors.response.use(
  res => res,
  err => {
    if (err.response?.status === 401) {
      const auth = useAuthStore()
      const hadToken = !!auth.token
      auth.logout()
      invalidateApiCache()
      if (hadToken) window.location.href = '/login'
    }
    return Promise.reject(err)
  }
)

export default api

export const task = async (name, method, data = {}) => {
  const res = await api.post(`task/${name}/${method}`, data)
  if (method !== 'get') invalidateApiCache()
  return res
}

export const list = (name, params = {}, opts = {}) =>
  cachedGet('list', name, params, () => api.get(`list/${name}`, { params }), opts)

export const item = (name, params = {}, opts = {}) =>
  cachedGet('item', name, params, () => api.get(`item/${name}`, { params }), opts)

export const all = (name, params = {}, opts = {}) =>
  cachedGet('all', name, params, () => api.get(`all/${name}`, { params }), { ttl: 120_000, ...opts })

export const count = (name, params = {}, opts = {}) =>
  cachedGet('count', name, params, () => api.get(`count/${name}`, { params }), opts)
