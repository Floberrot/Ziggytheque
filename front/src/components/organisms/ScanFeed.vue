<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { TriangleAlert, Book, CheckCircle2, CircleSlash, Info, RotateCcw, Search, Undo2 } from 'lucide-vue-next'
import type { ScanFeedItem } from '@/types'
import BaseLoader from '@/components/atoms/BaseLoader.vue'
import EditionBadge from '@/components/molecules/EditionBadge.vue'
import { coverUrl } from '@/utils/coverUrl'

/** Every barcode read while scanning a shelf — newest first — and what it did. */
defineProps<{ items: ScanFeedItem[] }>()

const emit = defineEmits<{
  undo: [item: ScanFeedItem]
  openSeries: [item: ScanFeedItem]
  searchManually: [item: ScanFeedItem]
}>()

const { t } = useI18n()
</script>

<template>
  <ul class="space-y-2">
    <li
      v-for="item in items"
      :key="item.id"
      class="flex items-center gap-3 p-2.5 rounded-2xl border bg-base-100"
      :class="{
        'border-success/40': item.status === 'added',
        'border-info/40': item.status === 'owned',
        'border-warning/40': item.status === 'notFound' || item.status === 'invalid',
        'border-error/40': item.status === 'error',
        'border-base-300/70 opacity-60': item.status === 'undone' || item.status === 'pending',
      }"
    >
      <div class="w-10 shrink-0 aspect-[2/3] rounded-md overflow-hidden bg-base-200 flex items-center justify-center">
        <img v-if="coverUrl(item.coverUrl)" :src="coverUrl(item.coverUrl)!" alt="" class="w-full h-full object-cover" />
        <Book v-else class="h-4 w-4 text-base-content/20" />
      </div>

      <div class="flex-1 min-w-0">
        <template v-if="item.workTitle">
          <p class="text-sm font-semibold truncate">
            {{ item.workTitle }}
            <span v-if="item.volumeNumber" class="text-base-content/50 font-normal">
              — {{ t('catalogue.tome', { number: item.volumeNumber }) }}
            </span>
          </p>
          <EditionBadge :publisher="item.publisher" :special-edition="item.specialEdition" size="xs" />
        </template>
        <p v-else class="text-sm font-mono text-base-content/60 truncate">{{ item.code }}</p>

        <p class="flex items-center gap-1 text-[11px] mt-0.5">
          <template v-if="item.status === 'pending'">
            <BaseLoader size="xs" /> <span class="text-base-content/50">{{ t('scanBatch.pending') }}</span>
          </template>
          <template v-else-if="item.status === 'added'">
            <CheckCircle2 class="h-3.5 w-3.5 text-success" />
            <span class="text-success">
              {{ item.seriesCreated
                ? t('scanBatch.addedNewSeries', { count: item.totalVolumes ?? 0 })
                : t('scanBatch.added') }}
            </span>
          </template>
          <template v-else-if="item.status === 'owned'">
            <Info class="h-3.5 w-3.5 text-info" /> <span class="text-info">{{ t('scanBatch.alreadyOwned') }}</span>
          </template>
          <template v-else-if="item.status === 'undone'">
            <RotateCcw class="h-3.5 w-3.5" /> <span>{{ t('scanBatch.undone') }}</span>
          </template>
          <template v-else-if="item.status === 'notFound'">
            <CircleSlash class="h-3.5 w-3.5 text-warning" /> <span class="text-warning">{{ t('scanBatch.notFound') }}</span>
          </template>
          <template v-else-if="item.status === 'invalid'">
            <TriangleAlert class="h-3.5 w-3.5 text-warning" /> <span class="text-warning">{{ t('scanBatch.invalid') }}</span>
          </template>
          <template v-else>
            <TriangleAlert class="h-3.5 w-3.5 text-error" /> <span class="text-error">{{ t('scanBatch.error') }}</span>
          </template>
        </p>
      </div>

      <div class="flex items-center gap-1 shrink-0">
        <button
          v-if="item.status === 'added' && item.volumeEntryId"
          class="btn btn-ghost btn-xs gap-1"
          :title="t('scanBatch.undo')"
          @click="emit('undo', item)"
        >
          <Undo2 class="h-3.5 w-3.5" />
          <span class="hidden sm:inline">{{ t('scanBatch.undo') }}</span>
        </button>
        <button
          v-if="item.collectionEntryId && (item.status === 'added' || item.status === 'owned')"
          class="btn btn-ghost btn-xs"
          @click="emit('openSeries', item)"
        >
          {{ t('scanBatch.openSeries') }}
        </button>
        <button
          v-if="item.status === 'notFound'"
          class="btn btn-ghost btn-xs gap-1"
          @click="emit('searchManually', item)"
        >
          <Search class="h-3.5 w-3.5" />
          {{ t('scanBatch.searchManually') }}
        </button>
      </div>
    </li>
  </ul>
</template>
