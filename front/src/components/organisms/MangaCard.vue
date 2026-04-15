<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import type { CollectionEntry } from '@/types'

const props = defineProps<{ entry: CollectionEntry }>()
const router = useRouter()

const ownedRatio = computed(() =>
  props.entry.totalVolumes > 0
    ? (props.entry.ownedCount / props.entry.totalVolumes) * 100
    : 0,
)

const wishedRatio = computed(() =>
  props.entry.totalVolumes > 0
    ? (props.entry.wishedCount / props.entry.totalVolumes) * 100
    : 0,
)

const completionClass = computed(() => {
  if (ownedRatio.value === 100) return 'ring-success'
  if (ownedRatio.value > 50) return 'ring-primary/60'
  if (props.entry.wishedCount > 0) return 'ring-warning/50'
  return 'ring-base-300/40'
})

function open() {
  router.push({ name: 'collection-detail', params: { id: props.entry.id } })
}
</script>

<template>
  <div
    class="manga-card group relative cursor-pointer select-none"
    @click="open"
  >
    <!-- Card container with glassmorphism on hover -->
    <div
      class="relative rounded-2xl overflow-hidden bg-base-100 shadow-md ring-2 transition-all duration-300 ease-out
             group-hover:shadow-2xl group-hover:scale-[1.04] group-hover:-translate-y-1"
      :class="completionClass"
    >
      <!-- Cover image -->
      <div class="aspect-[2/3] overflow-hidden bg-base-200">
        <img
          v-if="entry.manga.coverUrl"
          :src="entry.manga.coverUrl"
          :alt="entry.manga.title"
          class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
          loading="lazy"
        />
        <div v-else class="w-full h-full flex items-center justify-center text-4xl text-base-content/20">
          📚
        </div>
      </div>

      <!-- Gradient overlay always visible at bottom -->
      <div class="absolute inset-x-0 bottom-0 h-28 bg-gradient-to-t from-black/80 via-black/40 to-transparent pointer-events-none" />

      <!-- Bottom info (always visible) -->
      <div class="absolute inset-x-0 bottom-0 p-2.5 space-y-1.5">
        <p class="text-white text-xs font-semibold line-clamp-2 leading-tight drop-shadow">
          {{ entry.manga.title }}
        </p>
        <p class="text-white/60 text-[10px] truncate">{{ entry.manga.edition }}</p>

        <!-- Dual progress bar: owned (green) + wished (yellow) -->
        <div class="space-y-0.5">
          <div class="flex justify-between text-[10px] text-white/70">
            <span>
              <span class="text-success font-bold">{{ entry.ownedCount }}</span>
              <span v-if="entry.wishedCount > 0" class="text-warning font-bold"> +{{ entry.wishedCount }}✦</span>
              <span class="opacity-60"> / {{ entry.totalVolumes }}</span>
            </span>
          </div>
          <!-- Track -->
          <div class="relative w-full h-1.5 rounded-full bg-white/20 overflow-hidden">
            <!-- Owned segment -->
            <div
              class="absolute left-0 top-0 h-full rounded-full bg-success transition-all duration-500"
              :style="{ width: `${ownedRatio}%` }"
            />
            <!-- Wished segment (stacked after owned) -->
            <div
              class="absolute top-0 h-full rounded-full bg-warning/80 transition-all duration-500"
              :style="{ left: `${ownedRatio}%`, width: `${Math.min(wishedRatio, 100 - ownedRatio)}%` }"
            />
          </div>
        </div>
      </div>

      <!-- Hover overlay: genre badge + completion pill -->
      <div
        class="absolute inset-x-0 top-0 flex items-start justify-between p-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200"
      >
        <span
          v-if="entry.manga.genre"
          class="badge badge-xs bg-black/60 text-white border-none capitalize backdrop-blur-sm"
        >
          {{ entry.manga.genre }}
        </span>
        <span
          v-if="ownedRatio === 100"
          class="badge badge-xs badge-success border-none"
        >
          Complet
        </span>
      </div>
    </div>
  </div>
</template>
