<script setup lang="ts">
interface Props {
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
    :class="['divider', { 'divider-center': label }]"
  >
    {{ label }}
  </div>
  <div v-else class="divider divider-horizontal" />
</template>
