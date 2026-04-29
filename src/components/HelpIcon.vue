<script setup>
defineProps({ section: { type: String, required: true } })
</script>

<template>
  <RouterLink :to="'/help#' + section"
    class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-100 hover:bg-primary-100 text-gray-400 hover:text-primary-600 transition-colors shrink-0"
    title="View help for this page">
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
  </RouterLink>
</template>
