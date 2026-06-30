import { onActivated, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'

/**
 * Refetch list data when the page is shown (mount, KeepAlive re-activate, or split-pane list route).
 * @param {Function} load
 * @param {{ listRouteName?: string }} opts
 */
export function useListRefresh(load, { listRouteName } = {}) {
  const route = useRoute()

  onMounted(() => load())
  onActivated(() => load())

  if (listRouteName) {
    watch(() => route.name, name => {
      if (name === listRouteName) load()
    })
  }
}
