<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { CheckSquare } from 'lucide-vue-next'
import type { VolumeEntry } from '@/types'
import type { ScreenPoint } from '@/utils/pointer'
import VolumeTile from '@/components/molecules/VolumeTile.vue'

/**
 * The tomes of a series, with their status legend and the batch selection (the page
 * owns the selection and applies the batch actions).
 */
const props = defineProps<{
  /** Sorted by number. */
  volumes: VolumeEntry[]
  ownedCount: number
  totalVolumes: number
  batchMode: boolean
  selectedIds: Set<string>
}>()

const emit = defineEmits<{
  toggleBatchMode: []
  select: [volumeEntryIds: Set<string>]
  activate: [volume: VolumeEntry]
  contextMenu: [volume: VolumeEntry, point: ScreenPoint]
  openActions: [volume: VolumeEntry]
}>()

const { t } = useI18n()

/** Quick picks of the batch selection. */
function selectWhere(matches: (volume: VolumeEntry) => boolean): void {
  emit('select', new Set(props.volumes.filter(matches).map((volume) => volume.id)))
}

function selectAll(): void {
  selectWhere(() => true)
}

function selectOwned(): void {
  selectWhere((volume) => volume.isOwned)
}

function selectUnread(): void {
  selectWhere((volume) => volume.isOwned && !volume.isRead)
}

function selectAnnounced(): void {
  selectWhere((volume) => volume.isAnnounced && !volume.isOwned)
}
</script>

<template>
  <div>
    <!-- Grid header -->
    <div class="flex items-center justify-between mb-3">
      <h2 class="text-xs font-semibold uppercase tracking-widest text-base-content/40">
        {{ t('collection.volumes') }} — {{ ownedCount }}/{{ totalVolumes }}
      </h2>
      <div class="flex items-center gap-3">
        <div class="hidden sm:flex gap-3 text-xs text-base-content/40">
          <span class="flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-sm bg-info ring-1 ring-info inline-block" />{{ t('enrich.statusReadLabel') }}
          </span>
          <span class="flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-sm bg-success ring-1 ring-success inline-block" />{{ t('enrich.statusOwnedLabel') }}
          </span>
          <span class="flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-sm bg-warning ring-1 ring-warning inline-block" />{{ t('enrich.statusWishedLabel') }}
          </span>
          <span class="flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-sm bg-secondary ring-1 ring-secondary inline-block" />{{ t('enrich.statusAnnouncedLabel') }}
          </span>
        </div>
        <button
          class="btn btn-xs gap-1"
          :class="batchMode ? 'btn-primary' : 'btn-ghost'"
          :aria-pressed="batchMode"
          @click="emit('toggleBatchMode')"
        >
          <CheckSquare class="w-3 h-3" />
          {{ batchMode ? t('volumeGrid.batchDone') : t('volumeGrid.batchSelect') }}
        </button>
      </div>
    </div>

    <!-- Batch quick-select row -->
    <div v-if="batchMode" class="flex flex-wrap gap-1.5 mb-3">
      <span class="text-xs text-base-content/40 self-center mr-1">{{ t('volumeGrid.selectLabel') }}</span>
      <button class="btn btn-xs btn-ghost" @click="selectAll">{{ t('volumeGrid.selectAll') }}</button>
      <button class="btn btn-xs btn-ghost" @click="selectOwned">{{ t('volumeGrid.selectOwned') }}</button>
      <button class="btn btn-xs btn-ghost" @click="selectUnread">{{ t('volumeGrid.selectUnread') }}</button>
      <button class="btn btn-xs btn-ghost" @click="selectAnnounced">{{ t('volumeGrid.selectAnnounced') }}</button>
      <button class="btn btn-xs btn-ghost text-base-content/30" @click="emit('select', new Set())">{{ t('volumeGrid.clearSelection') }}</button>
    </div>

    <div v-if="volumes.length" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-7 xl:grid-cols-8 gap-3">
      <VolumeTile
        v-for="volume in volumes"
        :key="volume.id"
        :volume="volume"
        :batch-mode="batchMode"
        :selected="selectedIds.has(volume.id)"
        @activate="emit('activate', volume)"
        @context-menu="emit('contextMenu', volume, $event)"
        @open-actions="emit('openActions', volume)"
      />
    </div>

    <p v-else class="text-sm text-base-content/40 italic py-4">
      {{ t('volumeGrid.empty') }}
    </p>

    <p v-if="!batchMode" class="mt-5 text-xs text-base-content/30 hidden sm:block">
      {{ t('volumeGrid.hintDesktop') }}
    </p>
    <p v-if="!batchMode" class="mt-5 text-xs text-base-content/30 sm:hidden">
      {{ t('volumeGrid.hintMobile') }}
    </p>
  </div>
</template>
