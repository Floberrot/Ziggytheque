<script setup lang="ts">
defineProps<{ name?: string }>()
</script>

<template>
  <Transition :name="name || 'slide-up'">
    <slot />
  </Transition>
</template>

<style scoped>
.slide-up-enter-active,
.slide-up-leave-active {
  transition:
    opacity var(--motion-base) var(--motion-ease-standard),
    transform var(--motion-base) var(--motion-ease-out);
}

.slide-up-enter-from,
.slide-up-leave-to {
  opacity: 0;
  transform: translateY(4px);
}
</style>
