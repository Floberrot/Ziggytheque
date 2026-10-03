<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { ImageOff, RefreshCw, Search } from 'lucide-vue-next'
import type { CoverProvider, VolumeSearchResult } from '@/api/manga'
import type { CoverProviderOption } from '@/composables/useCoverProvider'
import BaseButton from '@/components/atoms/BaseButton.vue'
import BaseCover from '@/components/atoms/BaseCover.vue'
import BaseCoverProviderLogo from '@/components/atoms/BaseCoverProviderLogo.vue'
import BaseLoader from '@/components/atoms/BaseLoader.vue'
import { coverSourceLabel } from '@/utils/coverSource'

/**
 * "Search" tab of the tome modal: cover suggestions found by title, from the source
 * picked by its logo. The modal owns the search (useCoverTitleSearch) and its paging.
 */
defineProps<{
  results: VolumeSearchResult[]
  isSearching: boolean
  isLoadingMore: boolean
  hasMore: boolean
  provider: CoverProvider
  providers: CoverProviderOption[]
  providerLabel: string
}>()

const query = defineModel<string>('query', { required: true })

const emit = defineEmits<{
  selectProvider: [provider: CoverProvider]
  refresh: []
  apply: [cover: { coverUrl: string; isbn?: string }]
}>()

const { t } = useI18n()

function selectProvider(provider: CoverProvider): void {
  emit('selectProvider', provider)
  // Close the DaisyUI dropdown by removing focus from the trigger/menu.
  if (document.activeElement instanceof HTMLElement) document.activeElement.blur()
}

function apply(result: VolumeSearchResult): void {
  if (result.coverUrl) emit('apply', { coverUrl: result.coverUrl, isbn: result.isbn ?? undefined })
}
</script>

<template>
  <div class="flex gap-2 items-center mb-4">
    <!-- Cover source picker: logo + tooltip naming the active source -->
    <div class="dropdown">
      <div
        tabindex="0"
        role="button"
        class="btn btn-square btn-outline btn-sm tooltip tooltip-right p-1.5"
        :data-tip="t('enrich.coverVia', { name: providerLabel })"
        :aria-label="t('enrich.coverVia', { name: providerLabel })"
      >
        <BaseCoverProviderLogo :provider="provider" class="h-full w-full" />
      </div>
      <ul
        tabindex="0"
        class="dropdown-content menu z-30 mt-1 w-48 rounded-box bg-base-100 p-1 shadow"
      >
        <li class="menu-title text-xs">{{ t('enrich.coverSource') }}</li>
        <li v-for="option in providers" :key="option.key">
          <button
            type="button"
            :class="{ active: option.key === provider }"
            :aria-pressed="option.key === provider"
            @click="selectProvider(option.key)"
          >
            <BaseCoverProviderLogo :provider="option.key" class="h-5 w-5 shrink-0" />
            <span>{{ option.label }}</span>
          </button>
        </li>
      </ul>
    </div>
    <label class="input input-bordered input-sm flex items-center gap-2 flex-1">
      <Search class="h-4 w-4 opacity-40 shrink-0" />
      <input
        v-model="query"
        type="text"
        class="grow text-sm"
        :placeholder="t('enrich.coverSearchPlaceholder')"
        :aria-label="t('enrich.coverSearchPlaceholder')"
      />
      <BaseLoader v-if="isSearching" size="xs" class="opacity-40" />
    </label>
    <BaseButton
      class="btn btn-square btn-outline btn-sm shrink-0"
      :loading="isSearching"
      :disabled="query.trim().length < 2"
      :title="t('enrich.refreshSearch')"
      :aria-label="t('enrich.refreshSearch')"
      @click="emit('refresh')"
    >
      <template #icon><RefreshCw class="h-4 w-4" /></template>
    </BaseButton>
  </div>
  <p v-if="!results.length && !isSearching" class="text-sm text-base-content/30 text-center py-10">
    {{ t('enrich.searchEmptyHint') }}
  </p>
  <TransitionGroup name="cover-pop" tag="div" class="grid grid-cols-2 sm:grid-cols-3 gap-4" appear>
    <button
      v-for="(result, index) in results"
      :key="result.externalId ?? result.coverUrl ?? index"
      class="group flex flex-col gap-1.5 text-left"
      :style="{ transitionDelay: Math.min(index, 8) * 35 + 'ms' }"
      :disabled="!result.coverUrl"
      @click="apply(result)"
    >
      <div
        class="w-full aspect-[2/3] rounded-lg overflow-hidden bg-base-200 ring-2 ring-transparent transition-all duration-150"
        :class="result.coverUrl
          ? 'group-hover:ring-primary group-hover:scale-[1.03] group-hover:shadow-lg cursor-pointer active:scale-95'
          : 'opacity-40'"
      >
        <BaseCover :src="result.coverUrl" :alt="result.title" class="w-full h-full">
          <template #fallback>
            <ImageOff class="h-9 w-9" stroke-width="1.5" />
          </template>
        </BaseCover>
      </div>
      <span v-if="result.source" class="badge badge-sm badge-ghost w-full justify-center font-medium">{{ coverSourceLabel(result.source) }}</span>
      <div v-if="result.title || result.edition" class="px-0.5">
        <p class="text-xs font-medium line-clamp-2 leading-tight">{{ result.title }}</p>
        <p v-if="result.edition" class="text-[10px] text-base-content/40 truncate">{{ result.edition }}</p>
      </div>
    </button>
  </TransitionGroup>
  <div v-if="isLoadingMore || hasMore" class="py-3 flex items-center justify-center gap-2 text-xs text-base-content/40">
    <BaseLoader v-if="isLoadingMore" size="xs" />
    <span v-else>{{ t('enrich.scrollForMore') }}</span>
  </div>
</template>

<style scoped>
/* Cover suggestions appear with a soft, slightly staggered fade-in instead of popping in */
.cover-pop-enter-active {
  transition: opacity 0.28s ease, transform 0.28s cubic-bezier(0.22, 0.61, 0.36, 1);
}
.cover-pop-leave-active {
  transition: opacity 0.15s ease;
}
.cover-pop-enter-from {
  opacity: 0;
  transform: scale(0.94) translateY(8px);
}
.cover-pop-leave-to {
  opacity: 0;
}
</style>
