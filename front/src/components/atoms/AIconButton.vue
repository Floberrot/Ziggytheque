<script setup lang="ts">
import AIcon from './AIcon.vue'

export interface Props {
  icon: string
  ariaLabel: string
  variant?: 'primary' | 'ghost' | 'danger' | 'success' | 'warning'
  size?: 'sm' | 'md' | 'lg'
  disabled?: boolean
  loading?: boolean
}

withDefaults(defineProps<Props>(), {
  variant: 'ghost',
  size: 'md',
  disabled: false,
  loading: false,
})

const variantClasses = {
  primary: 'btn-primary',
  ghost: 'btn-ghost',
  danger: 'btn-error',
  success: 'btn-success',
  warning: 'btn-warning',
}

const sizeClasses = {
  sm: 'btn-sm',
  md: 'btn-md',
  lg: 'btn-lg',
}
</script>

<template>
  <button
    :class="[
      'btn btn-circle',
      variantClasses[variant],
      sizeClasses[size],
      { 'btn-disabled': disabled || loading, loading },
    ]"
    :aria-label="ariaLabel"
    :disabled="disabled || loading"
  >
    <AIcon :name="icon" :size="size" />
  </button>
</template>
