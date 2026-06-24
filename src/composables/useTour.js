import { ref, onUnmounted, nextTick } from 'vue'

/**
 * Lightweight guided tour system.
 * Usage:
 *   const { startTour, activeTour } = useTour('dashboard', [
 *     { target: '.stat-collected', title: 'Collections', text: 'Money collected this month' },
 *     { target: '.stat-pending',   title: 'Pending',     text: 'Outstanding invoices' },
 *   ])
 */

const activeTour = ref(null)  // shared across components
const currentStep = ref(0)
const tooltipStyle = ref({})
const arrowStyle = ref({})
let highlightTimer = null     // track pending 300ms timer

function positionTooltip(el) {
  if (!el) return
  const rect = el.getBoundingClientRect()
  const vw = window.innerWidth
  const vh = window.innerHeight

  // Try below the element first
  let top = rect.bottom + 12
  let left = rect.left + rect.width / 2

  // If below goes off screen, show above
  if (top + 180 > vh) {
    top = rect.top - 12
    tooltipStyle.value = {
      position: 'fixed',
      bottom: (vh - top) + 'px',
      left: Math.max(16, Math.min(left, vw - 200)) + 'px',
      transform: 'translateX(-50%)',
      zIndex: 10001,
    }
    arrowStyle.value = { bottom: '-6px', left: '50%', transform: 'translateX(-50%) rotate(180deg)' }
    return
  }

  tooltipStyle.value = {
    position: 'fixed',
    top: top + 'px',
    left: Math.max(16, Math.min(left, vw - 200)) + 'px',
    transform: 'translateX(-50%)',
    zIndex: 10001,
  }
  arrowStyle.value = { top: '-6px', left: '50%', transform: 'translateX(-50%)' }
}

export function useTour(tourId, steps = []) {
  function startTour() {
    if (!steps.length) return
    activeTour.value = { id: tourId, steps }
    currentStep.value = 0
    nextTick(() => highlightStep(0))
  }

  function clearHighlights() {
    document.querySelectorAll('.tour-highlight').forEach(el => {
      el.classList.remove('tour-highlight')
      el.style.removeProperty('position')
      el.style.removeProperty('z-index')
      el.style.removeProperty('box-shadow')
      el.style.removeProperty('border-radius')
    })
  }

  function highlightStep(idx) {
    // Cancel any pending highlight (race condition fix)
    if (highlightTimer) { clearTimeout(highlightTimer); highlightTimer = null }

    clearHighlights()

    if (idx >= steps.length) {
      endTour()
      return
    }

    const step = steps[idx]
    const el = document.querySelector(step.target)
    if (!el) {
      // Skip missing elements
      if (idx < steps.length - 1) {
        currentStep.value = idx + 1
        nextTick(() => highlightStep(idx + 1))
      } else {
        endTour()
      }
      return
    }

    // Scroll into view
    el.scrollIntoView({ behavior: 'smooth', block: 'center' })

    highlightTimer = setTimeout(() => {
      highlightTimer = null
      if (!activeTour.value) return  // tour ended while waiting — don't apply styles
      // Highlight the element
      el.classList.add('tour-highlight')
      el.style.position = 'relative'
      el.style.zIndex = '10000'
      el.style.boxShadow = '0 0 0 4px rgba(59, 125, 237, 0.4), 0 0 0 9999px rgba(0, 0, 0, 0.4)'
      el.style.borderRadius = el.style.borderRadius || '12px'

      positionTooltip(el)
    }, 300)
  }

  function nextStep() {
    if (currentStep.value < steps.length - 1) {
      currentStep.value++
      highlightStep(currentStep.value)
    } else {
      endTour()
    }
  }

  function prevStep() {
    if (currentStep.value > 0) {
      currentStep.value--
      highlightStep(currentStep.value)
    }
  }

  function endTour() {
    // Cancel pending highlight timer before cleanup
    if (highlightTimer) { clearTimeout(highlightTimer); highlightTimer = null }
    clearHighlights()
    // Save the ACTIVE tour's ID as seen (not the overlay's '_global' ID)
    const seenId = activeTour.value?.id || tourId
    activeTour.value = null
    currentStep.value = 0
    tooltipStyle.value = {}
    arrowStyle.value = {}
    localStorage.setItem(`tour_${seenId}_seen`, '1')
  }

  function isTourSeen() {
    return localStorage.getItem(`tour_${tourId}_seen`) === '1'
  }

  function resetTour() {
    localStorage.removeItem(`tour_${tourId}_seen`)
  }

  // Cleanup on unmount
  onUnmounted(() => {
    if (activeTour.value?.id === tourId) endTour()
  })

  return {
    activeTour,
    currentStep,
    tooltipStyle,
    arrowStyle,
    startTour,
    nextStep,
    prevStep,
    endTour,
    isTourSeen,
    resetTour,
  }
}
