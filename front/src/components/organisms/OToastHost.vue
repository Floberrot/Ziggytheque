<script setup lang="ts">
import { useUiStore } from '@/stores/useUiStore'

const uiStore = useUiStore()

function removeToast(id: string) {
  uiStore.removeToast(id)
}
</script>

<template>
  <div class="fixed bottom-20 lg:bottom-4 right-4 flex flex-col gap-2 z-50 pointer-events-none max-w-sm">
    <transition-group name="toast-list">
      <div
        v-for="toast in uiStore.toasts"
        :key="toast.id"
        :class="[
          'alert pointer-events-auto rounded-lg shadow-lg p-4 gap-3',
          toast.type === 'success' && 'alert-success',
          toast.type === 'error' && 'alert-error',
          toast.type === 'info' && 'alert-info',
          (toast.type as any) === 'warning' && 'alert-warning',
        ]"
      >
        <div class="flex-1">{{ toast.message }}</div>
        <button
          class="btn btn-ghost btn-xs"
          @click="removeToast(toast.id)"
        >
          <AIcon name="lucide:x" size="sm" />
        </button>
      </div>
    </transition-group>
  </div>
</template>

<style scoped>
.toast-list-enter-active,
.toast-list-leave-active {
  transition: all var(--motion-base);
}

.toast-list-enter-from,
.toast-list-leave-to {
  opacity: 0;
  transform: translateX(30px);
}
</style>
