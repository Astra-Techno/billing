// Tours disabled — all functions are no-ops
import { ref } from 'vue'

const _activeTour = ref(null)  // always null — no tour will ever activate

export function useTour() {
  return {
    activeTour:  _activeTour,
    currentStep: ref(0),
    tooltipStyle:ref({}),
    arrowStyle:  ref({}),
    startTour:   () => {},
    nextStep:    () => {},
    prevStep:    () => {},
    endTour:     () => {},
    isTourSeen:  () => true,
    resetTour:   () => {},
  }
}
