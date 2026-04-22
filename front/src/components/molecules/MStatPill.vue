<script setup lang="ts">
interface Props {
  color?: 'primary' | 'secondary' | 'success' | 'warning' | 'error'
  label: string
  value: string | number
}

withDefaults(defineProps<Props>(), {
  color: 'primary',
})
</script>

<template>
  <div class="flex items-center gap-3 px-4 py-2 rounded-full bg-base-200">
    <div
      :class="[
        'w-3 h-3 rounded-full',
        {
          'bg-primary': color === 'primary',
          'bg-secondary': color === 'secondary',
          'bg-success': color === 'success',
          'bg-warning': color === 'warning',
          'bg-error': color === 'error',
        },
      ]"
    />
    <div>
      <p class="text-xs text-base-content/60">{{ label }}</p>
      <p class="text-sm font-semibold">{{ value }}</p>
    </div>
  </div>
</template>
