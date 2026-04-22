<script setup lang="ts">
import { Dialog, DialogPanel, DialogTitle, TransitionRoot } from '@headlessui/vue'

export interface Props {
  open: boolean
  title: string
  description?: string
  confirmText?: string
  cancelText?: string
  loading?: boolean
}

withDefaults(defineProps<Props>(), {
  confirmText: 'Confirm',
  cancelText: 'Cancel',
  loading: false,
})

defineEmits<{
  confirm: []
  cancel: []
  close: []
}>()
</script>

<template>
  <TransitionRoot :show="open" as="template">
    <Dialog as="div" @close="$emit('close')" class="relative z-50">
      <transition
        enter-active-class="ease-out duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div class="fixed inset-0 bg-black bg-opacity-25" />
      </transition>

      <div class="fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4">
          <TransitionRoot
            :show="open"
            as="template"
            enter="ease-out duration-300"
            enter-from="opacity-0 scale-95"
            enter-to="opacity-100 scale-100"
            leave="ease-in duration-200"
            leave-from="opacity-100 scale-100"
            leave-to="opacity-0 scale-95"
          >
            <DialogPanel class="w-full max-w-md transform overflow-hidden rounded-lg bg-base-100 p-6 text-left align-middle shadow-xl transition-all">
              <DialogTitle class="heading-md mb-2">{{ title }}</DialogTitle>
              <p v-if="description" class="text-base-content/70 mb-6">{{ description }}</p>
              <div class="flex gap-2 justify-end">
                <AButton variant="ghost" @click="$emit('cancel')">
                  {{ cancelText }}
                </AButton>
                <AButton :loading="loading" @click="$emit('confirm')">
                  {{ confirmText }}
                </AButton>
              </div>
            </DialogPanel>
          </TransitionRoot>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>
