<script setup lang="ts">
import { RouterView } from 'vue-router'
import { useBreakpoint } from '@/composables/ui/useBreakpoint'

const { isMobile } = useBreakpoint()
</script>

<template>
  <div class="min-h-screen bg-base-100">
    <header role="banner" class="hidden lg:block border-b border-base-300">
      <div class="navbar bg-base-100 px-4">
        <div class="flex-1">
          <h1 class="heading-md">Ziggytheque</h1>
        </div>
      </div>
    </header>

    <nav aria-label="Primary" class="hidden lg:block w-64 fixed left-0 top-16 bottom-0 bg-base-200 border-r border-base-300 p-4">
      <slot name="sidebar" />
    </nav>

    <main id="main" class="min-h-[calc(100vh-80px)] lg:min-h-[calc(100vh-80px)] lg:ml-64 pb-20 lg:pb-0">
      <a href="#main" class="sr-only focus:not-sr-only">Skip to main content</a>
      <RouterView v-slot="{ Component }">
        <Transition name="page">
          <component :is="Component" />
        </Transition>
      </RouterView>
    </main>

    <nav v-if="isMobile" aria-label="Mobile navigation" class="fixed bottom-0 left-0 right-0 bg-base-100 border-t border-base-300">
      <slot name="bottom-nav" />
    </nav>

    <slot name="modals" />
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
