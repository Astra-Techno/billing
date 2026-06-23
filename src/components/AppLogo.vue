<script setup>
import { APP_NAME, LOGO_SRC } from '../config/brand'

defineProps({
  size: { type: String, default: 'md' }, // sm | md | lg
  showName: { type: Boolean, default: false },
})

const sizes = {
  sm: 'h-10',
  md: 'h-14',
  lg: 'h-20',
}
</script>

<template>
  <div class="flex flex-col items-center">
    <img
      :src="LOGO_SRC"
      :alt="APP_NAME"
      class="w-auto object-contain"
      :class="sizes[size] || sizes.md"
    />
    <span v-if="showName" class="sr-only">{{ APP_NAME }}</span>
  </div>
</template>
