<script setup lang="ts">
interface Props {
  aspect?: string
  radius?: 'sm' | 'md' | 'lg' | 'full'
  width?: string
  height?: string
}

const props = withDefaults(defineProps<Props>(), {
  radius: 'md',
  aspect: 'square',
})

const radiusMap = {
  sm: 'rounded-sm',
  md: 'rounded-md',
  lg: 'rounded-lg',
  full: 'rounded-full',
}
</script>

<template>
  <div
    :style="{
      aspectRatio: aspect,
      width: width,
      height: height,
    }"
    :class="[
      'bg-base-300 animate-pulse',
      radiusMap[radius],
    ]"
  />
</template>
