<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { getCollectionEntry, removeFromCollection, updateReadingStatus, toggleVolume, wishlistRemaining } from '@/api/collection'
import { useUiStore } from '@/stores/useUiStore'
import { useI18n } from 'vue-i18n'
import type { ReadingStatus, VolumeEntry } from '@/types'

const route = useRoute()
const router = useRouter()
const qc = useQueryClient()
const ui = useUiStore()
const { t } = useI18n()

const id = route.params.id as string

const { data: entry, isPending } = useQuery({
  queryKey: ['collection', id],
  queryFn: () => getCollectionEntry(id),
})

const sortedVolumes = computed<VolumeEntry[]>(() =>
  [...(entry.value?.volumes ?? [])].sort((a, b) => a.number - b.number),
)

const wishlistCount = computed(() =>
  sortedVolumes.value.filter((ve) => ve.isWishlisted && !ve.isOwned).length,
)

const removeMutation = useMutation({
  mutationFn: () => removeFromCollection(id),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['collection'] })
    ui.addToast(t('collection.removed'), 'success')
    router.push({ name: 'collection' })
  },
})

const statusMutation = useMutation({
  mutationFn: (status: ReadingStatus) => updateReadingStatus(id, status),
  onSuccess: () => qc.invalidateQueries({ queryKey: ['collection', id] }),
})

const toggleMutation = useMutation({
  mutationFn: ({ veId, field }: { veId: string; field: 'isOwned' | 'isRead' | 'isWishlisted' }) =>
    toggleVolume(id, veId, field),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['collection', id] })
    qc.invalidateQueries({ queryKey: ['collection'] })
    qc.invalidateQueries({ queryKey: ['wishlist'] })
    qc.invalidateQueries({ queryKey: ['stats'] })
  },
})

const wishlistRemainingMutation = useMutation({
  mutationFn: () => wishlistRemaining(id),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['collection', id] })
    qc.invalidateQueries({ queryKey: ['collection'] })
    qc.invalidateQueries({ queryKey: ['wishlist'] })
    qc.invalidateQueries({ queryKey: ['stats'] })
    ui.addToast(t('wishlist.remainingAdded'), 'success')
  },
})

function volumeRingClass(ve: VolumeEntry) {
  if (ve.isOwned) return 'ring-success/70 opacity-100'
  if (ve.isWishlisted) return 'ring-warning/70 opacity-80'
  return 'ring-base-300 opacity-30 grayscale'
}

function volumeBgClass(ve: VolumeEntry) {
  if (ve.isOwned) return 'bg-base-200 text-base-content'
  if (ve.isWishlisted) return 'bg-warning/20 text-warning'
  return 'bg-base-300 text-base-content/30'
}
</script>

