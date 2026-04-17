<script setup lang="ts">
import type { CoverSource } from '@/api/manga'

defineProps<{
  source: CoverSource
  searchQuery?: string
}>()

function openGoogleBooks() {
  const query = props.searchQuery || 'manga'
  window.open(`https://books.google.com/books?q=${encodeURIComponent(query)}`, '_blank')
}

const props = defineProps<{
  source: CoverSource
  searchQuery?: string
}>()
</script>

<template>
  <button
    v-if="source === 'google' && searchQuery"
    class="tooltip"
    data-tip="Chercher sur Google Books"
    @click="openGoogleBooks"
  >
    <!-- Google Books G logo -->
    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
      <path d="M12 0C5.37 0 0 5.37 0 12c0 6.63 5.37 12 12 12s12-5.37 12-12S18.63 0 12 0zm3 12c0 1.65-1.35 3-3 3s-3-1.35-3-3 1.35-3 3-3 3 1.35 3 3zm-7.5-2.25h3v4.5h-3z" />
    </svg>
  </button>
</template>

<style scoped>
:deep(.tooltip) {
  position: relative;
  z-index: 10;
  background: none;
  border: none;
  padding: 0;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

svg {
  opacity: 0.7;
  transition: opacity 0.2s;
}

:deep(.tooltip):hover svg {
  opacity: 1;
}
</style>
