<script setup lang="ts">
import { computed } from 'vue'

export interface Props {
  value: number
  max?: number
  size?: 'sm' | 'md' | 'lg'
  showLabel?: boolean
  glow?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  max: 100,
  size: 'md',
  showLabel: true,
  glow: false,
})

const percentage = computed(() => (props.value / props.max) * 100)

const sizeClasses = {
  sm: 'w-16 h-16',
  md: 'w-24 h-24',
  lg: 'w-32 h-32',
}
</script>

<template>
  <div
    :class="[
      'radial-progress',
      sizeClasses[size],
      { 'pulse-glow': glow && percentage === 100 },
    ]"
    :style="{
      '--value': percentage,
      '--size': size === 'sm' ? '4rem' : size === 'md' ? '6rem' : '8rem',
    }"
    role="progressbar"
    :aria-valuenow="value"
    :aria-valuemax="max"
  >
    <span v-if="showLabel" class="text-center">
      <span class="text-sm font-semibold">{{ Math.round(percentage) }}%</span>
    </span>
  </div>
</template>
