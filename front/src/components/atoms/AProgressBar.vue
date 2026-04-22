<script setup lang="ts">
interface Props {
  value: number
  max?: number
  color?: 'primary' | 'success' | 'warning' | 'error'
  showLabel?: boolean
}

withDefaults(defineProps<Props>(), {
  max: 100,
  color: 'primary',
  showLabel: false,
})
</script>

<template>
  <div class="w-full">
    <progress
      :value="value"
      :max="max"
      :class="[
        'progress w-full',
        `progress-${color}`,
      ]"
    />
    <div v-if="showLabel" class="text-xs text-base-content/60 mt-1">
      {{ value }} / {{ max }}
    </div>
  </div>
</template>
