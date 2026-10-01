<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { Layers, X } from 'lucide-vue-next'
import type { CollectionEntry, QuickActionRequest } from '@/types'
import BaseModal from '@/components/atoms/BaseModal.vue'
import MangaCard from '@/components/organisms/MangaCard.vue'

/** The editions of one work the collection holds, opened from its stacked card. */
defineProps<{ title: string | null; entries: CollectionEntry[] }>()

const emit = defineEmits<{
  close: []
  quickActions: [request: QuickActionRequest]
}>()

const { t } = useI18n()
</script>

<template>
  <BaseModal :open="title !== null" max-width-class="sm:max-w-3xl" @close="emit('close')">
    <div class="flex items-center gap-2 px-4 pt-4 pb-3 border-b border-base-200">
      <Layers class="h-5 w-5 text-primary shrink-0" />
      <div class="min-w-0 flex-1">
        <h2 class="font-bold leading-tight truncate">{{ title }}</h2>
        <p class="text-xs text-base-content/50">{{ t('collection.worksEditions', { count: entries.length }) }}</p>
      </div>
      <button type="button" class="btn btn-ghost btn-sm btn-circle" :aria-label="t('common.close')" @click="emit('close')">
        <X class="h-4 w-4" />
      </button>
    </div>
    <div class="overflow-y-auto p-4">
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        <MangaCard
          v-for="entry in entries"
          :key="entry.id"
          :entry="entry"
          @quick-actions="emit('quickActions', $event)"
        />
      </div>
    </div>
  </BaseModal>
</template>
