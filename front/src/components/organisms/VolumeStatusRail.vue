<script setup lang="ts">
import { computed, type Component } from 'vue'
import { useI18n } from 'vue-i18n'
import { Book, BookOpen, Check, HelpCircle, Info, Megaphone, Package, Plus, Star } from 'lucide-vue-next'
import type { VolumeEntry, VolumeToggleField } from '@/types'
import BaseCover from '@/components/atoms/BaseCover.vue'
import { volumeHeadlineStatus } from '@/utils/volumeStyles'

/**
 * Left rail of the tome modal: the cover (zoomable), the status cards of the tome and
 * its ISBN. It only emits which flag to flip — the page saves it.
 */
const props = defineProps<{ volume: VolumeEntry }>()

const emit = defineEmits<{
  toggle: [field: VolumeToggleField]
  openGuide: []
  zoom: []
}>()

const { t } = useI18n()

const headlineStatus = computed(() => volumeHeadlineStatus(props.volume))

const coverRingClass = computed(() => {
  switch (headlineStatus.value) {
    case 'owned':
      return 'ring-success/60'
    case 'wished':
      return 'ring-warning/60'
    case 'announced':
      return 'ring-secondary/50 ring-dashed'
    default:
      return 'ring-base-300'
  }
})

// ── Status cards (the "owned / wished / announced" panel) ──
// Static class literals per field so Tailwind keeps them; visibility + active
// state are derived from the current volume below.
interface StatusToggleConfig {
  field: VolumeToggleField
  icon: Component
  labelKey: string
  descriptionKey: string
  activeCard: string
  iconChip: string
  dotActive: string
}

const STATUS_TOGGLES: Record<Exclude<VolumeToggleField, 'isRead'>, StatusToggleConfig> = {
  isOwned: {
    field: 'isOwned',
    icon: Package,
    labelKey: 'enrich.statusOwnedLabel',
    descriptionKey: 'enrich.statusOwnedDesc',
    activeCard: 'border-success bg-success/10 ring-1 ring-success/30',
    iconChip: 'bg-success/15 text-success',
    dotActive: 'bg-success text-success-content',
  },
  isWished: {
    field: 'isWished',
    icon: Star,
    labelKey: 'enrich.statusWishedLabel',
    descriptionKey: 'enrich.statusWishedDesc',
    activeCard: 'border-warning bg-warning/10 ring-1 ring-warning/30',
    iconChip: 'bg-warning/15 text-warning',
    dotActive: 'bg-warning text-warning-content',
  },
  isAnnounced: {
    field: 'isAnnounced',
    icon: Megaphone,
    labelKey: 'enrich.statusAnnouncedLabel',
    descriptionKey: 'enrich.statusAnnouncedDesc',
    activeCard: 'border-secondary bg-secondary/10 ring-1 ring-secondary/30',
    iconChip: 'bg-secondary/15 text-secondary',
    dotActive: 'bg-secondary text-secondary-content',
  },
}

// Possession cards (Owned / Wished / Announced). "Owned" is always offered; wishing
// and announcing only make sense while not owned. Reading is a separate switch below
// (owning ≠ reading).
const possessionToggles = computed<{ config: StatusToggleConfig; active: boolean }[]>(() => {
  const volume = props.volume
  const list = [{ config: STATUS_TOGGLES.isOwned, active: volume.isOwned }]
  if (!volume.isOwned) {
    list.push({ config: STATUS_TOGGLES.isWished, active: volume.isWished })
    list.push({ config: STATUS_TOGGLES.isAnnounced, active: volume.isAnnounced })
  }
  return list
})

function zoom(): void {
  if (props.volume.coverUrl) emit('zoom')
}
</script>

