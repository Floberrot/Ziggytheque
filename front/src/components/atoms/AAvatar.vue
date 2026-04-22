<script setup lang="ts">
export interface Props {
  initials?: string
  image?: string
  size?: 'xs' | 'sm' | 'md' | 'lg'
}

withDefaults(defineProps<Props>(), {
  size: 'md',
})

const sizeClasses = {
  xs: 'w-6 h-6 text-xs',
  sm: 'w-8 h-8 text-sm',
  md: 'w-10 h-10 text-base',
  lg: 'w-12 h-12 text-lg',
}
</script>

<template>
  <div
    :class="[
      'avatar placeholder',
      sizeClasses[size],
    ]"
  >
    <div class="bg-primary text-primary-content rounded-full flex items-center justify-center">
      <img v-if="image" :src="image" :alt="initials" />
      <span v-else>{{ initials }}</span>
    </div>
  </div>
</template>
