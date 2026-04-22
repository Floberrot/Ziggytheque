<script setup lang="ts">
import { useRoute } from 'vue-router'
import { useCollectionEntry } from '@/composables/queries/useCollectionQueries'

const route = useRoute()
const { data: entry, isLoading } = useCollectionEntry(route.params.id as string)
</script>

<template>
  <div class="p-4 lg:p-6">
    <template v-if="isLoading">
      <ASkeleton class="mb-4" height="200px" />
    </template>
    <template v-else-if="entry">
      <h1 class="heading-xl mb-4">{{ entry.manga.title }}</h1>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <MCoverImage :src="entry.manga.coverUrl" :alt="entry.manga.title" />
        <div class="md:col-span-3">
          <div class="space-y-4">
            <p>{{ entry.manga.summary }}</p>
            <div v-if="entry && entry.volumes.length" class="space-y-2">
              <h2 class="heading-md">Volumes</h2>
              <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                <div v-for="ve in entry.volumes" :key="ve.id" class="flex items-center gap-2 p-2 bg-base-200 rounded">
                  <span>{{ entry.manga.title }} {{ ve.number }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
