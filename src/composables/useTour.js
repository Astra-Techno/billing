// Tours disabled — all functions are no-ops
export function useTour() {
  return {
    activeTour:  { value: null },
    currentStep: { value: 0 },
    tooltipStyle:{ value: {} },
    arrowStyle:  { value: {} },
    startTour:   () => {},
    nextStep:    () => {},
    prevStep:    () => {},
    endTour:     () => {},
    isTourSeen:  () => true,
    resetTour:   () => {},
  }
}
