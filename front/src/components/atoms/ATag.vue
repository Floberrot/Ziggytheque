<script setup lang="ts">
export interface Props {
  variant?: 'primary' | 'secondary' | 'neutral'
}

withDefaults(defineProps<Props>(), {
  variant: 'neutral',
})

const variantClasses = {
  primary: 'badge-primary',
  secondary: 'badge-secondary',
  neutral: 'badge-neutral',
}
</script>

<template>
  <span
    :class="[
      'badge badge-sm',
      variantClasses[variant],
    ]"
  >
    <slot />
  </span>
</template>
