<script setup lang="ts">
export interface Props {
  icon?: string
  title: string
  description?: string
}

defineProps<Props>()
</script>

<template>
  <div class="flex flex-col items-center justify-center gap-4 py-12 text-center">
    <AIcon v-if="icon" :name="icon" size="lg" class="opacity-50" />
    <div>
      <h3 class="heading-md mb-2">{{ title }}</h3>
      <p v-if="description" class="text-base-content/70">
        {{ description }}
      </p>
    </div>
    <slot />
  </div>
</template>
