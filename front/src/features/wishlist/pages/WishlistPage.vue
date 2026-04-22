<script setup lang="ts">
import { useWishlistList } from '@/composables/queries/useWishlistQueries'

const { data: wishlist, isLoading } = useWishlistList()
</script>

<template>
  <div class="p-4 lg:p-6">
    <h1 class="heading-xl mb-6">Wishlist</h1>
    <div v-if="isLoading" class="space-y-2">
      <ASkeleton height="80px" />
      <ASkeleton height="80px" />
      <ASkeleton height="80px" />
    </div>
    <div v-else-if="wishlist && wishlist.length" class="space-y-2">
      <div v-for="entry in wishlist" :key="entry.id" class="card bg-base-200 p-4">
        <h3>{{ entry.manga.title }}</h3>
        <p class="text-sm text-base-content/70">{{ entry.wishedCount }} volumes wished</p>
      </div>
    </div>
    <AEmptyState v-else icon="lucide:heart" title="Wishlist empty" description="Add items to your wishlist" />
  </div>
</template>
