<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { Trash2 } from 'lucide-vue-next'
import BaseButton from '@/components/atoms/BaseButton.vue'
import BaseModal from '@/components/atoms/BaseModal.vue'

/** Removing a series is not undoable: it asks first. */
defineProps<{
  open: boolean
  title: string
  pending: boolean
}>()

const emit = defineEmits<{
  confirm: []
  close: []
}>()

const { t } = useI18n()
</script>

<template>
  <BaseModal
    :open="open"
    variant="center"
    max-width-class="sm:max-w-sm"
    z-class="z-[80]"
    @close="emit('close')"
  >
    <div class="p-6">
      <div class="flex items-start gap-3 mb-4">
        <div class="w-10 h-10 rounded-full bg-error/15 flex items-center justify-center shrink-0 text-error">
          <Trash2 class="h-5 w-5" />
        </div>
        <div>
          <h3 class="font-bold text-lg leading-tight">{{ t('manga.removeConfirmTitle') }}</h3>
          <i18n-t keypath="manga.removeConfirmBody" tag="p" scope="global" class="text-sm text-base-content/60 mt-1 leading-relaxed">
            <template #title>
              <strong class="text-base-content">{{ title }}</strong>
            </template>
          </i18n-t>
        </div>
      </div>
      <div class="flex gap-3 justify-end">
        <button class="btn btn-ghost" @click="emit('close')">{{ t('common.cancel') }}</button>
        <BaseButton
          class="btn btn-error gap-2"
          :loading="pending"
          @click="emit('confirm')"
        >
          {{ t('common.delete') }}
        </BaseButton>
      </div>
    </div>
  </BaseModal>
</template>
