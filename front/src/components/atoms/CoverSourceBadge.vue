<script setup lang="ts">
import type { CoverSource } from '@/api/manga'

defineProps<{
  source: CoverSource
}>()
</script>

<template>
  <div
    v-if="source === 'mangadex'"
    class="tooltip"
    data-tip="Couvertures via MangaDex"
  >
    <!-- MangaDex logo -->
    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
      <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm3.5-9c.83 0 1.5-.67 1.5-1.5S16.33 8 15.5 8 14 8.67 14 9.5s.67 1.5 1.5 1.5zm-7 0c.83 0 1.5-.67 1.5-1.5S9.33 8 8.5 8 7 8.67 7 9.5 7.67 11 8.5 11zm3.5 6.5c2.33 0 4.31-1.46 5.11-3.5H6.89c.8 2.04 2.78 3.5 5.11 3.5z" />
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
:deep(.tooltip) {
  position: relative;
  z-index: 10;
}

svg {
  opacity: 0.7;
}
</style>
