<script setup lang="ts">
import { useRouter } from 'vue-router'
import type { CollectionEntry } from '@/types'

const props = defineProps<{ entry: CollectionEntry }>()
const router = useRouter()

const ownedRatio = computed(() =>
  props.entry.totalVolumes > 0
    ? Math.round((props.entry.ownedCount / props.entry.totalVolumes) * 100)
    : 0,
)

import { computed } from 'vue'

function open() {
  router.push({ name: 'collection-detail', params: { id: props.entry.id } })
}
</script>

<template>
  <div
    class="manga-card card bg-base-100 shadow cursor-pointer"
    @click="open"
  >
    <!-- Cover -->
    <figure class="aspect-[2/3] overflow-hidden bg-base-200">
      <img
        v-if="entry.manga.coverUrl"
        :src="entry.manga.coverUrl"
        :alt="entry.manga.title"
        class="w-full h-full object-cover"
      />
      <div v-else class="w-full h-full flex items-center justify-center text-base-content/30 text-3xl">
        📚
      </div>
    </figure>

    <div class="card-body p-3 gap-1">
      <p class="font-semibold text-sm line-clamp-2 leading-tight">{{ entry.manga.title }}</p>
      <p class="text-xs text-base-content/60">{{ entry.manga.edition }}</p>
      <p class="text-xs text-base-content/40 uppercase tracking-wide">{{ entry.manga.language }}</p>

      <!-- Progress -->
      <div class="mt-2 space-y-0.5">
        <div class="flex justify-between text-xs text-base-content/60">
          <span>{{ entry.ownedCount }}/{{ entry.totalVolumes }}</span>
          <span>{{ ownedRatio }}%</span>
        </div>
        <progress class="progress progress-primary w-full h-1" :value="entry.ownedCount" :max="entry.totalVolumes" />
      </div>
    </div>
  </div>
</template>
