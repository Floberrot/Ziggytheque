<script setup lang="ts">
import { useStats } from '@/composables/queries/useStatsQueries'

const { data: stats, isLoading } = useStats()
</script>

<template>
  <div class="p-4 lg:p-6">
    <h1 class="heading-xl mb-6">Dashboard</h1>
    <div v-if="isLoading" class="grid gap-4 grid-cols-1 md:grid-cols-2 lg:grid-cols-4">
      <ASkeleton aspect="auto" height="120px" />
      <ASkeleton aspect="auto" height="120px" />
      <ASkeleton aspect="auto" height="120px" />
      <ASkeleton aspect="auto" height="120px" />
    </div>
    <div v-else-if="stats" class="grid gap-4 grid-cols-1 md:grid-cols-2 lg:grid-cols-4">
      <MStatCard title="Owned" :value="stats.totalOwned" icon="lucide:check" />
      <MStatCard title="Read" :value="stats.totalRead" icon="lucide:eye" />
      <MStatCard title="Wishlist" :value="stats.totalWishlist" icon="lucide:heart" />
      <MStatCard title="Collection Value" :value="stats.ownedValue" unit="€" />
    </div>
  </div>
</template>
