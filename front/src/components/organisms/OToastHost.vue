<script setup lang="ts">
import { computed } from 'vue'
import { useUiStore } from '@/stores/useUiStore'
import MToast from '../molecules/MToast.vue'

const ui = useUiStore()

const toasts = computed(() => ui.toasts)
</script>

<template>
  <div class="fixed bottom-4 right-4 z-50 space-y-2 max-w-sm pointer-events-none">
    <TransitionGroup name="slide-up">
      <MToast
        v-for="toast in toasts"
        :key="toast.id"
        :type="toast.type"
        :message="toast.message"
        class="pointer-events-auto"
      />
    </TransitionGroup>
  </div>
</template>
