<script setup lang="ts">
export interface Props {
  duration?: 'fast' | 'base' | 'slow'
}

withDefaults(defineProps<Props>(), {
  duration: 'base',
})
</script>

<template>
  <Transition :name="`slide-up-${duration}`">
    <slot />
  </Transition>
</template>

<style scoped>
.slide-up-fast-enter-active,
.slide-up-fast-leave-active {
  transition: all var(--motion-fast) var(--motion-ease-out);
}
.slide-up-base-enter-active,
.slide-up-base-leave-active {
  transition: all var(--motion-base) var(--motion-ease-out);
}
.slide-up-slow-enter-active,
.slide-up-slow-leave-active {
  transition: all var(--motion-slow) var(--motion-ease-out);
}
.slide-up-fast-enter-from,
.slide-up-fast-leave-to,
.slide-up-base-enter-from,
.slide-up-base-leave-to,
.slide-up-slow-enter-from,
.slide-up-slow-leave-to {
  opacity: 0;
  transform: translateY(8px);
}
</style>
