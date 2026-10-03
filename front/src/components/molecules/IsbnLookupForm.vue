<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/atoms/BaseButton.vue'

/** "ISBN" tab of the tome modal: type an ISBN, look its covers up. */
defineProps<{
  loading: boolean
  /** i18n key of the lookup error, or null. */
  errorKey: string | null
}>()

const isbn = defineModel<string>('isbn', { required: true })

const emit = defineEmits<{
  search: []
  /** The field lost the focus: the modal saves a valid ISBN on its own. */
  commit: []
}>()

const { t } = useI18n()
</script>

<template>
  <div class="flex gap-2">
    <input
      v-model="isbn"
      type="text"
      class="input input-bordered input-sm flex-1"
      :placeholder="t('enrich.isbnPlaceholder')"
      :aria-label="t('enrich.isbnLabel')"
      @keyup.enter="emit('search')"
      @blur="emit('commit')"
    />
    <BaseButton class="btn btn-sm btn-primary shrink-0" :loading="loading" :disabled="!isbn.trim()" @click="emit('search')">
      {{ t('enrich.searchIsbn') }}
    </BaseButton>
  </div>
  <p v-if="errorKey" class="text-error text-xs mt-2">{{ t(errorKey) }}</p>
</template>
