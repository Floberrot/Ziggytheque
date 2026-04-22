<script setup lang="ts">
export interface Props {
  orientation?: 'horizontal' | 'vertical'
  label?: string
}

withDefaults(defineProps<Props>(), {
  orientation: 'horizontal',
})
</script>

<template>
  <div
    v-if="orientation === 'horizontal'"
    class="divider"
    :data-content="label"
  />
  <div v-else class="divider divider-vertical" />
</template>
