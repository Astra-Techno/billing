import { onActivated, watch } from 'vue'
import { useRoute } from 'vue-router'

/**
 * Refetch when a KeepAlive-cached list page is shown again.
 * @param {Function} load
 * @param {{ listRouteName?: string }} opts - when set, also reload when returning to the list route from a child (split pane)
 */
export function useListRefresh(load, { listRouteName } = {}) {
  const route = listRouteName ? useRoute() : null

  onActivated(() => load())

  if (listRouteName && route) {
    watch(() => route.name, name => {
      if (name === listRouteName) load()
    })
  }
}
