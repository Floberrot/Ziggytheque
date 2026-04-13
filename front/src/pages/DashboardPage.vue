<script setup lang="ts">
import { useQuery } from '@tanstack/vue-query'
import { getStats } from '@/api/stats'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const { data: stats, isPending } = useQuery({ queryKey: ['stats'], queryFn: getStats })
</script>

<template>
  <div class="p-6 space-y-8">
    <h1 class="text-2xl font-bold">{{ t('dashboard.title') }}</h1>

    <div v-if="isPending" class="flex justify-center py-16">
      <span class="loading loading-spinner loading-lg" />
    </div>

    <template v-else-if="stats">
      <!-- KPI cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="stat bg-base-100 rounded-box shadow">
          <div class="stat-title">{{ t('dashboard.ownedVolumes') }}</div>
          <div class="stat-value text-primary">{{ stats.totalOwned }}</div>
        </div>
        <div class="stat bg-base-100 rounded-box shadow">
          <div class="stat-title">{{ t('dashboard.readVolumes') }}</div>
          <div class="stat-value text-secondary">{{ stats.totalRead }}</div>
        </div>
        <div class="stat bg-base-100 rounded-box shadow">
          <div class="stat-title">{{ t('dashboard.wishlist') }}</div>
          <div class="stat-value">{{ stats.totalWishlist }}</div>
        </div>
        <div class="stat bg-base-100 rounded-box shadow">
          <div class="stat-title">{{ t('dashboard.collectionValue') }}</div>
          <div class="stat-value text-success">{{ stats.collectionValue.toFixed(2) }}€</div>
        </div>
      </div>

      <!-- Genre breakdown -->
      <div class="card bg-base-100 shadow">
        <div class="card-body">
          <h2 class="card-title text-lg">{{ t('dashboard.genreBreakdown') }}</h2>
          <div class="space-y-2">
            <div
              v-for="(count, genre) in stats.genreBreakdown"
              :key="genre"
              class="flex items-center gap-3"
            >
              <span class="w-28 text-sm capitalize shrink-0">{{ genre }}</span>
              <progress
                class="progress progress-primary flex-1"
                :value="count"
                :max="stats.totalMangas"
              />
              <span class="w-6 text-sm text-right">{{ count }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent additions -->
      <div class="card bg-base-100 shadow">
        <div class="card-body">
          <h2 class="card-title text-lg">{{ t('dashboard.recentAdditions') }}</h2>
          <div class="divide-y divide-base-200">
            <div
              v-for="entry in stats.recentAdditions"
              :key="entry.id"
              class="py-3 flex items-center gap-4"
            >
              <img
                v-if="entry.manga.coverUrl"
                :src="entry.manga.coverUrl"
                :alt="entry.manga.title"
                class="w-10 h-14 object-cover rounded"
              />
              <div v-else class="w-10 h-14 bg-base-200 rounded flex items-center justify-center text-xs text-base-content/40">?</div>
              <div class="flex-1 min-w-0">
                <p class="font-medium truncate">{{ entry.manga.title }}</p>
                <p class="text-sm text-base-content/60">{{ entry.manga.edition }}</p>
              </div>
              <div class="text-right text-sm text-base-content/60">
                <div>{{ entry.ownedCount }}/{{ entry.totalVolumes }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>
