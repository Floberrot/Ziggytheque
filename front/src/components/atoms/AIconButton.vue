<script setup lang="ts">
import AIcon from './AIcon.vue'

interface Props {
  icon: string
  size?: 'sm' | 'md' | 'lg'
  variant?: 'ghost' | 'primary' | 'danger'
  disabled?: boolean
  ariaLabel: string
}

withDefaults(defineProps<Props>(), {
  size: 'md',
  variant: 'ghost',
})
</script>

<template>
  <button
    :disabled="disabled"
    :aria-label="ariaLabel"
    :class="[
      'btn btn-circle',
      `btn-${variant}`,
      {
        'btn-sm': size === 'sm',
        'btn-md': size === 'md',
        'btn-lg': size === 'lg',
      },
    ]"
  >
    <AIcon :name="icon" :size="size" />
  </button>
</template>
