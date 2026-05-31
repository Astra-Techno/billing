<script setup>
import { useToast } from '../composables/useToast'

const { toasts, dismiss } = useToast()
</script>

<template>
  <Teleport to="body">
    <div class="fixed bottom-[calc(5.25rem+env(safe-area-inset-bottom))] lg:bottom-8 left-1/2 -translate-x-1/2 z-[9999] flex flex-col gap-2 items-center pointer-events-none px-4"
      style="min-width: 280px; max-width: 420px;">
      <TransitionGroup name="toast" tag="div" class="flex flex-col gap-2 w-full items-center">
        <div v-for="t in toasts" :key="t.id"
          class="pointer-events-auto w-full flex items-center gap-3 px-5 py-3.5 rounded-2xl text-sm font-semibold cursor-pointer select-none glass border border-white/50 shadow-premium"
          :class="{
            '!bg-ink/90 !text-white border-ink-soft/30': t.type === 'success',
            '!bg-danger-600/95 !text-white border-danger-500/30': t.type === 'error',
            '!bg-primary-600/95 !text-white border-primary-400/30': t.type === 'info',
          }"
          @click="dismiss(t.id)">
          <div class="shrink-0 w-8 h-8 rounded-xl flex items-center justify-center" :class="t.type === 'success' ? 'bg-pay-green/20' : 'bg-white/15'">
            <svg v-if="t.type === 'success'" class="w-4 h-4 text-emerald-300" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
            <svg v-else-if="t.type === 'error'" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <span class="flex-1 leading-snug">{{ t.message }}</span>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>