<template>
  <!-- Mobile: a band at the top · Desktop: a side column -->
  <div class="shrink-0 sm:w-72 flex flex-col gap-4 p-4 sm:p-5 border-b sm:border-b-0 sm:border-r border-base-200 sm:overflow-y-auto">
    <div class="flex flex-col gap-3">
      <!-- Cover preview -->
      <div
        class="shrink-0 w-28 mx-auto sm:w-44 aspect-[2/3] rounded-xl overflow-hidden ring-2 bg-base-200 transition-transform duration-150 relative"
        :class="[
          coverRingClass,
          volume.coverUrl ? 'cursor-zoom-in hover:scale-105 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary' : '',
        ]"
        :role="volume.coverUrl ? 'button' : undefined"
        :tabindex="volume.coverUrl ? 0 : undefined"
        :aria-label="volume.coverUrl ? t('enrich.zoomCover') : undefined"
        @click="zoom"
        @keydown.enter.prevent="zoom"
        @keydown.space.prevent="zoom"
      >
        <BaseCover v-if="volume.coverUrl" :src="volume.coverUrl" :alt="t('catalogue.tome', { number: volume.number })" class="w-full h-full" />
        <div v-else-if="volume.isAnnounced && !volume.isOwned" class="w-full h-full flex items-end justify-center bg-base-300" style="background-image: repeating-linear-gradient(45deg, transparent, transparent 4px, rgba(0,0,0,.06) 4px, rgba(0,0,0,.06) 8px);">
          <span class="badge badge-secondary mb-2 text-[9px]">{{ t('enrich.statusAnnouncedLabel') }}</span>
        </div>
        <div v-else class="w-full h-full flex items-center justify-center text-base-content/20">
          <Book class="h-10 w-10" stroke-width="1.5" />
        </div>
      </div>

      <!-- ── Status toggles — clear, self-explanatory cards ── -->
      <div class="flex flex-col gap-2.5">
        <div class="flex items-center justify-between gap-2">
          <p class="text-[11px] font-bold uppercase tracking-wide text-base-content/45">
            {{ t('enrich.statusTitle') }}
          </p>
          <button
            class="text-[11px] font-medium text-primary/70 hover:text-primary inline-flex items-center gap-0.5"
            @click="emit('openGuide')"
          >
            <HelpCircle class="h-3 w-3" />
            {{ t('enrich.statusHelp') }}
          </button>
        </div>

        <!-- Possession cards (Owned / Wished / Announced) -->
        <button
          v-for="{ config, active } in possessionToggles"
          :key="config.field"
          type="button"
          class="group/status relative flex items-center gap-3 w-full rounded-xl border p-2.5 text-left transition-all duration-150 active:scale-[0.98]"
          :class="active
            ? config.activeCard
            : 'border-base-300/70 bg-base-100 hover:border-base-content/20 hover:bg-base-200/40'"
          :aria-pressed="active"
          @click="emit('toggle', config.field)"
        >
          <span
            class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 transition-colors"
            :class="active ? config.iconChip : 'bg-base-200 text-base-content/40 group-hover/status:text-base-content/60'"
          >
            <component :is="config.icon" class="h-4 w-4" />
          </span>
          <span class="min-w-0 flex-1">
            <span class="block text-sm font-semibold leading-tight">{{ t(config.labelKey) }}</span>
            <span class="block text-[11px] text-base-content/50 leading-snug mt-0.5">{{ t(config.descriptionKey) }}</span>
          </span>
          <!-- State indicator: filled check when active, empty ring otherwise -->
          <span
            class="w-5 h-5 rounded-full flex items-center justify-center shrink-0 transition-all"
            :class="active ? config.dotActive : 'border-2 border-base-300 text-transparent group-hover/status:border-base-content/30'"
          >
            <Check v-if="active" class="h-3 w-3" stroke-width="3" />
            <Plus v-else class="h-3 w-3 text-base-content/30" stroke-width="3" />
          </span>
        </button>

        <!-- "Read" — an independent switch (a volume is read or not, regardless of how it's owned) -->
        <button
          v-if="volume.isOwned"
          type="button"
          class="group/read flex items-center gap-3 w-full rounded-xl border p-2.5 text-left transition-all duration-150 active:scale-[0.98]"
          :class="volume.isRead
            ? 'border-info bg-info/10 ring-1 ring-info/30'
            : 'border-base-300/70 bg-base-100 hover:border-base-content/20 hover:bg-base-200/40'"
          :aria-pressed="volume.isRead"
          @click="emit('toggle', 'isRead')"
        >
          <span
            class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 transition-colors"
            :class="volume.isRead ? 'bg-info/15 text-info' : 'bg-base-200 text-base-content/40 group-hover/read:text-base-content/60'"
          >
            <BookOpen class="h-4 w-4" />
          </span>
          <span class="min-w-0 flex-1">
            <span class="block text-sm font-semibold leading-tight">{{ t('enrich.statusReadLabel') }}</span>
            <span class="block text-[11px] text-base-content/50 leading-snug mt-0.5">{{ t('enrich.statusReadDesc') }}</span>
          </span>
          <!-- Switch -->
          <span
            class="relative w-10 h-6 rounded-full shrink-0 transition-colors duration-200"
            :class="volume.isRead ? 'bg-info' : 'bg-base-300'"
          >
            <span
              class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-base-100 shadow transition-transform duration-200"
              :class="volume.isRead ? 'translate-x-4' : ''"
            />
          </span>
        </button>

        <p class="text-[11px] text-base-content/40 leading-snug px-0.5">
          {{ volume.isOwned ? t('enrich.statusHintOwned') : t('enrich.statusHintNotOwned') }}
        </p>
      </div>
    </div>

    <!-- The tome's ISBN + help (desktop) -->
    <div class="hidden sm:block mt-auto pt-4 border-t border-base-200">
      <p class="text-[11px] font-semibold uppercase tracking-wide text-base-content/40">{{ t('enrich.isbnOfVolume') }}</p>
      <p class="text-sm font-semibold mt-1 tabular-nums">{{ volume.isbn || t('enrich.isbnUnknown') }}</p>
      <p class="flex items-start gap-1.5 text-[11px] text-base-content/40 mt-1.5 leading-snug">
        <Info class="h-3.5 w-3.5 shrink-0 mt-px text-primary/70" />
        {{ t('enrich.isbnHint') }}
      </p>
    </div>
  </div>
</template>
