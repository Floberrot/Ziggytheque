<script setup lang="ts">
import { useCollectionList } from '@/composables/queries/useCollectionQueries'

const { data: entries, isLoading } = useCollectionList()
</script>

<template>
  <div class="p-4 lg:p-6">
    <h1 class="heading-xl mb-6">Collection</h1>
    <div v-if="isLoading" class="grid gap-4 grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
      <ASkeleton aspect="2/3" />
      <ASkeleton aspect="2/3" />
      <ASkeleton aspect="2/3" />
      <ASkeleton aspect="2/3" />
    </div>
    <div v-else-if="entries && entries.length" class="grid gap-4 grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
      <RouterLink
        v-for="entry in entries"
        :key="entry.id"
        :to="`/collection/${entry.id}`"
        class="card bg-base-200 overflow-hidden hover:shadow-lg transition-shadow"
      >
        <MCoverImage :src="entry.manga.coverUrl" :alt="entry.manga.title" />
      </RouterLink>
    </div>
    <AEmptyState v-else icon="lucide:inbox" title="No entries" description="Your collection is empty" />
  </div>
</template>
