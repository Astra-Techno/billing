<script setup>
defineProps({ text: { type: String, required: true } })
</script>
<template>
  <span class="group relative inline-flex align-middle" tabindex="0">
    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-blue-50 text-[11px] font-bold text-blue-600 cursor-help">?</span>
    <span class="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 hidden w-64 -translate-x-1/2 rounded-lg bg-gray-900 px-3 py-2 text-left text-xs font-normal leading-relaxed text-white shadow-xl group-hover:block group-focus:block">{{ text }}</span>
  </span>
</template>
