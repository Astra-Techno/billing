import { ref, watch, onMounted, onActivated, onUnmounted, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { list as apiList } from '../api'

/**
 * Paginated list composable with infinite scroll and server-side search.
 *
 * Usage:
 *   const { items, loading, loadingMore, total, search, onSearch, loadMore, reload } =
 *     usePagedList('Product', {
 *       limit: 50,
 *       params: () => ({ 'filter.active': 1 }),
 *       listRouteName: 'Products',
 *       scrollContainer: '#product-scroll',
 *     })
 */
export function usePagedList(entity, options = {}) {
  const {
    limit = 50,
    params = null,
    listRouteName = '',
    scrollContainer = null,
    debounceMs = 350,
  } = options

  const route = useRoute()

  const items       = ref([])
  const loading     = ref(true)
  const loadingMore = ref(false)
  const total       = ref(0)
  const page        = ref(1)
  const hasMore     = ref(false)
  const search      = ref('')

  let debounceTimer = null
  let scrollEl = null

  async function load(resetPage = true) {
    if (resetPage) {
      page.value = 1
      loading.value = true
    } else {
      loadingMore.value = true
    }

    try {
      const p = { limit, page: page.value }
      if (search.value) p['filter.search'] = `%${search.value}%`
      if (params) Object.assign(p, typeof params === 'function' ? params() : params)

      const res = await apiList(entity, p)
      const newData = res.data?.data || []
      total.value = res.data?.total ?? newData.length

      if (resetPage) {
        items.value = newData
      } else {
        items.value = [...items.value, ...newData]
      }
      hasMore.value = items.value.length < total.value
    } catch {
      if (resetPage) items.value = []
    } finally {
      loading.value = false
      loadingMore.value = false
    }
  }

  function loadMore() {
    if (loadingMore.value || !hasMore.value) return
    page.value++
    load(false)
  }

  function onSearch(q) {
    search.value = q
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => load(true), debounceMs)
  }

  function reload() {
    return load(true)
  }

  // Infinite scroll handler
  function onScroll(e) {
    const el = e.target
    if (el.scrollTop + el.clientHeight >= el.scrollHeight - 100) {
      loadMore()
    }
  }

  function attachScroll() {
    nextTick(() => {
      scrollEl = scrollContainer
        ? document.querySelector(scrollContainer)
        : null
      if (scrollEl) scrollEl.addEventListener('scroll', onScroll, { passive: true })
    })
  }

  function detachScroll() {
    if (scrollEl) scrollEl.removeEventListener('scroll', onScroll)
    scrollEl = null
  }

  onMounted(() => {
    load(true)
    attachScroll()
  })

  onActivated(() => {
    load(true)
    attachScroll()
  })

  onUnmounted(() => {
    detachScroll()
    clearTimeout(debounceTimer)
  })

  if (listRouteName) {
    watch(() => route.name, name => {
      if (name === listRouteName) load(true)
    })
  }

  return { items, loading, loadingMore, total, hasMore, search, page, onSearch, loadMore, reload }
}
