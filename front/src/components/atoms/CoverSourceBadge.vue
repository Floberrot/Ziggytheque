<script setup lang="ts">
import type { CoverSource } from '@/api/manga'

defineProps<{
  source: CoverSource
}>()
</script>

<template>
  <div
    v-if="source === 'openlibrary'"
    class="tooltip"
    data-tip="Couvertures via Open Library"
  >
    <!-- Open Library book logo -->
    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
      <path d="M6 2C4.9 2 4 2.9 4 4v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2H6zm0 2h12v16H6V4zm2 2v2h8V6H8zm0 4v2h8v-2H8zm0 4v2h8v-2H8z" />
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
