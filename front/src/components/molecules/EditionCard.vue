<script setup lang="ts">
import { Book } from 'lucide-vue-next'
import type { DiscoveredEdition } from '@/api/manga'
import { coverUrl } from '@/utils/coverUrl'

const props = defineProps<{
  edition: DiscoveredEdition
}>()

const emit = defineEmits<{
  select: []
}>()
</script>

<template>
  <button
    class="group flex flex-col items-center gap-2 text-left w-full focus:outline-none"
    @click="emit('select')"
  >
    <div
      class="w-full aspect-[2/3] relative rounded-xl overflow-hidden bg-base-200 shadow group-hover:shadow-lg group-hover:scale-105 transition-all duration-150 ring-2 ring-transparent group-hover:ring-primary"
    >
      <img
        v-if="props.edition.coverUrl"
        :src="coverUrl(props.edition.coverUrl)!"
        :alt="props.edition.publisher"
        class="w-full h-full object-cover"
      />
      <div
        v-else
        class="w-full h-full flex items-center justify-center opacity-30 text-base-content"
      >
        <Book class="h-10 w-10" stroke-width="1.5" />
      </div>
      <div
        v-if="props.edition.volumeCount"
        class="absolute bottom-1 right-1 bg-black/70 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full leading-none"
      >
        {{ props.edition.volumeCount }}T
      </div>
    </div>
    <div class="w-full px-0.5 space-y-0.5">
      <p class="text-xs font-semibold leading-tight line-clamp-2">{{ props.edition.publisher }}</p>
      <p v-if="props.edition.editionLabel" class="text-[10px] text-base-content/60 truncate">
        {{ props.edition.editionLabel }}
      </p>
      <p class="text-[10px] text-base-content/40 truncate">
        <span v-if="props.edition.year">{{ props.edition.year }}</span>
        <span v-if="props.edition.year && props.edition.source"> · </span>
        <span>{{ props.edition.source }}</span>
      </p>
    </div>
  </button>
</template>