<template>
  <div class="p-4 md:p-6 max-w-5xl mx-auto">
    <div v-if="isPending" class="flex justify-center py-20">
      <span class="loading loading-spinner loading-lg" />
    </div>

    <template v-else-if="entry">
      <!-- Header -->
      <div class="flex gap-5 mb-8">
        <div class="shrink-0">
          <div class="relative">
            <img
              v-if="entry.manga.coverUrl"
              :src="entry.manga.coverUrl"
              :alt="entry.manga.title"
              class="w-28 md:w-36 rounded-2xl shadow-xl object-cover"
            />
            <div v-else class="w-28 md:w-36 aspect-[2/3] rounded-2xl bg-base-300 flex items-center justify-center text-4xl shadow-xl">
              📚
            </div>
          </div>
        </div>

        <div class="flex-1 min-w-0 space-y-2">
          <h1 class="text-2xl md:text-3xl font-bold leading-tight">{{ entry.manga.title }}</h1>

          <div class="flex flex-wrap gap-1.5">
            <span class="badge badge-primary">{{ entry.manga.edition }}</span>
            <span class="badge badge-outline">{{ entry.manga.language.toUpperCase() }}</span>
            <span v-if="entry.manga.genre" class="badge badge-outline capitalize">
              {{ entry.manga.genre.replace('_', ' ') }}
            </span>
          </div>

          <p v-if="entry.manga.author" class="text-sm text-base-content/70 font-medium">
            {{ entry.manga.author }}
          </p>

          <p v-if="entry.manga.summary" class="text-sm text-base-content/60 line-clamp-3 hidden md:block">
            {{ entry.manga.summary }}
          </p>

          <!-- Stats row -->
          <div class="flex flex-wrap gap-4 text-sm pt-1">
            <span class="flex items-center gap-1.5">
              <span class="w-2.5 h-2.5 rounded-full bg-success inline-block" />
              <span class="text-success font-bold">{{ entry.ownedCount }}</span>
              <span class="text-base-content/50">/ {{ entry.totalVolumes }} {{ t('collection.owned') }}</span>
            </span>
            <span v-if="wishlistCount > 0" class="flex items-center gap-1.5">
              <span class="w-2.5 h-2.5 rounded-full bg-warning inline-block" />
              <span class="text-warning font-bold">{{ wishlistCount }}</span>
              <span class="text-base-content/50">{{ t('wishlist.inWishlist') }}</span>
            </span>
            <span class="flex items-center gap-1.5">
              <span class="w-2.5 h-2.5 rounded-full bg-info inline-block" />
              <span class="text-info font-bold">{{ entry.readCount }}</span>
              <span class="text-base-content/50">{{ t('collection.read') }}</span>
            </span>
          </div>

          <!-- Actions -->
          <div class="flex flex-wrap items-center gap-2 pt-1">
            <select
              class="select select-sm select-bordered"
              :value="entry.readingStatus"
              @change="statusMutation.mutate(($event.target as HTMLSelectElement).value as ReadingStatus)"
            >
              <option value="not_started">{{ t('status.not_started') }}</option>
              <option value="in_progress">{{ t('status.in_progress') }}</option>
              <option value="completed">{{ t('status.completed') }}</option>
              <option value="on_hold">{{ t('status.on_hold') }}</option>
              <option value="dropped">{{ t('status.dropped') }}</option>
            </select>

            <!-- Wishlist remaining button -->
            <button
              v-if="entry.ownedCount < entry.totalVolumes && wishlistCount < (entry.totalVolumes - entry.ownedCount)"
              class="btn btn-warning btn-sm gap-1.5"
              :class="{ loading: wishlistRemainingMutation.isPending.value }"
              :disabled="wishlistRemainingMutation.isPending.value"
              @click="wishlistRemainingMutation.mutate()"
            >
              <svg v-if="!wishlistRemainingMutation.isPending.value" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
              </svg>
              {{ t('wishlist.addRemaining') }}
            </button>

            <button
              class="btn btn-error btn-sm btn-outline"
              :class="{ loading: removeMutation.isPending.value }"
              @click="removeMutation.mutate()"
            >
              {{ t('common.remove') }}
            </button>
          </div>
        </div>
      </div>

      <!-- Volumes section -->
      <div>
        <h2 class="text-xs font-semibold mb-3 text-base-content/50 uppercase tracking-widest">
          {{ t('collection.volumes') }} — {{ entry.ownedCount }}/{{ entry.totalVolumes }}
        </h2>

        <div v-if="sortedVolumes.length" class="grid grid-cols-6 sm:grid-cols-8 md:grid-cols-10 lg:grid-cols-12 gap-2">
          <div
            v-for="ve in sortedVolumes"
            :key="ve.id"
            class="group relative cursor-pointer select-none"
            :title="`Tome ${ve.number}${ve.isWishlisted ? ' ⭐ wishlist' : ''}${ve.priceCode ? ` — ${ve.priceCode.value.toFixed(2)}€` : ''}`"
            @click="toggleMutation.mutate({ veId: ve.id, field: 'isOwned' })"
          >
            <!-- Cover -->
            <div
              class="aspect-[2/3] rounded-lg overflow-hidden ring-2 transition-all duration-200"
              :class="volumeRingClass(ve)"
            >
              <img
                v-if="ve.coverUrl"
                :src="ve.coverUrl"
                :alt="`Tome ${ve.number}`"
                class="w-full h-full object-cover"
              />
              <div
                v-else
                class="w-full h-full flex items-center justify-center font-bold text-sm"
                :class="volumeBgClass(ve)"
              >
                {{ ve.number }}
              </div>
            </div>

            <!-- Status indicators (top-left corner) -->
            <!-- Read indicator -->
            <div
              v-if="ve.isRead"
              class="absolute top-1 left-1 w-4 h-4 rounded-full bg-info flex items-center justify-center shadow"
              @click.stop="toggleMutation.mutate({ veId: ve.id, field: 'isRead' })"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5 text-info-content" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
              </svg>
            </div>

            <!-- Hover: toggle read (only if owned) -->
            <button
              v-if="ve.isOwned && !ve.isRead"
              class="absolute top-1 left-1 w-4 h-4 rounded-full bg-base-300/80 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center shadow"
              title="Marquer comme lu"
              @click.stop="toggleMutation.mutate({ veId: ve.id, field: 'isRead' })"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5 text-base-content/60" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
              </svg>
            </button>

            <!-- Wishlist star indicator (top-right) -->
            <div
              v-if="ve.isWishlisted && !ve.isOwned"
              class="absolute top-1 right-1 w-4 h-4 rounded-full bg-warning flex items-center justify-center shadow"
              :title="t('wishlist.inWishlist')"
              @click.stop="toggleMutation.mutate({ veId: ve.id, field: 'isWishlisted' })"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5 text-warning-content" viewBox="0 0 20 20" fill="currentColor">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
              </svg>
            </div>

            <!-- Hover: toggle wishlist (only if not owned and not wishlisted) -->
            <button
              v-if="!ve.isOwned && !ve.isWishlisted"
              class="absolute top-1 right-1 w-4 h-4 rounded-full bg-base-300/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center shadow"
              :title="t('wishlist.addToWishlist')"
              @click.stop="toggleMutation.mutate({ veId: ve.id, field: 'isWishlisted' })"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5 text-base-content/50" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
              </svg>
            </button>

            <!-- Number label -->
            <div
              class="text-center text-xs mt-0.5 tabular-nums"
              :class="ve.isOwned ? 'text-base-content/70' : ve.isWishlisted ? 'text-warning/70' : 'text-base-content/25'"
            >
              {{ ve.number }}
            </div>
          </div>
        </div>

        <div v-else class="text-sm text-base-content/40 italic">
          {{ t('collection.noVolumes') }}
        </div>

        <!-- Legend -->
        <div class="flex flex-wrap gap-4 mt-4 text-xs text-base-content/50">
          <span class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-sm bg-success/40 ring-1 ring-success/60 inline-block" />
            {{ t('collection.owned') }}
          </span>
          <span class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-sm bg-info inline-block" />
            {{ t('collection.read') }}
          </span>
          <span class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-sm bg-warning/40 ring-1 ring-warning/60 inline-block" />
            {{ t('wishlist.inWishlist') }}
          </span>
          <span class="flex items-center gap-1.5">
            <span class="w-3 h-3 rounded-sm bg-base-300 opacity-40 inline-block" />
            {{ t('collection.missing') }}
          </span>
        </div>
      </div>
    </template>
  </div>
</template>
