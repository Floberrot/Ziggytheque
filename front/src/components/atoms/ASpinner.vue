<script setup lang="ts">
export interface Props {
  size?: 'xs' | 'sm' | 'md' | 'lg'
}

withDefaults(defineProps<Props>(), {
  size: 'md',
})

const sizeClasses = {
  xs: 'w-4 h-4',
  sm: 'w-6 h-6',
  md: 'w-8 h-8',
  lg: 'w-12 h-12',
}
</script>

<template>
  <div :class="['loading loading-spinner', sizeClasses[size]]" />
</template>
