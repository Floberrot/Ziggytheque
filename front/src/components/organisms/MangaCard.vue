<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { Book, Layers } from 'lucide-vue-next'
import type { CollectionEntry, QuickActionRequest } from '@/types'
import BaseHeartRating from '@/components/atoms/BaseHeartRating.vue'
import BaseLazyImage from '@/components/atoms/BaseLazyImage.vue'
import EditionBadge from '@/components/molecules/EditionBadge.vue'
import { useLongPress, type PressPoint } from '@/composables/useLongPress'
import { coverUrl } from '@/utils/coverUrl'

const props = defineProps<{
  entry: CollectionEntry
  /** Every edition of the work, when the card stands for several: shown as a stack. */
  editions?: CollectionEntry[]
}>()

const emit = defineEmits<{
  /** A stack was opened: the page lists its editions. */
  openEditions: []
  /** Right click or long press: the page offers the quick actions. */
  quickActions: [request: QuickActionRequest]
}>()

const router = useRouter()
const { t } = useI18n()

const isStack = computed(() => (props.editions?.length ?? 0) > 1)

/** A stack sums its editions; a single card shows its own series. */
const summary = computed(() => {
  const members = isStack.value ? props.editions! : [props.entry]
  return members.reduce(
    (total, member) => ({
      ownedCount: total.ownedCount + member.ownedCount,
      readCount: total.readCount + member.readCount,
      wishedCount: total.wishedCount + member.wishedCount,
      totalVolumes: total.totalVolumes + member.totalVolumes,
      ownedValue: total.ownedValue + member.ownedValue,
    }),
    { ownedCount: 0, readCount: 0, wishedCount: 0, totalVolumes: 0, ownedValue: 0 },
  )
})

const cover = computed(() => {
  if (!isStack.value) return props.entry.manga.coverUrl
  return props.editions!.find((member) => member.manga.coverUrl)?.manga.coverUrl ?? null
})

const ownedRatio = computed(() =>
  summary.value.totalVolumes > 0
    ? (summary.value.ownedCount / summary.value.totalVolumes) * 100
    : 0,
)

const readRatio = computed(() =>
  summary.value.totalVolumes > 0
    ? (summary.value.readCount / summary.value.totalVolumes) * 100
    : 0,
)

const wishedRatio = computed(() =>
  summary.value.totalVolumes > 0
    ? (summary.value.wishedCount / summary.value.totalVolumes) * 100
    : 0,
)

const ringClass = computed(() => {
  if (!isStack.value && props.entry.readingStatus === 'dropped') return 'ring-error/50'
  if (ownedRatio.value === 100) return 'ring-success/70'
  if (ownedRatio.value > 50) return 'ring-primary/50'
  if (summary.value.wishedCount > 0) return 'ring-warning/40'
  return 'ring-base-300/30'
})

const statusChip = computed(() => {
  if (isStack.value) return null
  switch (props.entry.readingStatus) {
    case 'dropped':
      return { label: 'Abandonné', classes: 'bg-error/20 text-error border border-error/30 backdrop-blur-sm' }
    case 'on_hold':
      return { label: 'En pause', classes: 'bg-warning/20 text-warning border border-warning/30 backdrop-blur-sm' }
    case 'not_started':
      return { label: 'À lire', classes: 'bg-base-content/8 text-base-content/40 border border-base-content/12 backdrop-blur-sm' }
    case 'completed':
      return { label: 'Complet', classes: 'bg-success/20 text-success border border-success/30 backdrop-blur-sm' }
    default:
      return null
  }
})

const coverStyle = computed(() =>
  !isStack.value && props.entry.readingStatus === 'dropped'
    ? 'filter: grayscale(30%) brightness(0.8)'
    : '',
)

function requestQuickActions(point: PressPoint, source: QuickActionRequest['source']): void {
  if (isStack.value) {
    emit('openEditions')
    return
  }
  emit('quickActions', { entry: props.entry, point, source })
}

const longPress = useLongPress((point) => requestQuickActions(point, 'touch'))

function onContextMenu(event: MouseEvent): void {
  event.preventDefault()
  const point = { x: event.clientX, y: event.clientY }
  if (longPress.isTouching()) {
    // Android reports the long press as a context menu too: open it once.
    if (longPress.markHandled()) requestQuickActions(point, 'touch')
    return
  }
  requestQuickActions(point, 'mouse')
}

function open(): void {
  if (longPress.swallowClick()) return
  if (isStack.value) {
    emit('openEditions')
    return
  }
  router.push({ name: 'collection-detail', params: { id: props.entry.id } })
}
</script>

