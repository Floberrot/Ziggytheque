<script setup lang="ts">
interface Props {
  variant?: 'primary' | 'ghost' | 'danger' | 'success' | 'warning' | 'outline'
  size?: 'sm' | 'md' | 'lg'
  loading?: boolean
  disabled?: boolean
  type?: 'button' | 'submit' | 'reset'
}

withDefaults(defineProps<Props>(), {
  variant: 'primary',
  size: 'md',
  type: 'button',
})
</script>

<template>
  <button
    :type="type"
    :disabled="loading || disabled"
    :class="[
      'btn',
      `btn-${variant}`,
      `btn-${size}`,
      { 'btn-loading': loading },
      'transition-all duration-200 ease-out',
      'focus-visible:outline-2 focus-visible:outline-offset-2',
    ]"
  >
    <slot />
  </button>
</template>
