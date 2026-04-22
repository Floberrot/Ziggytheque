<script setup lang="ts">
export interface Props {
  duration?: 'fast' | 'base' | 'slow'
}

withDefaults(defineProps<Props>(), {
  duration: 'base',
})

const durationClasses = {
  fast: 'duration-[var(--motion-fast)]',
  base: 'duration-[var(--motion-base)]',
  slow: 'duration-[var(--motion-slow)]',
}
</script>

<template>
  <Transition
    :name="`fade-${duration}`"
    :class="durationClasses[duration]"
  >
    <slot />
  </Transition>
</template>

<style scoped>
.fade-fast-enter-active,
.fade-fast-leave-active {
  transition: opacity var(--motion-fast);
}
.fade-base-enter-active,
.fade-base-leave-active {
  transition: opacity var(--motion-base);
}
.fade-slow-enter-active,
.fade-slow-leave-active {
  transition: opacity var(--motion-slow);
}
.fade-fast-enter-from,
.fade-fast-leave-to,
.fade-base-enter-from,
.fade-base-leave-to,
.fade-slow-enter-from,
.fade-slow-leave-to {
  opacity: 0;
}
</style>
