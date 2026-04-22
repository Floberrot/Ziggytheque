<script setup lang="ts">
import { DialogPanel, TransitionRoot } from '@headlessui/vue'

export interface Props {
  open: boolean
  title?: string
  size?: 'sm' | 'md' | 'lg' | 'xl'
}

const props = withDefaults(defineProps<Props>(), {
  size: 'md',
})

defineEmits<{
  close: []
}>()

const sizeClasses = {
  sm: 'max-w-sm',
  md: 'max-w-md',
  lg: 'max-w-lg',
  xl: 'max-w-xl',
}
</script>

<template>
  <TransitionRoot :show="open" as="template">
    <div class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex min-h-full items-center justify-center p-4">
        <transition
          enter-active-class="duration-[var(--motion-base)]"
          enter-from-class="opacity-0"
          enter-to-class="opacity-100"
          leave-active-class="duration-[var(--motion-fast)]"
          leave-from-class="opacity-100"
          leave-to-class="opacity-0"
        >
          <div
            v-show="open"
            class="fixed inset-0 bg-black/50"
            @click="$emit('close')"
          />
        </transition>

        <TransitionRoot
          :show="open"
          as="template"
          enter="duration-[var(--motion-base)]"
          enter-from="opacity-0 scale-95"
          enter-to="opacity-100 scale-100"
          leave="duration-[var(--motion-fast)]"
          leave-from="opacity-100 scale-100"
          leave-to="opacity-0 scale-95"
        >
          <DialogPanel
            :class="[
              'w-full transform rounded-lg bg-base-100 p-6 shadow-lg transition-all',
              sizeClasses[size],
            ]"
          >
            <div v-if="title" class="mb-4">
              <h2 class="heading-md">{{ title }}</h2>
            </div>
            <div class="modal-body">
              <slot />
            </div>
            <div v-if="$slots.footer" class="divider my-4" />
            <div v-if="$slots.footer" class="modal-footer">
              <slot name="footer" />
            </div>
          </DialogPanel>
        </TransitionRoot>
      </div>
    </div>
  </TransitionRoot>
</template>
