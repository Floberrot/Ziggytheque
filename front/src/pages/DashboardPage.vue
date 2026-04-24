<script setup lang="ts">
import { computed } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { useI18n } from 'vue-i18n'
import { Book } from 'lucide-vue-next'
import { getStats } from '@/api/stats'
import StatCard from '@/components/molecules/StatCard.vue'
import GenrePieChart from '@/components/molecules/GenrePieChart.vue'
import { coverUrl } from '@/utils/coverUrl'

const { t, locale } = useI18n()
const { data: stats, isPending } = useQuery({ queryKey: ['stats'], queryFn: getStats })

const readingProgress = computed(() => {
  if (!stats.value?.totalOwned) return 0
  return Math.round((stats.value.totalRead / stats.value.totalOwned) * 100)
})

const today = computed(() =>
  new Date().toLocaleDateString(locale.value === 'fr' ? 'fr-FR' : 'en-US', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  })
)
</script>

<template>
  <div class="p-4 sm:p-6 space-y-6 sm:space-y-8">
    <!-- Header -->
    <div>
      <p class="text-sm text-base-content/40 capitalize mb-0.5">{{ today }}</p>
      <h1 class="text-3xl font-bold tracking-tight">{{ t('dashboard.title') }}</h1>
    </div>

    <div v-if="isPending" class="flex justify-center py-20">
      <span class="loading loading-dots loading-lg text-primary" />
    </div>

    <template v-else-if="stats">
      <!-- KPI cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard :value="stats.totalMangas" :label="t('dashboard.totalMangas')" color="primary" icon="book" style="animation-delay: 0ms" />
        <StatCard :value="stats.totalOwned" :label="t('dashboard.ownedVolumes')" color="secondary" icon="layers" style="animation-delay: 80ms" />
        <StatCard :value="stats.totalRead" :label="t('dashboard.readVolumes')" color="success" icon="check" style="animation-delay: 160ms" />
        <StatCard :value="stats.totalWishlist" :label="t('dashboard.wishlist')" color="warning" icon="heart" style="animation-delay: 240ms" />
      </div>

      <!-- Charts + Values row -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Genre Donut Chart -->
        <div class="card bg-base-100 shadow-sm">
          <div class="card-body gap-3">
            <h2 class="text-xs font-semibold text-base-content/50 uppercase tracking-widest">
              {{ t('dashboard.genreBreakdown') }}
            </h2>
            <div v-if="Object.keys(stats.genreBreakdown).length" class="h-56">
              <GenrePieChart :breakdown="stats.genreBreakdown" />
            </div>
            <p v-else class="text-sm text-base-content/40 italic py-8 text-center">Aucune donnée</p>
          </div>
        </div>

        <!-- Value summary + Reading progress -->
        <div class="flex flex-col gap-4">
          <div class="card bg-base-100 shadow-sm flex-1">
            <div class="card-body gap-4">
              <h2 class="text-xs font-semibold text-base-content/50 uppercase tracking-widest">
                {{ t('dashboard.valueSummary') }}
              </h2>
              <div class="space-y-3">
                <div class="flex justify-between items-center">
                  <span class="text-sm text-base-content/60">{{ t('dashboard.ownedValue') }}</span>
                  <span class="font-semibold text-success">{{ stats.ownedValue.toFixed(2) }} €</span>
                </div>
                <div class="divider my-0" />
                <div class="flex justify-between items-center">
                  <span class="text-sm text-base-content/60">{{ t('dashboard.wishlistValue') }}</span>
                  <span class="font-semibold text-warning">{{ stats.wishlistValue.toFixed(2) }} €</span>
                </div>
                <div class="divider my-0" />
                <div class="flex justify-between items-center">
                  <span class="text-sm font-semibold">{{ t('dashboard.totalValue') }}</span>
                  <span class="text-xl font-bold">{{ stats.totalValue.toFixed(2) }} €</span>
                </div>
              </div>
            </div>
          </div>

          <div class="card bg-base-100 shadow-sm">
            <div class="card-body gap-3">
              <div class="flex justify-between items-center">
                <h2 class="text-xs font-semibold text-base-content/50 uppercase tracking-widest">
                  {{ t('dashboard.readingProgress') }}
                </h2>
                <span class="text-2xl font-bold text-primary">{{ readingProgress }}%</span>
              </div>
              <progress class="progress progress-primary w-full" :value="readingProgress" max="100" />
              <p class="text-xs text-base-content/40">
                {{ stats.totalRead }} {{ t('dashboard.volumesRead') }} / {{ stats.totalOwned }} {{ t('dashboard.volumesOwned') }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent additions -->
      <div class="card bg-base-100 shadow-sm">
        <div class="card-body gap-4">
          <h2 class="text-xs font-semibold text-base-content/50 uppercase tracking-widest">
            {{ t('dashboard.recentAdditions') }}
          </h2>
          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            <router-link
              v-for="(entry, i) in stats.recentAdditions"
              :key="entry.id"
              :to="`/collection/${entry.id}`"
              class="group flex flex-col items-center gap-2 p-3 rounded-xl hover:bg-base-200 transition-colors duration-200 recent-card"
              :style="`animation-delay: ${i * 60}ms`"
            >
              <div class="relative">
                <img
                  v-if="entry.manga.coverUrl"
                  :src="coverUrl(entry.manga.coverUrl)!"
                  :alt="entry.manga.title"
                  class="w-16 h-24 object-cover rounded-lg shadow-md group-hover:shadow-xl group-hover:-translate-y-1 transition-all duration-200"
                />
                <div
                  v-else
                  class="w-16 h-24 bg-base-300 rounded-lg flex items-center justify-center text-base-content/30 group-hover:shadow-xl group-hover:-translate-y-1 transition-all duration-200"
                >
                  <Book class="h-8 w-8" />
                </div>
                <div class="absolute -bottom-1.5 -right-1.5 badge badge-xs badge-primary font-semibold">
                  {{ entry.ownedCount }}/{{ entry.totalVolumes }}
                </div>
              </div>
              <p class="text-xs font-medium text-center line-clamp-2 leading-tight w-full">{{ entry.manga.title }}</p>
              <p class="text-xs text-base-content/40 text-center truncate w-full">{{ entry.manga.edition }}</p>
            </router-link>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.recent-card {
  animation: fadeInUp 0.4s ease-out both;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(14px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
