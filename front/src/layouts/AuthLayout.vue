<script setup lang="ts">
import { RouterView } from 'vue-router'
</script>

<template>
  <div class="min-h-screen bg-base-100 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
      <RouterView v-slot="{ Component }">
        <Transition name="page">
          <component :is="Component" />
        </Transition>
      </RouterView>
    </div>
  </div>
</template>

<style scoped>
.page-enter-active,
.page-leave-active {
  transition: all var(--motion-base) var(--motion-ease-out);
}

.page-enter-from {
  opacity: 0;
  transform: translateY(4px);
}

.page-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
