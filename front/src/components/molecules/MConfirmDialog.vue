<script setup lang="ts">
import { Dialog, TransitionRoot, TransitionChild } from '@headlessui/vue'
import AButton from '../atoms/AButton.vue'

interface Props {
  open: boolean
  title: string
  description?: string
  confirmText?: string
  cancelText?: string
  danger?: boolean
}

defineProps<Props>()
defineEmits<{
  confirm: []
  cancel: []
}>()
</script>

<template>
  <TransitionRoot :show="open">
    <Dialog as="div" class="relative z-50" @close="$emit('cancel')">
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
            <Dialog.Panel class="w-full max-w-sm transform bg-base-100 rounded-lg p-6 shadow-xl transition-all">
              <Dialog.Title class="text-lg font-semibold mb-2">
                {{ title }}
              </Dialog.Title>
              <Dialog.Description v-if="description" class="text-sm text-base-content/70 mb-6">
                {{ description }}
              </Dialog.Description>
              <div class="flex gap-3 justify-end">
                <AButton variant="ghost" @click="$emit('cancel')">
                  {{ cancelText || 'Cancel' }}
                </AButton>
                <AButton :variant="danger ? 'danger' : 'primary'" @click="$emit('confirm')">
                  {{ confirmText || 'Confirm' }}
                </AButton>
              </div>
            </Dialog.Panel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>
