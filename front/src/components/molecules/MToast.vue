<script setup lang="ts">
interface Props {
  type?: 'success' | 'error' | 'info' | 'warning'
  message: string
  icon?: string
}

withDefaults(defineProps<Props>(), {
  type: 'info',
})

const typeClass = {
  success: 'alert-success',
  error: 'alert-error',
  info: 'alert-info',
  warning: 'alert-warning',
}
</script>

<template>
  <div
    :role="type === 'error' ? 'alert' : 'status'"
    :aria-live="type === 'error' ? 'assertive' : 'polite'"
    :class="['alert', typeClass[type]]"
  >
    <span>{{ message }}</span>
  </div>
</template>
