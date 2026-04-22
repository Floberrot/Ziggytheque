<script setup lang="ts">
import { Dialog, TransitionRoot, TransitionChild } from '@headlessui/vue'

interface Props {
  open: boolean
  size?: 'sm' | 'md' | 'lg' | 'xl'
  title?: string
}

defineProps<Props>()
defineEmits<{ close: [] }>()

const sizeMap = {
  sm: 'max-w-sm',
  md: 'max-w-md',
  lg: 'max-w-lg',
  xl: 'max-w-xl',
}
</script>

<template>
  <TransitionRoot :show="open">
    <Dialog as="div" class="relative z-50" @close="$emit('close')">
      <TransitionChild
        as="template"
        enter="ease-out duration-200"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="ease-in duration-150"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-black/50 backdrop-blur-sm" />
      </TransitionChild>

      <div class="fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
          <TransitionChild
            as="template"
            enter="ease-out duration-200"
            enter-from="opacity-0 scale-95"
            enter-to="opacity-100 scale-100"
            leave="ease-in duration-150"
            leave-from="opacity-100 scale-100"
            leave-to="opacity-0 scale-95"
          >
            <Dialog.Panel
              :class="[
                'w-full transform bg-base-100 text-left align-middle shadow-xl transition-all rounded-lg p-6',
                sizeMap[size || 'md'],
              ]"
            >
              <Dialog.Title v-if="title" class="text-lg font-semibold leading-6">
                {{ title }}
              </Dialog.Title>
              <slot />
            </Dialog.Panel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>
