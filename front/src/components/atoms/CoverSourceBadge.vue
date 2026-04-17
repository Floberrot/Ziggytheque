<script setup lang="ts">
import type { CoverSource } from '@/api/manga'

defineProps<{
  source: CoverSource
}>()
</script>

<template>
  <div
    v-if="source === 'amazon'"
    class="tooltip"
    data-tip="Couvertures via Amazon"
  >
    <!-- Amazon smile logo -->
    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
      <path d="M12 0C5.37 0 0 5.37 0 12c0 6.63 5.37 12 12 12s12-5.37 12-12S18.63 0 12 0zm4.5 10.5c-1.5 1.5-3 2.25-3 3s.75.75 1.5.75c1.05 0 2.25-.75 3-1.5v2.25c-.75.75-2.25 1.5-3.75 1.5-2.25 0-3.75-1.5-3.75-3.75 0-1.5.75-3 2.25-4.5 1.5-1.5 2.25-2.25 2.25-3s-.75-.75-1.5-.75c-1.05 0-2.25.75-3 1.5V7.5c.75-.75 2.25-1.5 3.75-1.5 2.25 0 3.75 1.5 3.75 3.75 0 1.5-.75 3-2.25 4.5z" />
    </svg>
  </div>

  <div
    v-else-if="source === 'google'"
    class="tooltip"
    data-tip="Couvertures via Google Books"
  >
    <!-- Google Books stylized G logo -->
    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
      <path d="M12 0C5.37 0 0 5.37 0 12c0 6.63 5.37 12 12 12s12-5.37 12-12S18.63 0 12 0zm3 12c0 1.65-1.35 3-3 3s-3-1.35-3-3 1.35-3 3-3 3 1.35 3 3zm-7.5-2.25h3v4.5h-3z" />
    </svg>
  </div>

  <!-- null and 'none' render nothing -->
</template>

<style scoped>
svg {
  opacity: 0.7;
}
</style>
