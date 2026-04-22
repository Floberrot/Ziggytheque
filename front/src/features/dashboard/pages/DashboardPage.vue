<script setup lang="ts">
import { useStats } from '@/composables/queries/useStatsQueries'

const { data: stats, isLoading: statsLoading } = useStats()
</script>

<template>
  <div class="p-4 lg:p-6 space-y-8">
    <div>
      <h1 class="heading-xl mb-6">Dashboard</h1>
      <div v-if="statsLoading" class="grid gap-4 grid-cols-1 md:grid-cols-2 lg:grid-cols-4">
        <ASkeleton v-for="i in 4" :key="i" height="120px" />
      </div>
      <div v-else-if="stats" class="grid gap-4 grid-cols-1 md:grid-cols-2 lg:grid-cols-4">
        <MStatCard title="Total Mangas" :value="stats.totalMangas" icon="lucide:book" />
        <MStatCard title="Owned Volumes" :value="stats.totalOwned" icon="lucide:check" />
        <MStatCard title="Read Volumes" :value="stats.totalRead" icon="lucide:eye" />
        <MStatCard title="Total Value" :value="`€${stats.totalValue}`" icon="lucide:coin" />
      </div>
    </div>

    <div v-if="stats" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <div class="card bg-base-200 p-6">
        <h2 class="heading-md mb-4">Collection Value</h2>
        <div class="text-4xl font-bold text-primary mb-2">€{{ stats.ownedValue }}</div>
        <div class="text-sm text-base-content/70">Wishlist: €{{ stats.wishlistValue }}</div>
      </div>

      <div class="card bg-base-200 p-6">
        <h2 class="heading-md mb-4">Top Genres</h2>
        <div class="space-y-2">
          <div
            v-for="(count, genre) in Object.entries(stats.genreBreakdown).slice(0, 5)"
            :key="genre"
            class="flex justify-between items-center text-sm"
          >
            <span>{{ genre }}</span>
            <ABadge>{{ count }}</ABadge>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
