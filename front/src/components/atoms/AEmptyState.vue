<script setup lang="ts">
import AIcon from './AIcon.vue'

interface Props {
  icon?: string
  title: string
  description?: string
}

defineProps<Props>()
</script>

<template>
  <div class="flex flex-col items-center justify-center py-12 px-4">
    <AIcon v-if="icon" :name="icon" size="lg" class="text-base-content/40 mb-4" />
    <h3 class="text-lg font-semibold mb-2">{{ title }}</h3>
    <p v-if="description" class="text-sm text-base-content/60 text-center max-w-sm">
      {{ description }}
    </p>
    <slot name="actions" class="mt-4" />
  </div>
</template>
