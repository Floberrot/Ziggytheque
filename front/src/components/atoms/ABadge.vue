<script setup lang="ts">
interface Props {
  variant?: 'primary' | 'secondary' | 'success' | 'warning' | 'error' | 'info'
  subtle?: boolean
}

withDefaults(defineProps<Props>(), {
  variant: 'primary',
})
</script>

<template>
  <div
    :class="[
      'badge',
      `badge-${variant}`,
      { 'badge-outline': subtle },
    ]"
  >
    <slot />
  </div>
</template>
