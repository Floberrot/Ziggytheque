<script setup lang="ts">
import { Dialog, TransitionRoot, TransitionChild } from '@headlessui/vue'

interface Props {
  open: boolean
  title?: string
}

defineProps<Props>()
defineEmits<{ close: [] }>()
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

      <div class="fixed inset-x-0 bottom-0">
        <TransitionChild
          as="template"
          enter="ease-out duration-200"
          enter-from="translate-y-full"
          enter-to="translate-y-0"
          leave="ease-in duration-150"
          leave-from="translate-y-0"
          leave-to="translate-y-full"
        >
          <Dialog.Panel class="w-full bg-base-100 rounded-t-2xl p-6 shadow-2xl">
            <div v-if="title" class="flex items-center justify-between mb-4">
              <Dialog.Title class="text-lg font-semibold">{{ title }}</Dialog.Title>
              <button
                type="button"
                class="btn btn-ghost btn-circle btn-sm"
                @click="$emit('close')"
              >
                ✕
              </button>
            </div>
            <slot />
          </Dialog.Panel>
        </TransitionChild>
      </div>
    </Dialog>
  </TransitionRoot>
</template>
