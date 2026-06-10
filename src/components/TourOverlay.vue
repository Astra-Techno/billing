<script setup>
import { useTour } from '../composables/useTour'

const { activeTour, currentStep, tooltipStyle, arrowStyle, nextStep, prevStep, endTour } = useTour('_global')
</script>

<template>
  <Teleport to="body">
    <template v-if="activeTour">
      <!-- Tooltip -->
      <div :style="tooltipStyle" class="tour-tooltip">
        <!-- Arrow -->
        <div class="tour-arrow" :style="arrowStyle"></div>

        <!-- Content -->
        <div class="p-4">
          <div class="flex items-start justify-between gap-2 mb-2">
            <h3 class="text-sm font-bold text-gray-900">{{ activeTour.steps[currentStep]?.title }}</h3>
            <button @click="endTour" class="text-gray-400 hover:text-gray-600 -mt-0.5 shrink-0">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
          <p class="text-xs text-gray-600 leading-relaxed">{{ activeTour.steps[currentStep]?.text }}</p>
        </div>

        <!-- Footer -->
        <div class="flex items-center justify-between px-4 py-2.5 border-t border-gray-100 bg-gray-50/50 rounded-b-xl">
          <span class="text-[10px] font-semibold text-gray-400">{{ currentStep + 1 }} / {{ activeTour.steps.length }}</span>
          <div class="flex gap-2">
            <button v-if="currentStep > 0" @click="prevStep"
              class="px-3 py-1 text-xs font-semibold text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">
              Back
            </button>
            <button @click="nextStep"
              class="px-3 py-1.5 text-xs font-semibold text-white rounded-lg transition-colors"
              style="background: linear-gradient(135deg, #3b7ded, #1a5fd4);">
              {{ currentStep === activeTour.steps.length - 1 ? 'Done' : 'Next' }}
            </button>
          </div>
        </div>
      </div>
    </template>
  </Teleport>
</template>

<style>
.tour-tooltip {
  width: 280px;
  background: white;
  border-radius: 12px;
  box-shadow: 0 20px 60px rgba(15, 23, 42, 0.2), 0 4px 16px rgba(15, 23, 42, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.8);
  overflow: hidden;
  animation: tourFadeIn 0.25s ease;
}
.tour-arrow {
  position: absolute;
  width: 12px;
  height: 12px;
  background: white;
  border: 1px solid #e5e7eb;
  border-right: none;
  border-bottom: none;
  transform-origin: center;
  transform: translateX(-50%) rotate(45deg);
}
@keyframes tourFadeIn {
  from { opacity: 0; transform: translateX(-50%) translateY(8px); }
  to { opacity: 1; transform: translateX(-50%) translateY(0); }
}
.dark .tour-tooltip { background: #1e293b; border-color: #334155; }
.dark .tour-tooltip h3 { color: #f1f5f9; }
.dark .tour-tooltip p { color: #94a3b8; }
.dark .tour-tooltip .border-t { border-color: #334155; }
.dark .tour-tooltip .bg-gray-50\/50 { background: rgba(15, 23, 42, 0.5); }
.dark .tour-arrow { background: #1e293b; border-color: #334155; }
</style>
