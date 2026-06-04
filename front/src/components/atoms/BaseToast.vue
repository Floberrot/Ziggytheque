<script setup lang="ts">
import type { Toast } from '@/stores/useUiStore'
import BaseLoader from './BaseLoader.vue'

defineProps<{
  toast: Toast
}>()
</script>

<template>
  <div
    class="alert text-sm"
    :class="{
      'alert-success': toast.type === 'success',
      'alert-error': toast.type === 'error',
      'alert-info': toast.type === 'info' || toast.type === 'progress',
    }"
  >
    <template v-if="toast.type === 'progress'">
      <BaseLoader size="xs" />
      <div class="flex flex-col gap-0.5 min-w-0">
        <span>{{ toast.message }}</span>
        <progress
          v-if="toast.progress && toast.progress.total > 0"
          class="progress progress-primary w-full h-1.5"
          :value="toast.progress.current"
          :max="toast.progress.total"
        />
      </div>
    </template>
    <span v-else>{{ toast.message }}</span>
  </div>
</template>
