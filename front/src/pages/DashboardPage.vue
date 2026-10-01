<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { useI18n } from 'vue-i18n'
import { Share2, Star, TrendingUp, CalendarRange, ListChecks, Wallet, Users } from 'lucide-vue-next'
import { getStats } from '@/api/stats'
import { createShare } from '@/api/share'
import { removeFromCollection, toggleFollow, updateCollectionRating } from '@/api/collection'
import { useLongPress } from '@/composables/useLongPress'
import { useUiStore } from '@/stores/useUiStore'
import type { CollectionEntry, QuickActionRequest } from '@/types'
import StatCard from '@/components/molecules/StatCard.vue'
import GenrePieChart from '@/components/molecules/GenrePieChart.vue'
import MonthlyAdditionsChart from '@/components/molecules/MonthlyAdditionsChart.vue'
import ReadingStatusBar from '@/components/molecules/ReadingStatusBar.vue'
import TopAuthorsList from '@/components/molecules/TopAuthorsList.vue'
import ShareModal from '@/components/organisms/ShareModal.vue'
import CollectionQuickActions from '@/components/organisms/CollectionQuickActions.vue'
import BaseLoader from '@/components/atoms/BaseLoader.vue'
import BaseCover from '@/components/atoms/BaseCover.vue'
import { editionLabel } from '@/utils/edition'

const { t, locale } = useI18n()
const { data: stats, isPending } = useQuery({ queryKey: ['stats'], queryFn: getStats })

const shareOpen = ref(false)
const shareUrl = ref<string | null>(null)

const shareMutation = useMutation({
  mutationFn: createShare,
  onSuccess: (data) => {
    shareUrl.value = data.url
  },
})

function openShare(): void {
  shareUrl.value = null
  shareOpen.value = true
  shareMutation.mutate()
}

const readingProgress = computed(() => {
  if (!stats.value?.totalOwned) return 0
  return Math.round((stats.value.totalRead / stats.value.totalOwned) * 100)
})

const hasGenres = computed(() => stats.value && Object.keys(stats.value.genreBreakdown).length > 0)

// ── Quick actions on the recent additions (right click / long press) ─────────

const router = useRouter()
const queryClient = useQueryClient()
const ui = useUiStore()
const quickActionRequest = ref<QuickActionRequest | null>(null)
let pressedEntry: CollectionEntry | null = null

const longPress = useLongPress((point) => {
  if (pressedEntry) quickActionRequest.value = { entry: pressedEntry, point, source: 'touch' }
})

function onTileTouchStart(event: TouchEvent, entry: CollectionEntry): void {
  pressedEntry = entry
  longPress.onTouchStart(event)
}

function onTileContextMenu(event: MouseEvent, entry: CollectionEntry): void {
  event.preventDefault()
  const point = { x: event.clientX, y: event.clientY }
  if (longPress.isTouching()) {
    if (longPress.markHandled()) quickActionRequest.value = { entry, point, source: 'touch' }
    return
  }
  quickActionRequest.value = { entry, point, source: 'mouse' }
}

/** The click that ends a long press must not follow the link. */
function onTileClick(event: MouseEvent): void {
  if (longPress.swallowClick()) event.preventDefault()
}

function refreshCollection(): void {
  queryClient.invalidateQueries({ queryKey: ['collection'] })
  queryClient.invalidateQueries({ queryKey: ['stats'] })
}

function patchRequestedEntry(entryId: string, patch: Partial<CollectionEntry>): void {
  const request = quickActionRequest.value
  if (request?.entry.id === entryId) {
    quickActionRequest.value = { ...request, entry: { ...request.entry, ...patch } }
  }
}

const followMutation = useMutation({
  mutationFn: (entry: CollectionEntry) => toggleFollow(entry.id),
  onSuccess: (result, entry) => {
    patchRequestedEntry(entry.id, { notificationsEnabled: result.notificationsEnabled })
    ui.addToast(t(result.notificationsEnabled ? 'quickActions.followed' : 'quickActions.unfollowed'), 'success')
    refreshCollection()
  },
  onError: () => ui.addToast(t('quickActions.error'), 'error'),
})

