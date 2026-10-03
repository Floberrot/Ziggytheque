<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { CatalogueEdition } from '@/api/catalogue'
import BaseLoader from '@/components/atoms/BaseLoader.vue'
import CatalogueEditionCard from '@/components/organisms/CatalogueEditionCard.vue'

/** The "Editions" tab: every French edition of the work the catalogues know. */
defineProps<{
  workTitle: string
  editions: CatalogueEdition[]
  loading: boolean
  failed: boolean
  /** The series on screen, flagged in the list. */
  currentEntryId: string
}>()

const emit = defineEmits<{ select: [edition: CatalogueEdition] }>()

const { t } = useI18n()
</script>

<template>
  <div class="space-y-3">
    <div>
      <h2 class="text-sm font-bold">{{ t('catalogue.otherEditionsTitle', { title: workTitle }) }}</h2>
      <p class="text-xs text-base-content/50">{{ t('catalogue.otherEditionsHint') }}</p>
    </div>
    <BaseLoader v-if="loading" variant="section" />
    <p v-else-if="failed" class="text-sm text-error py-4">{{ t('catalogue.searchError') }}</p>
    <div v-else-if="editions.length" class="flex flex-col gap-2">
      <div
        v-for="edition in editions"
        :key="`${edition.workTitle}|${edition.publisher}|${edition.specialEdition}`"
        class="relative"
      >
        <span
          v-if="edition.collection?.entryId === currentEntryId"
          class="absolute -top-2 left-3 z-10 badge badge-primary badge-xs"
        >
          {{ t('catalogue.thisSeries') }}
        </span>
        <CatalogueEditionCard :edition="edition" @select="emit('select', $event)" />
      </div>
    </div>
    <p v-else class="text-sm text-base-content/40 italic py-4">{{ t('catalogue.otherEditionsEmpty') }}</p>
  </div>
</template>
