<script setup lang="ts">
import { Icon } from '@iconify/vue'

interface Props {
  name: string
  size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl'
  class?: string
}

const props = withDefaults(defineProps<Props>(), {
  size: 'md',
})

const sizeMap = {
  xs: '16px',
  sm: '20px',
  md: '24px',
  lg: '32px',
  xl: '40px',
}
</script>

<template>
  <Icon
    :icon="name"
    :width="sizeMap[size]"
    :height="sizeMap[size]"
    :class="class"
  />
</template>
