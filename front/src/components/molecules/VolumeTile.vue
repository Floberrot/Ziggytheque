<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Eye, Megaphone, MoreHorizontal, Star } from 'lucide-vue-next'
import type { VolumeEntry } from '@/types'
import BaseLazyImage from '@/components/atoms/BaseLazyImage.vue'
import { coverUrl } from '@/utils/coverUrl'
import { contextMenuPoint, type ScreenPoint } from '@/utils/pointer'
import { volumeOpacityClass, volumeRingClass } from '@/utils/volumeStyles'

/**
 * One tome of the series grid. A button for the keyboard too (Enter / Space opens it,
 * the Menu key its quick actions); in batch mode it is a toggle of the selection.
 */
const props = defineProps<{
  volume: VolumeEntry
  batchMode: boolean
  selected: boolean
}>()

const emit = defineEmits<{
  /** Click, Enter or Space: the page opens the tome, or picks it in batch mode. */
  activate: []
  /** Right click, long press or Menu key: the quick actions at that point. */
  contextMenu: [point: ScreenPoint]
  /** The visible "⋯" of the phone layout. */
  openActions: []
}>()

const { t } = useI18n()

const isPicked = computed(() => props.batchMode && props.selected)

// Number colour of a tome without (or with a broken) cover, and of its label below.
const numberClass = computed(() => {
  const volume = props.volume
  if (volume.isOwned) return 'text-base-content/50'
  if (volume.isWished) return 'text-warning/60'
  if (volume.isAnnounced) return 'text-secondary/50'
  return 'text-base-content/15'
})

const labelClass = computed(() => {
  const volume = props.volume
  if (volume.isOwned) return 'text-base-content/60'
  if (volume.isWished) return 'text-warning/60'
  if (volume.isAnnounced) return 'text-secondary/60'
  return 'text-base-content/20'
})

/** "Tome 3 — Possédé, Lu": what a screen reader announces for the tile. */
const accessibleName = computed(() => {
  const volume = props.volume
  const statuses: string[] = []
  if (volume.isOwned) statuses.push(t('enrich.statusOwnedLabel'))
  if (volume.isRead) statuses.push(t('enrich.statusReadLabel'))
  if (volume.isWished && !volume.isOwned) statuses.push(t('enrich.statusWishedLabel'))
  if (volume.isAnnounced && !volume.isOwned) statuses.push(t('enrich.statusAnnouncedLabel'))
  if (statuses.length === 0) statuses.push(t('volume.untracked'))
  return `${t('catalogue.tome', { number: volume.number })} — ${statuses.join(', ')}`
})

function onContextMenu(event: MouseEvent): void {
  emit('contextMenu', contextMenuPoint(event))
}
</script>

<template>
  <div
    class="group relative cursor-pointer select-none rounded-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
    role="button"
    tabindex="0"
    :aria-label="accessibleName"
    :aria-pressed="batchMode ? selected : undefined"
    @click="emit('activate')"
    @keydown.enter.prevent="emit('activate')"
    @keydown.space.prevent="emit('activate')"
    @contextmenu.prevent="onContextMenu"
  >
    <!-- Selection indicator (batch mode) -->
    <div
      v-if="batchMode"
      class="absolute top-1 left-1 z-20 w-4 h-4 rounded-full border-2 flex items-center justify-center transition-all duration-150 pointer-events-none shadow-sm"
      :class="selected
        ? 'bg-primary border-primary text-primary-content'
        : 'bg-base-100/80 border-base-content/30'"
    >
      <svg v-if="selected" class="w-2.5 h-2.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
      </svg>
    </div>

    <!-- Cover card -->
    <div
      class="aspect-[2/3] rounded-xl overflow-hidden ring-2 transition-all duration-200 relative shadow-sm"
      :class="[
        volumeRingClass(volume, isPicked),
        volumeOpacityClass(volume),
        isPicked
          ? 'ring-offset-2 ring-offset-base-100 scale-105 shadow-lg shadow-primary/20'
          : 'group-hover:scale-105 group-hover:shadow-lg group-hover:z-10',
      ]"
    >
      <BaseLazyImage
        v-if="volume.coverUrl"
        :src="coverUrl(volume.coverUrl)!"
        :alt="t('catalogue.tome', { number: volume.number })"
      >
        <template #fallback>
          <div class="w-full h-full flex items-center justify-center bg-base-200">
            <span class="font-bold text-xl" :class="numberClass">
              {{ volume.number }}
            </span>
          </div>
        </template>
      </BaseLazyImage>
      <div
        v-else
        class="w-full h-full flex items-center justify-center bg-base-200"
      >
        <span class="font-bold text-xl" :class="numberClass">
          {{ volume.number }}
        </span>
      </div>

      <!-- Read indicator band at bottom -->
      <div
        v-if="volume.isRead"
        class="absolute bottom-0 left-0 right-0 bg-info/90 backdrop-blur-sm text-info-content text-[7px] font-black tracking-widest text-center py-[3px] leading-none uppercase"
        aria-hidden="true"
      >
        {{ t('enrich.statusReadLabel') }}
      </div>

      <!-- Hover overlay (non-batch mode) -->
      <div
        v-if="!batchMode"
        class="absolute inset-0 bg-primary/20 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center"
      >
        <div class="bg-white/80 rounded-full p-1 shadow">
          <Eye class="h-4 w-4 text-primary" />
        </div>
      </div>

      <!-- Mobile quick actions — visible "⋯" trigger (long-press via
           @contextmenu is unreliable on iOS Safari) -->
      <button
        v-if="!batchMode"
        class="sm:hidden absolute bottom-1 right-1 z-10 w-8 h-8 flex items-center justify-center rounded-full bg-base-100/90 text-base-content/70 shadow ring-1 ring-base-300"
        :aria-label="t('manga.volumeMenuLabel', { number: volume.number })"
        @click.stop="emit('openActions')"
        @keydown.enter.stop
        @keydown.space.stop
      >
        <MoreHorizontal class="h-4 w-4" />
      </button>
    </div>

    <!-- Announced badge (top-left) -->
    <div
      v-if="volume.isAnnounced && !volume.isOwned"
      class="absolute top-0.5 left-0.5 w-3.5 h-3.5 rounded-full bg-secondary flex items-center justify-center z-10 pointer-events-none shadow-sm"
    >
      <Megaphone class="w-2 h-2 text-secondary-content" />
    </div>

    <!-- Wished badge (top-right) -->
    <div
      v-if="volume.isWished && !volume.isOwned"
      class="absolute top-0.5 right-0.5 w-3.5 h-3.5 rounded-full bg-warning flex items-center justify-center z-10 pointer-events-none shadow-sm"
    >
      <Star class="w-2 h-2 text-warning-content" fill="currentColor" stroke-width="0" />
    </div>

    <!-- Number label -->
    <div
      class="text-center text-[10px] sm:text-[9px] mt-0.5 tabular-nums font-semibold leading-tight"
      :class="labelClass"
    >
      T{{ volume.number }}
    </div>
  </div>
</template>
