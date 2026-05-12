import { reactive } from 'vue'

const toasts = reactive([])
let nextId = 1

export function useToast() {
  function show(message, type = 'success', duration = 3500) {
    const id = nextId++
    toasts.push({ id, message, type })
    setTimeout(() => dismiss(id), duration)
  }

  function dismiss(id) {
    const idx = toasts.findIndex(t => t.id === id)
    if (idx !== -1) toasts.splice(idx, 1)
  }

  return {
    toasts,
    success: (msg, duration) => show(msg, 'success', duration),
    error:   (msg, duration) => show(msg, 'error', duration ?? 5000),
    info:    (msg, duration) => show(msg, 'info', duration),
    dismiss,
  }
}
