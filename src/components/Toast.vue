<script setup>
import { useToast } from '../composables/useToast'

const { toasts, dismiss } = useToast()
</script>

<template>
  <Teleport to="body">
    <div class="fixed bottom-[calc(5rem+env(safe-area-inset-bottom))] lg:bottom-6 left-1/2 -translate-x-1/2 z-[9999] flex flex-col gap-2 items-center pointer-events-none px-4"
      style="min-width: 280px; max-width: 420px;">
      <TransitionGroup name="toast" tag="div" class="flex flex-col gap-2 w-full items-center">
        <div v-for="t in toasts" :key="t.id"
          class="pointer-events-auto w-full flex items-center gap-3 px-4 py-3.5 rounded-full shadow-gpay-lg text-sm font-medium bg-google-text text-white cursor-pointer select-none"
          :class="{
            'bg-google-text': t.type === 'success',
            '!bg-danger-500': t.type === 'error',
            '!bg-primary-600': t.type === 'info',
          }"
          @click="dismiss(t.id)">
          <div class="shrink-0">
            <svg v-if="t.type === 'success'" class="w-5 h-5 text-pay-green" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
            <svg v-else-if="t.type === 'error'" class="w-5 h-5 text-white/90" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            <svg v-else class="w-5 h-5 text-white/90" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <span class="flex-1 leading-snug">{{ t.message }}</span>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>
