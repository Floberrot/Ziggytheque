<script setup lang="ts">
import { ref } from 'vue'
import { useCollectionList } from '@/composables/queries/useCollectionQueries'

const { data: entries, isLoading } = useCollectionList()
const filterGenre = ref('')

const filteredEntries = () => {
  if (!filterGenre.value || !entries?.value) return entries?.value || []
  return (entries.value as any[]).filter((e: any) => !filterGenre.value || e.manga.genre?.includes(filterGenre.value))
}
</script>

<template>
  <div class="p-4 lg:p-6">
    <div class="mb-6">
      <h1 class="heading-xl">Collection</h1>
      <p class="text-sm text-base-content/70">{{ entries?.length ?? 0 }} series</p>
    </div>

    <div class="mb-6">
      <AInput
        v-model="filterGenre"
        type="search"
        placeholder="Filter by genre..."
        label="Filter"
      />
    </div>

    <div v-if="isLoading" class="grid gap-4 grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
      <ASkeleton v-for="i in 8" :key="i" aspect="2/3" />
    </div>

    <div v-else-if="filteredEntries()?.length" class="grid gap-4 grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
      <RouterLink
        v-for="entry in filteredEntries()"
        :key="entry.id"
        :to="`/collection/${entry.id}`"
        class="group relative overflow-hidden rounded-lg"
      >
        <MCoverImage :src="entry.manga.coverUrl" :alt="entry.manga.title" />
        <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-2">
          <p class="text-xs font-semibold text-white line-clamp-2">{{ entry.manga.title }}</p>
        </div>
      </RouterLink>
    </div>

    <AEmptyState v-else icon="lucide:inbox" title="No entries" description="Your collection is empty or no matches found" />
  </div>
</template>
