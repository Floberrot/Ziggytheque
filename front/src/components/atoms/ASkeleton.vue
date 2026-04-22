<script setup lang="ts">
export interface Props {
  width?: string
  height?: string
  aspect?: string
  radius?: 'xs' | 'sm' | 'md' | 'lg' | 'xl' | '2xl' | 'full'
}

withDefaults(defineProps<Props>(), {
  width: '100%',
  height: '1rem',
  radius: 'md',
})

const radiusClasses = {
  xs: 'rounded-xs',
  sm: 'rounded-sm',
  md: 'rounded-md',
  lg: 'rounded-lg',
  xl: 'rounded-xl',
  '2xl': 'rounded-2xl',
  full: 'rounded-full',
}
</script>

<template>
  <div
    :class="['skeleton', radiusClasses[radius]]"
    :style="{
      width: width,
      height: height,
      aspectRatio: aspect,
    }"
  />
</template>
