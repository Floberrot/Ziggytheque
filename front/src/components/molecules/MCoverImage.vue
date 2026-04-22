<script setup lang="ts">
import { computed } from 'vue'

export interface Props {
  src?: string
  alt: string
  fallback?: string
  ring?: boolean
  opacity?: 'full' | 'muted' | 'dim'
}

const props = withDefaults(defineProps<Props>(), {
  fallback: 'lucide:image',
  ring: false,
  opacity: 'full',
})

const opacityClasses = {
  full: 'opacity-100',
  muted: 'opacity-60',
  dim: 'opacity-30',
}

const imageClasses = computed(() => [
  'w-full h-full object-cover rounded-lg',
  opacityClasses[props.opacity],
  { 'ring-2 ring-primary ring-offset-2': props.ring },
])
</script>

<template>
  <div class="relative aspect-[2/3] overflow-hidden rounded-lg bg-base-200">
    <img
      v-if="src"
      :src="src"
      :alt="alt"
      :class="imageClasses"
    />
    <div v-else class="flex h-full w-full items-center justify-center bg-base-300">
      <AIcon :name="fallback" size="lg" class="opacity-50" />
    </div>
    <slot />
  </div>
</template>
