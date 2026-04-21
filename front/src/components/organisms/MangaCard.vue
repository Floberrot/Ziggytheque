<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import type { CollectionEntry } from '@/types'
import BaseHeartRating from '@/components/atoms/BaseHeartRating.vue'
import { coverUrl } from '@/utils/coverUrl'

const props = defineProps<{ entry: CollectionEntry }>()
const router = useRouter()

const ownedRatio = computed(() =>
  props.entry.totalVolumes > 0
    ? (props.entry.ownedCount / props.entry.totalVolumes) * 100
    : 0,
)

const readRatio = computed(() =>
  props.entry.totalVolumes > 0
    ? (props.entry.readCount / props.entry.totalVolumes) * 100
    : 0,
)

const wishedRatio = computed(() =>
  props.entry.totalVolumes > 0
    ? (props.entry.wishedCount / props.entry.totalVolumes) * 100
    : 0,
)

const ringClass = computed(() => {
  if (props.entry.readingStatus === 'dropped') return 'ring-error/50'
  if (ownedRatio.value === 100) return 'ring-success/70'
  if (ownedRatio.value > 50) return 'ring-primary/50'
  if (props.entry.wishedCount > 0) return 'ring-warning/40'
  return 'ring-base-300/30'
})

const statusChip = computed(() => {
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
  props.entry.readingStatus === 'dropped'
    ? 'filter: grayscale(30%) brightness(0.8)'
    : '',
)

function open() {
  router.push({ name: 'collection-detail', params: { id: props.entry.id } })
}
</script>

<template>
  <div
    class="manga-card group relative cursor-pointer select-none"
    @click="open"
  >
    <div
      class="relative rounded-2xl overflow-hidden bg-base-200 shadow-md ring-2 transition-all duration-300 ease-out
             group-hover:shadow-2xl group-hover:scale-[1.03] group-hover:-translate-y-1"
      :class="ringClass"
    >
      <!-- Cover -->
      <div class="aspect-[2/3] overflow-hidden">
        <img
          v-if="entry.manga.coverUrl"
          :src="coverUrl(entry.manga.coverUrl)!"
          :alt="entry.manga.title"
          class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
          :style="coverStyle"
          loading="lazy"
        />
        <div v-else class="w-full h-full flex items-center justify-center bg-base-200 text-base-content/15">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
          </svg>
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
          <p class="text-white/45 text-[10px] truncate mt-0.5 leading-none opacity-0 group-hover:opacity-100 transition-opacity duration-200">
            {{ entry.manga.edition }}
          </p>
        </div>

        <!-- Stats row -->
        <div class="flex items-center justify-between gap-1">
          <div class="flex items-center gap-2.5 text-xs">
            <!-- Owned -->
            <span class="flex items-center gap-1">
              <span class="w-1.5 h-1.5 rounded-full bg-success shrink-0" />
              <span class="text-white font-semibold tabular-nums">{{ entry.ownedCount }}</span>
              <span class="text-white/40 tabular-nums">/{{ entry.totalVolumes }}</span>
            </span>
            <!-- Read -->
            <span v-if="entry.readCount > 0" class="flex items-center gap-1 text-info/80">
              <span class="w-1.5 h-1.5 rounded-full bg-info shrink-0" />
              <span class="tabular-nums">{{ entry.readCount }}</span>
            </span>
            <!-- Wished -->
            <span v-if="entry.wishedCount > 0" class="flex items-center gap-1 text-warning/80">
              <span class="w-1.5 h-1.5 rounded-full bg-warning shrink-0" />
              <span class="tabular-nums font-medium">{{ entry.wishedCount }}</span>
            </span>
            <!-- Announced -->
            <span v-if="entry.announcedCount > 0" class="flex items-center gap-1 text-base-content/50">
              <span class="w-1.5 h-1.5 rounded-full bg-base-300 shrink-0" />
              <span class="tabular-nums font-medium">{{ entry.announcedCount }}</span>
            </span>
          </div>
          <span v-if="entry.ownedValue > 0" class="text-[10px] text-white/45 shrink-0 tabular-nums">
            {{ entry.ownedValue.toFixed(2) }} €
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
