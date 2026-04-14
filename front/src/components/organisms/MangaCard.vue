<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import type { CollectionEntry } from '@/types'

const props = defineProps<{
  entry: CollectionEntry
  mode?: 'collection' | 'wishlist'
}>()

const router = useRouter()

const ownedRatio = computed(() =>
  props.entry.totalVolumes > 0
    ? Math.round((props.entry.ownedCount / props.entry.totalVolumes) * 100)
    : 0,
)

const wishlistRatio = computed(() =>
  props.entry.totalVolumes > 0
    ? Math.round((props.entry.wishlistCount / props.entry.totalVolumes) * 100)
    : 0,
)

// Volume mini-dots: compact visual (max 25 shown)
const volumeDots = computed(() => {
  const total = props.entry.totalVolumes
  if (total === 0) return []
  const shown = Math.min(total, 25)
  // We only have ownedCount + wishlistCount from summary; fill dots left to right
  return Array.from({ length: shown }, (_, i) => {
    if (i < props.entry.ownedCount) return 'owned'
    if (i < props.entry.ownedCount + props.entry.wishlistCount) return 'wishlist'
    return 'none'
  })
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
    <!-- Card container with glass morphism + hover lift -->
    <div class="relative overflow-hidden rounded-2xl bg-base-100 shadow-md
                transition-all duration-300 ease-out
                hover:shadow-xl hover:-translate-y-1 hover:scale-[1.02]
                border border-base-200 hover:border-primary/30">

      <!-- Cover image -->
      <div class="relative aspect-[2/3] overflow-hidden bg-base-200">
        <img
          v-if="entry.manga.coverUrl"
          :src="entry.manga.coverUrl"
          :alt="entry.manga.title"
          class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
          loading="lazy"
        />
        <div
          v-else
          class="w-full h-full flex items-center justify-center text-4xl text-base-content/20"
        >
          📚
        </div>

        <!-- Gradient overlay at bottom -->
        <div class="absolute inset-x-0 bottom-0 h-2/3
                    bg-gradient-to-t from-black/80 via-black/30 to-transparent" />

        <!-- Wishlist badge -->
        <div
          v-if="mode === 'wishlist' || entry.wishlistCount > 0"
          class="absolute top-2 right-2"
        >
          <div class="badge badge-warning badge-sm gap-1 shadow font-semibold">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor">
              <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
            </svg>
            {{ entry.wishlistCount }}
          </div>
        </div>

        <!-- Title overlay on cover -->
        <div class="absolute inset-x-0 bottom-0 p-3">
          <p class="font-bold text-sm leading-tight text-white line-clamp-2 drop-shadow">
            {{ entry.manga.title }}
          </p>
          <p class="text-xs text-white/70 mt-0.5 truncate drop-shadow">{{ entry.manga.edition }}</p>
        </div>
      </div>

      <!-- Card body -->
      <div class="p-3 space-y-2">
        <!-- Genre badge -->
        <div class="flex items-center gap-1.5 flex-wrap">
          <span v-if="entry.manga.genre" class="badge badge-outline badge-xs capitalize opacity-70">
            {{ entry.manga.genre?.replace('_', ' ') }}
          </span>
          <span class="badge badge-outline badge-xs opacity-50 uppercase">
            {{ entry.manga.language }}
          </span>
        </div>

        <!-- Volume mini dots -->
        <div v-if="entry.totalVolumes > 0" class="flex flex-wrap gap-0.5">
          <div
            v-for="(dot, i) in volumeDots"
            :key="i"
            class="w-2 h-2 rounded-full transition-colors"
            :class="{
              'bg-success': dot === 'owned',
              'bg-warning': dot === 'wishlist',
              'bg-base-300': dot === 'none',
            }"
          />
          <span v-if="entry.totalVolumes > 25" class="text-xs text-base-content/30 self-center ml-0.5">
            +{{ entry.totalVolumes - 25 }}
          </span>
        </div>

        <!-- Progress bar + count -->
        <div class="space-y-1">
          <!-- Stacked bar: owned (green) + wishlist (orange) + missing (gray) -->
          <div class="w-full h-1.5 rounded-full bg-base-300 overflow-hidden flex">
            <div
              class="bg-success h-full rounded-l-full transition-all duration-500"
              :style="{ width: `${ownedRatio}%` }"
            />
            <div
              class="bg-warning h-full transition-all duration-500"
              :style="{ width: `${wishlistRatio}%` }"
            />
          </div>
          <div class="flex justify-between text-xs text-base-content/50">
            <span>
              <span class="text-success font-semibold">{{ entry.ownedCount }}</span>
              <span v-if="entry.wishlistCount > 0">
                + <span class="text-warning font-semibold">{{ entry.wishlistCount }}</span>
              </span>
              <span class="opacity-60"> / {{ entry.totalVolumes }}</span>
            </span>
            <span>{{ ownedRatio }}%</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
