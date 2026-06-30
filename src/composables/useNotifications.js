import { ref, computed } from 'vue'
import { list } from '../api'
import { inrCompact } from '../utils/currency'

const items   = ref([])
const loading = ref(false)
const loaded  = ref(false)
let loadedAt  = 0
const TTL     = 5 * 60_000

function daysSince(dateStr) {
  return Math.floor((Date.now() - new Date(dateStr)) / 86_400_000)
}

export function useNotifications() {
  async function load(force = false) {
    if (loading.value) return
    if (!force && loaded.value && Date.now() - loadedAt < TTL) return
    loading.value = true
    try {
      const cutoff = new Date()
      cutoff.setDate(cutoff.getDate() - 3)
      const cutoffStr = cutoff.toISOString().split('T')[0]

      const [overdueRes, draftRes] = await Promise.all([
        list('Invoice:overdue', { limit: 20 }),
        list('Invoice', {
          'filter.status':  'draft',
          'filter.to_date': cutoffStr,
          sort_by:          'i.created_at',
          sort_order:       'asc',
          limit:            10,
        }),
      ])

      const notifs = []

      for (const inv of (overdueRes.data?.data || [])) {
        const days = daysSince(inv.due_date)
        notifs.push({
          id:    `overdue-${inv.id}`,
          type:  'overdue',
          title: inv.client_name,
          body:  `${inv.number} · ₹${inrCompact(inv.amount_due)} overdue${days > 0 ? ` (${days}d)` : ''}`,
          link:  `/invoices/${inv.id}`,
          date:  inv.due_date,
        })
      }

      for (const inv of (draftRes.data?.data || [])) {
        const days = daysSince(inv.created_at)
        notifs.push({
          id:    `draft-${inv.id}`,
          type:  'draft',
          title: inv.client_name,
          body:  `${inv.number} · draft not sent (${days}d old)`,
          link:  `/invoices/${inv.id}`,
          date:  inv.created_at,
        })
      }

      items.value  = notifs
      loaded.value = true
      loadedAt     = Date.now()
    } catch {}
    loading.value = false
  }

  function refresh() {
    return load(true)
  }

  const count = computed(() => items.value.length)

  return { notifications: items, count, loading, loaded, load, refresh }
}
