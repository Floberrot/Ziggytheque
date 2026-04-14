<script setup lang="ts">
import { computed } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { getWishlist, removeFromWishlist, purchaseWishlistItem } from '@/api/wishlist'
import { useUiStore } from '@/stores/useUiStore'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import type { CollectionEntry } from '@/types'

const qc = useQueryClient()
const ui = useUiStore()
const { t } = useI18n()
const router = useRouter()

const { data: items, isPending } = useQuery({ queryKey: ['wishlist'], queryFn: getWishlist })

const totalWishlistVolumes = computed(() =>
  items.value?.reduce((s, e) => s + e.wishlistCount, 0) ?? 0,
)

const removeMutation = useMutation({
  mutationFn: (id: string) => removeFromWishlist(id),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['wishlist'] })
    qc.invalidateQueries({ queryKey: ['collection'] })
    qc.invalidateQueries({ queryKey: ['stats'] })
    ui.addToast(t('wishlist.removed'), 'success')
  },
})

const purchaseMutation = useMutation({
  mutationFn: (id: string) => purchaseWishlistItem(id),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['wishlist'] })
    qc.invalidateQueries({ queryKey: ['collection'] })
    qc.invalidateQueries({ queryKey: ['stats'] })
    ui.addToast(t('wishlist.purchased'), 'success')
  },
})

// Compute the visual volume dots for each entry
function getVolumeDots(entry: CollectionEntry) {
  const total = entry.totalVolumes
  if (total === 0) return []
  const shown = Math.min(total, 30)
  return Array.from({ length: shown }, (_, i) => {
    if (i < entry.ownedCount) return 'owned'
    if (i < entry.ownedCount + entry.wishlistCount) return 'wishlist'
    return 'none'
  })
}

function openDetail(id: string) {
  router.push({ name: 'collection-detail', params: { id } })
}
</script>

<template>
  <div class="p-4 md:p-6 space-y-6 max-w-screen-xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div>
        <h1 class="text-3xl font-bold tracking-tight">{{ t('wishlist.title') }}</h1>
        <p v-if="!isPending && items?.length" class="text-sm text-base-content/50 mt-1">
          {{ items.length }} {{ t('wishlist.oeuvres') }} &bull;
          <span class="text-warning font-medium">{{ totalWishlistVolumes }}</span>
          {{ t('wishlist.volumesToBuy') }}
        </p>
      </div>
      <RouterLink to="/add" class="btn btn-outline btn-warning gap-2 shrink-0">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        {{ t('wishlist.addToWishlist') }}
      </RouterLink>
    </div>

    <!-- Loading -->
    <div v-if="isPending" class="flex justify-center py-20">
      <span class="loading loading-spinner loading-lg text-warning" />
    </div>

    <!-- Empty state -->
    <div
      v-else-if="!items?.length"
      class="flex flex-col items-center justify-center py-24 gap-4 text-base-content/40"
    >
      <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
      </svg>
      <p class="text-lg font-medium">{{ t('wishlist.empty') }}</p>
      <RouterLink to="/add" class="btn btn-warning btn-sm">
        {{ t('wishlist.addToWishlist') }}
      </RouterLink>
    </div>

    <!-- Wishlist cards grid -->
    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
      <div
        v-for="entry in items"
        :key="entry.id"
        class="group relative overflow-hidden rounded-2xl bg-base-100 border border-warning/20
               shadow-md transition-all duration-300 hover:shadow-xl hover:-translate-y-0.5
               hover:border-warning/40"
      >
        <!-- Accent line at top -->
        <div class="absolute inset-x-0 top-0 h-0.5 bg-gradient-to-r from-warning/60 via-warning to-warning/60" />

        <div class="flex gap-4 p-4">
          <!-- Cover -->
          <div
            class="shrink-0 w-20 rounded-xl overflow-hidden bg-base-200 shadow cursor-pointer
                   transition-transform duration-300 group-hover:scale-105"
            @click="openDetail(entry.id)"
          >
            <div class="aspect-[2/3]">
              <img
                v-if="entry.manga.coverUrl"
                :src="entry.manga.coverUrl"
                :alt="entry.manga.title"
                class="w-full h-full object-cover"
                loading="lazy"
              />
              <div v-else class="w-full h-full flex items-center justify-center text-2xl text-base-content/20">
                📚
              </div>
            </div>
          </div>

          <!-- Info -->
          <div class="flex-1 min-w-0 space-y-2">
            <div>
              <button
                class="font-bold leading-tight line-clamp-2 text-left hover:text-primary transition-colors"
                @click="openDetail(entry.id)"
              >
                {{ entry.manga.title }}
              </button>
              <p class="text-sm text-base-content/60 mt-0.5">{{ entry.manga.edition }}</p>
            </div>

            <!-- Volume dots -->
            <div class="flex flex-wrap gap-0.5" :title="`${entry.ownedCount} possédés, ${entry.wishlistCount} souhaités`">
              <div
                v-for="(dot, i) in getVolumeDots(entry)"
                :key="i"
                class="w-2.5 h-2.5 rounded-full transition-colors"
                :class="{
                  'bg-success': dot === 'owned',
                  'bg-warning': dot === 'wishlist',
                  'bg-base-300': dot === 'none',
                }"
              />
              <span v-if="entry.totalVolumes > 30" class="text-xs text-base-content/30 self-center ml-1">
                +{{ entry.totalVolumes - 30 }}
              </span>
            </div>

            <!-- Volume counts -->
            <div class="flex gap-3 text-xs">
              <span v-if="entry.ownedCount > 0" class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-success inline-block" />
                <span class="text-base-content/60">{{ entry.ownedCount }} {{ t('collection.owned') }}</span>
              </span>
              <span class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-warning inline-block" />
                <span class="text-warning font-semibold">{{ entry.wishlistCount }}</span>
                <span class="text-base-content/60">{{ t('wishlist.toGet') }}</span>
              </span>
            </div>

            <!-- Actions -->
            <div class="flex gap-2 pt-1">
              <button
                class="btn btn-warning btn-sm flex-1 gap-1.5 font-semibold"
                :class="{ loading: purchaseMutation.isPending.value }"
                :disabled="purchaseMutation.isPending.value"
                @click="purchaseMutation.mutate(entry.id)"
              >
                <svg v-if="!purchaseMutation.isPending.value" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                {{ t('wishlist.purchase') }}
              </button>
              <button
                class="btn btn-ghost btn-sm btn-square text-base-content/40 hover:text-error hover:bg-error/10"
                :title="t('wishlist.removeFromWishlist')"
                @click="removeMutation.mutate(entry.id)"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
