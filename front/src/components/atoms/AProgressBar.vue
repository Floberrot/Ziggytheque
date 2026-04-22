<script setup lang="ts">
export interface Segment {
  value: number
  variant: 'success' | 'warning' | 'primary' | 'secondary'
}

export interface Props {
  segments: Segment[]
  height?: 'xs' | 'sm' | 'md'
}

const props = withDefaults(defineProps<Props>(), {
  height: 'sm',
})

const heightClasses = {
  xs: 'h-1',
  sm: 'h-2',
  md: 'h-3',
}

const variantClasses = {
  success: 'bg-success',
  warning: 'bg-warning',
  primary: 'bg-primary',
  secondary: 'bg-secondary',
}

const getTotal = () => props.segments.reduce((sum, seg) => sum + seg.value, 0)
</script>

<template>
  <div :class="['flex w-full gap-0.5 overflow-hidden rounded', heightClasses[props.height]]">
    <div
      v-for="(seg, idx) in props.segments"
      :key="idx"
      :class="[variantClasses[seg.variant]]"
      :style="{ flex: seg.value / getTotal() }"
    />
  </div>
</template>
