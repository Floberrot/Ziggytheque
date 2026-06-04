<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useQuery } from '@tanstack/vue-query'
import { useI18n } from 'vue-i18n'
import { BookMarked, Layers, BookOpen, Heart, ArrowRight, Library } from 'lucide-vue-next'
import { getShare } from '@/api/share'
import AppLogo from '@/components/atoms/AppLogo.vue'
import GenrePieChart from '@/components/molecules/GenrePieChart.vue'

const route = useRoute()
const { t, locale } = useI18n()

const token = computed(() => String(route.params.token ?? ''))

const { data: snapshot, isPending, isError } = useQuery({
  queryKey: ['share', token],
  queryFn: () => getShare(token.value),
  retry: false,
})

const readingProgress = computed(() => {
  const stats = snapshot.value?.stats
  if (!stats?.totalOwned) return 0
  return Math.round((stats.totalRead / stats.totalOwned) * 100)
})

const hasGenres = computed(
  () => snapshot.value && Object.keys(snapshot.value.stats.genreBreakdown).length > 0,
)

const snapshotDate = computed(() => {
  if (!snapshot.value) return ''
  return new Date(snapshot.value.createdAt).toLocaleDateString(
    locale.value === 'fr' ? 'fr-FR' : 'en-US',
    { day: 'numeric', month: 'long', year: 'numeric' },
  )
})

const tiles = computed(() => {
  const stats = snapshot.value?.stats
  if (!stats) return []
  return [
    { key: 'totalMangas', value: stats.totalMangas, label: t('dashboard.totalMangas'), icon: BookMarked, color: 'text-primary' },
    { key: 'totalOwned', value: stats.totalOwned, label: t('dashboard.ownedVolumes'), icon: Layers, color: 'text-secondary' },
    { key: 'totalRead', value: stats.totalRead, label: t('dashboard.readVolumes'), icon: BookOpen, color: 'text-success' },
    { key: 'totalWishlist', value: stats.totalWishlist, label: t('dashboard.wishlist'), icon: Heart, color: 'text-warning' },
  ]
})
</script>

<template>
  <div class="min-h-dvh bg-base-200/40">
    <!-- Top bar -->
    <header class="sticky top-0 z-10 border-b border-base-200 bg-base-100/80 backdrop-blur">
      <div class="mx-auto max-w-3xl px-5 py-3 flex items-center justify-between">
        <AppLogo class="h-8" />
        <router-link to="/login" class="btn btn-ghost btn-sm">{{ t('share.public.signIn') }}</router-link>
      </div>
    </header>

    <main class="mx-auto max-w-3xl px-5 py-8 sm:py-12">
      <!-- Loading -->
      <div v-if="isPending" class="flex justify-center py-24">
        <span class="loading loading-dots loading-lg text-primary" />
      </div>

      <!-- Not found -->
      <div v-else-if="isError || !snapshot" class="text-center py-20 space-y-4">
        <div class="mx-auto w-16 h-16 rounded-2xl bg-base-200 flex items-center justify-center text-base-content/30">
          <Library class="h-8 w-8" />
        </div>
        <h1 class="text-2xl font-bold">{{ t('share.public.notFoundTitle') }}</h1>
        <p class="text-base-content/50">{{ t('share.public.notFoundBody') }}</p>
        <router-link to="/login" class="btn btn-primary btn-sm mt-2">{{ t('share.public.signIn') }}</router-link>
      </div>

      <!-- Snapshot -->
      <div v-else class="space-y-7">
        <!-- Hero -->
        <div class="text-center space-y-2">
          <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary/70">
            {{ t('share.public.eyebrow') }}
          </p>
          <h1 class="text-3xl sm:text-4xl font-bold tracking-tight">
            {{ t('share.public.heroTitle', { name: snapshot.ownerName }) }}
          </h1>
          <p class="text-sm text-base-content/45">{{ t('share.public.capturedOn', { date: snapshotDate }) }}</p>
        </div>

        <!-- KPI tiles -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
          <div
            v-for="tile in tiles"
            :key="tile.key"
            class="card bg-base-100 shadow-sm"
          >
            <div class="card-body p-4 items-center text-center gap-1.5">
              <component :is="tile.icon" class="h-5 w-5" :class="tile.color" />
              <p class="text-2xl sm:text-3xl font-bold tracking-tight leading-none">{{ tile.value }}</p>
              <p class="text-[10px] sm:text-xs uppercase tracking-wide text-base-content/45">{{ tile.label }}</p>
            </div>
          </div>
        </div>

        <!-- Reading progress -->
        <div class="card bg-base-100 shadow-sm">
          <div class="card-body gap-3">
            <div class="flex items-center justify-between">
              <h2 class="text-xs font-semibold text-base-content/50 uppercase tracking-widest">
                {{ t('dashboard.readingProgress') }}
              </h2>
              <span class="text-2xl font-bold text-primary">{{ readingProgress }}%</span>
            </div>
            <progress class="progress progress-primary w-full" :value="readingProgress" max="100" />
            <p class="text-xs text-base-content/40">
              {{ snapshot.stats.totalRead }} {{ t('dashboard.volumesRead') }} /
              {{ snapshot.stats.totalOwned }} {{ t('dashboard.volumesOwned') }}
            </p>
          </div>
        </div>

        <!-- Genre donut -->
        <div v-if="hasGenres" class="card bg-base-100 shadow-sm">
          <div class="card-body gap-4">
            <h2 class="text-xs font-semibold text-base-content/50 uppercase tracking-widest">
              {{ t('dashboard.genreBreakdown') }}
            </h2>
            <GenrePieChart :breakdown="snapshot.stats.genreBreakdown" />
          </div>
        </div>

        <!-- CTA -->
        <div class="card bg-gradient-to-br from-primary/15 via-base-100 to-secondary/10 border border-primary/15 shadow-sm">
          <div class="card-body items-center text-center gap-3 py-8">
            <h2 class="text-xl font-bold">{{ t('share.public.ctaTitle') }}</h2>
            <p class="text-sm text-base-content/55 max-w-sm">{{ t('share.public.ctaBody') }}</p>
            <router-link to="/register" class="btn btn-primary gap-2 mt-1">
              {{ t('share.public.ctaButton') }}
              <ArrowRight class="h-4 w-4" />
            </router-link>
          </div>
        </div>

        <p class="text-center text-xs text-base-content/30 pt-2">{{ t('share.public.footer') }}</p>
      </div>
    </main>
  </div>
</template>
