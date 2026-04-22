<script setup lang="ts">
import { useRoute, useRouter } from 'vue-router'
import { useCollectionEntry, useRemoveFromCollection, useToggleVolume } from '@/composables/queries/useCollectionQueries'

const route = useRoute()
const router = useRouter()
const { data: entry, isLoading } = useCollectionEntry(route.params.id as string)
const { mutate: removeFromCollection } = useRemoveFromCollection()
const { mutate: toggleVolume } = useToggleVolume()

function handleDelete() {
  if (confirm('Remove from collection?')) {
    removeFromCollection(route.params.id as string, {
      onSuccess: () => router.push('/collection'),
    })
  }
}

function toggleVolumeOwned(volumeId: string) {
  toggleVolume({
    collectionId: route.params.id as string,
    volumeEntryId: volumeId,
    field: 'isOwned',
  })
}
</script>

<template>
  <div class="p-4 lg:p-6">
    <div v-if="isLoading" class="space-y-4">
      <ASkeleton height="200px" />
      <ASkeleton height="100px" />
    </div>

    <div v-else-if="entry" class="space-y-6">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <MCoverImage :src="entry.manga.coverUrl" :alt="entry.manga.title" />
        <div class="md:col-span-3">
          <h1 class="heading-xl mb-2">{{ entry.manga.title }}</h1>
          <div class="space-y-4 text-sm">
            <div><strong>Author:</strong> {{ entry.manga.author }}</div>
            <div><strong>Status:</strong> <ABadge>{{ entry.readingStatus }}</ABadge></div>
            <div><strong>Genre:</strong> {{ entry.manga.genre }}</div>
            <p class="text-base-content/80">{{ entry.manga.summary }}</p>
            <div class="flex gap-2 pt-4">
              <AButton @click="handleDelete" variant="danger">Delete</AButton>
            </div>
          </div>
        </div>
      </div>

      <div v-if="entry.volumes.length" class="space-y-4">
        <h2 class="heading-md">Volumes ({{ entry.volumes.length }})</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 max-h-96 overflow-y-auto">
          <div
            v-for="vol in entry.volumes"
            :key="vol.id"
            class="card bg-base-200 p-3 cursor-pointer hover:shadow-md transition-shadow"
            :class="{ 'ring-2 ring-success': vol.isOwned }"
            @click="toggleVolumeOwned(vol.id)"
          >
            <p class="font-semibold text-sm">Vol. {{ vol.number }}</p>
            <div class="flex gap-1 mt-2 text-xs">
              <ABadge v-if="vol.isOwned" variant="success">Owned</ABadge>
              <ABadge v-if="vol.isRead" variant="info">Read</ABadge>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
