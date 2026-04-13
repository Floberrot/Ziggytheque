<script setup lang="ts">
defineProps<{
  message: string
  type: 'success' | 'error' | 'info'
}>()
</script>

<template>
  <div
    class="alert text-sm"
    :class="{
      'alert-success': type === 'success',
      'alert-error': type === 'error',
      'alert-info': type === 'info',
    }"
  >
    <span>{{ message }}</span>
  </div>
</template>
