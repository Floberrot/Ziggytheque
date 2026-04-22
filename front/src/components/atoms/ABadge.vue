<script setup lang="ts">
export interface Props {
  variant?: 'primary' | 'secondary' | 'success' | 'warning' | 'error' | 'info'
  subtle?: boolean
}

withDefaults(defineProps<Props>(), {
  variant: 'primary',
  subtle: false,
})

const variantClasses = {
  primary: 'badge-primary',
  secondary: 'badge-secondary',
  success: 'badge-success',
  warning: 'badge-warning',
  error: 'badge-error',
  info: 'badge-info',
}
</script>

<template>
  <span
    :class="[
      'badge',
      variantClasses[variant],
      { 'badge-outline': subtle },
    ]"
  >
    <slot />
  </span>
</template>
