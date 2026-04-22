<script setup lang="ts">
export interface Props {
  variant?: 'primary' | 'ghost' | 'danger' | 'success' | 'warning' | 'outline'
  size?: 'sm' | 'md' | 'lg'
  loading?: boolean
  disabled?: boolean
}

withDefaults(defineProps<Props>(), {
  variant: 'primary',
  size: 'md',
  loading: false,
  disabled: false,
})

const variantClasses = {
  primary: 'btn-primary',
  ghost: 'btn-ghost',
  danger: 'btn-error',
  success: 'btn-success',
  warning: 'btn-warning',
  outline: 'btn-outline',
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
      'btn gap-2',
      variantClasses[variant],
      sizeClasses[size],
      { 'btn-disabled': disabled || loading, loading },
    ]"
    :disabled="disabled || loading"
  >
    <slot />
  </button>
</template>
