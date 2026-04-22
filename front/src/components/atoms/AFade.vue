<script setup lang="ts">
defineProps<{ name?: string }>()
</script>

<template>
  <Transition :name="name || 'fade'">
    <slot />
  </Transition>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity var(--motion-base) var(--motion-ease-standard);
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
