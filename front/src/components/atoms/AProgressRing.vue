<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  value: number
  max?: number
  size?: number
  strokeWidth?: number
  color?: string
}

const props = withDefaults(defineProps<Props>(), {
  max: 100,
  size: 120,
  strokeWidth: 4,
  color: 'oklch(0.72 0.18 12)',
})

const circumference = computed(() => 2 * Math.PI * (props.size / 2 - props.strokeWidth / 2))
const offset = computed(() => circumference.value - (props.value / props.max) * circumference.value)
</script>

<template>
  <div class="flex items-center justify-center">
    <svg :width="size" :height="size" class="transform -rotate-90">
      <circle
        :cx="size / 2"
        :cy="size / 2"
        :r="size / 2 - strokeWidth / 2"
        :stroke-width="strokeWidth"
        fill="none"
        stroke="currentColor"
        class="text-base-300"
      />
      <circle
        :cx="size / 2"
        :cy="size / 2"
        :r="size / 2 - strokeWidth / 2"
        :stroke-width="strokeWidth"
        fill="none"
        :stroke="color"
        stroke-dasharray="circumference"
        :stroke-dashoffset="offset"
        stroke-linecap="round"
        class="transition-all duration-500"
      />
    </svg>
    <div class="absolute flex items-center justify-center">
      <span class="text-sm font-semibold">
        <slot>{{ Math.round((value / max) * 100) }}%</slot>
      </span>
    </div>
  </div>
</template>
