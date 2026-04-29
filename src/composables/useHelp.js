import { ref } from 'vue'

const section = ref(null)

export function useHelp() {
  return {
    helpSection: section,
    openHelp:  (s) => { section.value = s },
    closeHelp: ()  => { section.value = null },
  }
}
