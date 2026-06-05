import { onMounted, onUnmounted, nextTick } from 'vue'

/**
 * Keyboard UX for forms:
 * - Ctrl+Enter → submit
 * - Auto-focus first input on mount
 * - Tab from last line-item field → auto-add new row (optional)
 *
 * Usage:
 *   useFormKeys({ formId: 'invoice-form', autoFocus: true })
 *   useFormKeys({ formId: 'client-form', autoFocus: '.first-focus' })
 */
export function useFormKeys(options = {}) {
  const { formId, autoFocus = true } = options

  function onKeydown(e) {
    // Ctrl+Enter or Cmd+Enter → submit form
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
      e.preventDefault()
      const form = formId
        ? document.getElementById(formId)
        : document.querySelector('form')
      if (form) form.requestSubmit()
    }

    // Escape → close any open modal (blur active element)
    if (e.key === 'Escape') {
      const modal = document.querySelector('[class*="fixed inset-0"]')
      if (modal) {
        const closeBtn = modal.querySelector('button')
        if (closeBtn) closeBtn.click()
      }
    }
  }

  onMounted(() => {
    document.addEventListener('keydown', onKeydown)

    // Auto-focus first input
    if (autoFocus) {
      nextTick(() => {
        setTimeout(() => {
          const selector = typeof autoFocus === 'string'
            ? autoFocus
            : formId
              ? `#${formId} input:not([disabled]):not([type=hidden]):not([type=date]), #${formId} select:not([disabled]), #${formId} textarea:not([disabled])`
              : 'form input:not([disabled]):not([type=hidden]):not([type=date]), form select:not([disabled])'
          const el = document.querySelector(selector)
          if (el) el.focus()
        }, 100)
      })
    }
  })

  onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown)
  })
}

/**
 * Handle Tab on last field of last line item → auto-add new row.
 * Attach to the last editable field in each row:
 *   @keydown.tab="onLastFieldTab(i, $event)"
 *
 * @param {number} index - current item index
 * @param {Event} event - keydown event
 * @param {Array} items - reactive items array
 * @param {Function} addItem - function to add a new blank item
 * @param {string} firstFieldSelector - CSS selector for the first input in a row
 */
export function handleLineItemTab(index, event, items, addItem, firstFieldSelector = '.line-item-first') {
  // Only on forward tab (not shift+tab)
  if (event.shiftKey) return

  const isLastRow = index === items.length - 1
  if (isLastRow) {
    event.preventDefault()
    addItem()
    // Focus the first field of the new row after DOM update
    nextTick(() => {
      setTimeout(() => {
        const rows = document.querySelectorAll(firstFieldSelector)
        const lastRow = rows[rows.length - 1]
        if (lastRow) lastRow.focus()
      }, 50)
    })
  }
}