<template>
  <div
    class="manga-card group relative cursor-pointer select-none"
    @click="open"
    @contextmenu="onContextMenu"
    @touchstart.passive="longPress.onTouchStart"
    @touchmove.passive="longPress.onTouchMove"
    @touchend="longPress.onTouchEnd"
    @touchcancel="longPress.onTouchCancel"
  >
    <!-- Several editions of one work: the other editions peek out behind the card -->
    <template v-if="isStack">
      <div class="absolute inset-0 rounded-2xl bg-base-300 ring-1 ring-base-content/10 translate-x-2 -translate-y-2 rotate-3 transition-transform duration-300 group-hover:translate-x-3 group-hover:rotate-6" aria-hidden="true" />
      <div class="absolute inset-0 rounded-2xl bg-base-200 ring-1 ring-base-content/10 translate-x-1 -translate-y-1 rotate-[1.5deg] transition-transform duration-300 group-hover:translate-x-1.5 group-hover:rotate-3" aria-hidden="true" />
    </template>

    <div
      class="relative rounded-2xl overflow-hidden bg-base-200 shadow-md ring-2 transition-all duration-300 ease-out
             group-hover:shadow-2xl group-hover:scale-[1.03] group-hover:-translate-y-1"
      :class="ringClass"
    >
      <!-- Cover -->
      <div class="aspect-[2/3] overflow-hidden">
        <BaseLazyImage
          v-if="cover"
          :src="coverUrl(cover)!"
          :alt="entry.manga.title"
          class="transition-transform duration-500 group-hover:scale-110"
          :style="coverStyle"
        >
          <template #fallback>
            <div class="w-full h-full flex items-center justify-center bg-base-200 text-base-content/15">
              <Book class="h-12 w-12" stroke-width="1" />
            </div>
          </template>
        </BaseLazyImage>
        <div v-else class="w-full h-full flex items-center justify-center bg-base-200 text-base-content/15">
          <Book class="h-12 w-12" stroke-width="1" />
        </div>
      </div>

      <!-- Deep gradient overlay -->
      <div class="absolute inset-x-0 bottom-0 h-36 bg-gradient-to-t from-black/90 via-black/50 to-transparent pointer-events-none" />

      <!-- Top row: genre badge + status chip -->
      <div class="absolute inset-x-0 top-0 flex items-start justify-between p-2.5">
        <div class="flex flex-col gap-1.5">
          <span
            v-if="entry.manga.genre"
            class="badge badge-xs bg-black/55 text-white/75 border-none capitalize backdrop-blur-sm"
          >
            {{ entry.manga.genre }}
          </span>
          <!-- Rating chip below genre (appears on hover) -->
          <BaseHeartRating
            v-if="!isStack"
            :model-value="entry.rating"
            readonly
            compact
            class="opacity-0 group-hover:opacity-100 transition-opacity duration-200"
          />
        </div>
        <!-- Status chip — always visible for notable statuses -->
        <span
          v-if="statusChip"
          class="badge badge-xs font-semibold leading-none"
          :class="statusChip.classes"
        >
          {{ statusChip.label }}
        </span>
      </div>

      <!-- Bottom info -->
      <div class="absolute inset-x-0 bottom-0 px-3 pb-3 pt-1 flex flex-col gap-2">
        <!-- Title -->
        <div>
          <p class="text-white text-sm font-bold line-clamp-2 leading-snug drop-shadow-md">
            {{ entry.manga.title }}
          </p>
          <p v-if="entry.manga.author" class="text-white/60 text-[10px] truncate mt-0.5 leading-none">
            {{ entry.manga.author }}
          </p>
          <span
            v-if="isStack"
            class="mt-1 inline-flex items-center gap-1 rounded-full bg-primary/90 px-2 py-0.5 text-[10px] font-semibold text-primary-content"
          >
            <Layers class="h-3 w-3" />
            {{ t('collection.worksEditions', { count: editions!.length }) }}
          </span>
          <EditionBadge
            v-else
            :publisher="entry.manga.edition"
            :special-edition="entry.manga.specialEdition"
            tone="overlay"
            size="xs"
            class="mt-1"
          />
        </div>

        <!-- Stats row -->
        <div class="flex items-center justify-between gap-1">
          <div class="flex items-center gap-2.5 text-xs">
            <!-- Owned -->
            <span class="flex items-center gap-1">
              <span class="w-1.5 h-1.5 rounded-full bg-success shrink-0" />
              <span class="text-white font-semibold tabular-nums">{{ summary.ownedCount }}</span>
              <span class="text-white/40 tabular-nums">/{{ summary.totalVolumes }}</span>
            </span>
            <!-- Read -->
            <span v-if="summary.readCount > 0" class="flex items-center gap-1 text-info/80">
              <span class="w-1.5 h-1.5 rounded-full bg-info shrink-0" />
              <span class="tabular-nums">{{ summary.readCount }}</span>
            </span>
            <!-- Wished -->
            <span v-if="summary.wishedCount > 0" class="flex items-center gap-1 text-warning/80">
              <span class="w-1.5 h-1.5 rounded-full bg-warning shrink-0" />
              <span class="tabular-nums font-medium">{{ summary.wishedCount }}</span>
            </span>
          </div>
          <span
            v-if="summary.ownedValue > 0"
            class="text-[10px] text-white/55 shrink-0 tabular-nums opacity-0 group-hover:opacity-100 transition-opacity duration-200"
          >
            {{ summary.ownedValue.toFixed(2) }} €
          </span>
        </div>

        <!-- Progress bar -->
        <div class="relative w-full h-1.5 rounded-full bg-white/15 overflow-hidden">
          <div
            class="absolute left-0 top-0 h-full bg-info transition-all duration-500"
            :style="{ width: `${readRatio}%` }"
          />
          <div
            class="absolute top-0 h-full bg-success transition-all duration-500"
            :style="{ left: `${readRatio}%`, width: `${ownedRatio - readRatio}%` }"
          />
          <div
            class="absolute top-0 h-full bg-warning/80 transition-all duration-500"
            :style="{ left: `${ownedRatio}%`, width: `${Math.min(wishedRatio, 100 - ownedRatio)}%` }"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* A long press opens the quick actions, not the browser's image menu. */
.manga-card {
  -webkit-touch-callout: none;
}
</style>
