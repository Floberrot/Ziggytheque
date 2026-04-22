<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  src?: string
  initials: string
  size?: 'sm' | 'md' | 'lg'
}

const props = withDefaults(defineProps<Props>(), {
  size: 'md',
})

const sizeMap = {
  sm: 'w-8 h-8 text-xs',
  md: 'w-10 h-10 text-sm',
  lg: 'w-12 h-12 text-base',
}

const bgColor = computed(() => {
  const colors = [
    'bg-primary',
    'bg-secondary',
    'bg-accent',
    'bg-success',
    'bg-warning',
  ]
  return colors[props.initials.charCodeAt(0) % colors.length]
})
</script>

<template>
  <div
    v-if="src"
    :class="['avatar', sizeMap[size]]"
  >
    <img :src="src" :alt="initials" />
  </div>
  <div
    v-else
    :class="[
      'flex items-center justify-center rounded-full font-semibold text-white',
      sizeMap[size],
      bgColor,
    ]"
  >
    {{ initials.toUpperCase() }}
  </div>
</template>