const rateMutation = useMutation({
  mutationFn: ({ entry, rating }: { entry: CollectionEntry; rating: number }) => updateCollectionRating(entry.id, rating),
  onSuccess: (_, { entry, rating }) => {
    patchRequestedEntry(entry.id, { rating })
    ui.addToast(t('quickActions.rated'), 'success')
    refreshCollection()
  },
  onError: () => ui.addToast(t('quickActions.error'), 'error'),
})

const removeMutation = useMutation({
  mutationFn: (entry: CollectionEntry) => removeFromCollection(entry.id),
  onSuccess: () => {
    quickActionRequest.value = null
    ui.addToast(t('collection.removed'), 'success')
    refreshCollection()
  },
  onError: () => ui.addToast(t('quickActions.error'), 'error'),
})

const quickActionBusy = computed(
  () => followMutation.isPending.value || rateMutation.isPending.value || removeMutation.isPending.value,
)

function openEntry(entry: CollectionEntry): void {
  quickActionRequest.value = null
  router.push({ name: 'collection-detail', params: { id: entry.id } })
}

const today = computed(() =>
  new Date().toLocaleDateString(locale.value === 'fr' ? 'fr-FR' : 'en-US', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  }),
)
</script>

<template>
  <div class="p-4 sm:p-6 space-y-6 sm:space-y-7">
    <!-- Header -->
    <div class="flex items-start justify-between gap-4">
      <div>
        <p class="text-sm text-base-content/40 capitalize mb-0.5">{{ today }}</p>
        <h1 class="text-3xl font-bold tracking-tight">{{ t('dashboard.title') }}</h1>
      </div>
      <button class="btn btn-primary gap-2 shadow-sm" @click="openShare">
        <Share2 class="h-4 w-4" />
        <span class="hidden sm:inline">{{ t('share.button') }}</span>
      </button>
    </div>

    <div v-if="isPending" class="flex justify-center py-20">
      <BaseLoader size="lg" class="text-primary" />
    </div>

    <template v-else-if="stats">
      <!-- KPI cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard :value="stats.totalMangas" :label="t('dashboard.totalMangas')" color="primary" icon="book" style="animation-delay: 0ms" />
        <StatCard :value="stats.totalOwned" :label="t('dashboard.ownedVolumes')" color="secondary" icon="layers" style="animation-delay: 80ms" />
        <StatCard :value="stats.totalRead" :label="t('dashboard.readVolumes')" color="success" icon="check" style="animation-delay: 160ms" />
        <StatCard :value="stats.totalWishlist" :label="t('dashboard.wishlist')" color="warning" icon="heart" style="animation-delay: 240ms" />
      </div>

      <!-- Genre + reading progress -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="card bg-base-100 shadow-sm lg:col-span-2">
          <div class="card-body gap-4">
            <h2 class="text-xs font-semibold text-base-content/50 uppercase tracking-widest">
              {{ t('dashboard.genreBreakdown') }}
            </h2>
            <GenrePieChart v-if="hasGenres" :breakdown="stats.genreBreakdown" />
            <p v-else class="text-sm text-base-content/40 italic py-8 text-center">{{ t('common.noData') }}</p>
          </div>
        </div>

        <div class="card bg-base-100 shadow-sm">
          <div class="card-body items-center gap-4">
            <h2 class="self-start flex items-center gap-1.5 text-xs font-semibold text-base-content/50 uppercase tracking-widest">
              <TrendingUp class="h-3.5 w-3.5" /> {{ t('dashboard.readingProgress') }}
            </h2>
            <div
              class="radial-progress text-primary"
              :style="`--value:${readingProgress}; --size:8.5rem; --thickness:0.7rem`"
              role="progressbar"
            >
              <span class="text-3xl font-bold">{{ readingProgress }}<span class="text-lg">%</span></span>
            </div>
            <p class="text-xs text-base-content/45 text-center -mt-1">
              {{ stats.totalRead }} {{ t('dashboard.volumesRead') }} / {{ stats.totalOwned }} {{ t('dashboard.volumesOwned') }}
            </p>
            <div class="w-full divider my-0" />
            <div class="flex items-center justify-between w-full">
              <span class="flex items-center gap-1.5 text-sm text-base-content/60">
                <Star class="h-4 w-4 text-warning" /> {{ t('dashboard.averageRating') }}
              </span>
              <span class="font-bold">
                <template v-if="stats.averageRating !== null">
                  {{ stats.averageRating.toFixed(1) }}<span class="text-sm text-base-content/40">/10</span>
                </template>
                <template v-else>—</template>
              </span>
            </div>
            <p v-if="stats.ratedCount" class="text-[11px] text-base-content/35 -mt-2 self-end">
              {{ t('dashboard.ratedCount', { count: stats.ratedCount }) }}
            </p>
          </div>
        </div>
      </div>

      <!-- Monthly additions + reading status -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="card bg-base-100 shadow-sm lg:col-span-2">
          <div class="card-body gap-4">
            <h2 class="flex items-center gap-1.5 text-xs font-semibold text-base-content/50 uppercase tracking-widest">
              <CalendarRange class="h-3.5 w-3.5" /> {{ t('dashboard.monthlyAdditions') }}
            </h2>
            <MonthlyAdditionsChart :data="stats.monthlyAdditions">
              <template #caption>{{ t('dashboard.addedLast12Months') }}</template>
            </MonthlyAdditionsChart>
          </div>
        </div>

        <div class="card bg-base-100 shadow-sm">
          <div class="card-body gap-4">
            <h2 class="flex items-center gap-1.5 text-xs font-semibold text-base-content/50 uppercase tracking-widest">
              <ListChecks class="h-3.5 w-3.5" /> {{ t('dashboard.readingStatus') }}
            </h2>
            <ReadingStatusBar :breakdown="stats.readingStatusBreakdown" />
          </div>
        </div>
      </div>

      <!-- Value summary + top authors -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="card bg-base-100 shadow-sm">
          <div class="card-body gap-4">
            <h2 class="flex items-center gap-1.5 text-xs font-semibold text-base-content/50 uppercase tracking-widest">
              <Wallet class="h-3.5 w-3.5" /> {{ t('dashboard.valueSummary') }}
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

        <div class="card bg-base-100 shadow-sm lg:col-span-2">
          <div class="card-body gap-4">
            <h2 class="flex items-center gap-1.5 text-xs font-semibold text-base-content/50 uppercase tracking-widest">
              <Users class="h-3.5 w-3.5" /> {{ t('dashboard.topAuthors') }}
            </h2>
            <TopAuthorsList :authors="stats.topAuthors" />
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
              class="group flex flex-col items-center gap-2 p-3 rounded-xl hover:bg-base-200 transition-colors duration-200 recent-card select-none"
              :style="`animation-delay: ${i * 60}ms`"
              @click.capture="onTileClick"
              @contextmenu="onTileContextMenu($event, entry)"
              @touchstart.passive="onTileTouchStart($event, entry)"
              @touchmove.passive="longPress.onTouchMove"
              @touchend="longPress.onTouchEnd"
              @touchcancel="longPress.onTouchCancel"
            >
              <div class="relative">
                <BaseCover
                  :src="entry.manga.coverUrl"
                  :alt="entry.manga.title"
                  class="w-16 h-24 rounded-lg shadow-md group-hover:shadow-xl group-hover:-translate-y-1 transition-all duration-200"
                  icon-class="h-8 w-8"
                />
                <div class="absolute -bottom-1.5 -right-1.5 badge badge-xs badge-primary font-semibold">
                  {{ entry.ownedCount }}/{{ entry.totalVolumes }}
                </div>
              </div>
              <p class="text-xs font-medium text-center line-clamp-2 leading-tight w-full">{{ entry.manga.title }}</p>
              <p class="text-xs text-base-content/40 text-center truncate w-full">
                {{ editionLabel(entry.manga.edition, entry.manga.specialEdition) }}
              </p>
            </router-link>
          </div>
        </div>
      </div>
    </template>

    <ShareModal :open="shareOpen" :url="shareUrl" :loading="shareMutation.isPending.value" :stats="stats" @close="shareOpen = false" />

    <CollectionQuickActions
      :request="quickActionRequest"
      :busy="quickActionBusy"
      @close="quickActionRequest = null"
      @open="openEntry"
      @toggle-follow="followMutation.mutate($event)"
      @rate="(entry, rating) => rateMutation.mutate({ entry, rating })"
      @remove="removeMutation.mutate($event)"
    />
  </div>
</template>

<style scoped>
.recent-card {
  animation: fadeInUp 0.4s ease-out both;
  /* A long press opens the quick actions, not the link preview. */
  -webkit-touch-callout: none;
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
