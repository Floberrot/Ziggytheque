<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { Search } from 'lucide-vue-next'
import type { CatalogueEdition, CatalogueSearchResult } from '@/api/catalogue'
import BaseLoader from '@/components/atoms/BaseLoader.vue'
import CatalogueEditionCard from '@/components/organisms/CatalogueEditionCard.vue'

/**
 * "Search" tab of the add page: a tome by title or author (an ISBN typed in the field
 * is detected by the page), the French series found. The page owns the query.
 */
defineProps<{
  isSearching: boolean
  /** The debounced query is long enough to search. */
  hasQuery: boolean
  errorMessage: string | null
  result: CatalogueSearchResult | undefined
}>()

const query = defineModel<string>('query', { required: true })
const mode = defineModel<'title' | 'author'>('mode', { required: true })

const emit = defineEmits<{
  select: [edition: CatalogueEdition]
  fillManually: []
}>()

const { t } = useI18n()

const MODES = ['title', 'author'] as const
</script>

<template>
  <section class="space-y-3">
    <div class="flex flex-col sm:flex-row gap-2">
      <label class="input input-bordered flex items-center gap-2 flex-1">
        <Search class="h-4 w-4 opacity-50 shrink-0" />
        <input
          v-model="query"
          type="search"
          class="grow"
          :placeholder="mode === 'title' ? t('add.searchPlaceholderTitle') : t('add.searchPlaceholderAuthor')"
          :aria-label="mode === 'title' ? t('add.searchPlaceholderTitle') : t('add.searchPlaceholderAuthor')"
          autocomplete="off"
          autofocus
        />
        <BaseLoader v-if="isSearching" size="xs" class="opacity-50" />
      </label>
      <div class="join shrink-0" role="group">
        <button
          v-for="option in MODES"
          :key="option"
          class="btn join-item"
          :class="mode === option ? 'btn-primary' : 'btn-outline'"
          :aria-pressed="mode === option"
          @click="mode = option"
        >
          {{ t(`add.mode.${option}`) }}
        </button>
      </div>
    </div>
    <p class="text-xs text-base-content/40">{{ t('add.searchHint') }}</p>

    <template v-if="hasQuery">
      <div v-if="errorMessage" class="alert alert-warning text-sm py-2">{{ errorMessage }}</div>

      <div v-else-if="result && result.editions.length" class="space-y-2">
        <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">
          {{ t('add.resultsCount', { count: result.editions.length }, result.editions.length) }}
        </p>
        <CatalogueEditionCard
          v-for="edition in result.editions"
          :key="`${edition.workTitle}|${edition.publisher}|${edition.specialEdition}`"
          :edition="edition"
          @select="emit('select', $event)"
        />
      </div>

      <div
        v-else-if="result && !isSearching"
        class="text-center py-8 space-y-3"
      >
        <p class="text-sm text-base-content/50">{{ t('add.noResults') }}</p>
        <button class="btn btn-outline btn-sm" @click="emit('fillManually')">{{ t('add.fillManually') }}</button>
      </div>
    </template>
  </section>
</template>
