<script setup lang="ts">
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import {
  getCollectionEntry,
  removeFromCollection,
  updateReadingStatus,
  toggleVolume,
  addRemainingToWishlist,
  purchaseVolume,
} from '@/api/collection'
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

const wishedVolumes = computed(() => sortedVolumes.value.filter((v) => v.isWished && !v.isOwned))
const missingVolumes = computed(() => sortedVolumes.value.filter((v) => !v.isOwned && !v.isWished))

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
  mutationFn: ({ veId, field }: { veId: string; field: 'isOwned' | 'isRead' | 'isWished' }) =>
    toggleVolume(id, veId, field),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['collection', id] })
    qc.invalidateQueries({ queryKey: ['collection'] })
    qc.invalidateQueries({ queryKey: ['wishlist'] })
    qc.invalidateQueries({ queryKey: ['stats'] })
  },
})

const addToWishlistMutation = useMutation({
  mutationFn: () => addRemainingToWishlist(id),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['collection', id] })
    qc.invalidateQueries({ queryKey: ['wishlist'] })
    qc.invalidateQueries({ queryKey: ['stats'] })
    ui.addToast('Tomes manquants ajoutés à la liste de souhaits', 'success')
  },
})

const purchaseMutation = useMutation({
  mutationFn: (veId: string) => purchaseVolume(id, veId),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['collection', id] })
    qc.invalidateQueries({ queryKey: ['collection'] })
    qc.invalidateQueries({ queryKey: ['wishlist'] })
    qc.invalidateQueries({ queryKey: ['stats'] })
    ui.addToast(t('wishlist.purchased'), 'success')
  },
})

function volumeStatusClass(ve: VolumeEntry): string {
  if (ve.isOwned) return 'ring-success/70 opacity-100'
  if (ve.isWished) return 'ring-warning/70 opacity-80'
  return 'ring-base-300/40 opacity-30 grayscale'
}
</script>

<template>
  <div class="min-h-screen">
    <div v-if="isPending" class="flex justify-center py-20">
      <span class="loading loading-spinner loading-lg" />
    </div>

    <template v-else-if="entry">
      <!-- Hero header -->
      <div class="relative overflow-hidden">
        <!-- Blurred background from cover -->
        <div
          v-if="entry.manga.coverUrl"
          class="absolute inset-0 bg-cover bg-center blur-3xl scale-110 opacity-20 pointer-events-none"
          :style="{ backgroundImage: `url(${entry.manga.coverUrl})` }"
        />
        <div class="absolute inset-0 bg-gradient-to-b from-base-100/50 to-base-100 pointer-events-none" />

        <div class="relative max-w-5xl mx-auto px-6 pt-8 pb-6">
          <div class="flex gap-6">
            <!-- Cover -->
            <div class="shrink-0">
              <div class="w-28 md:w-36 aspect-[2/3] rounded-2xl overflow-hidden shadow-2xl ring-2 ring-base-content/10">
                <img
                  v-if="entry.manga.coverUrl"
                  :src="entry.manga.coverUrl"
                  :alt="entry.manga.title"
                  class="w-full h-full object-cover"
                />
                <div v-else class="w-full h-full flex items-center justify-center bg-base-200 text-4xl">
                  📚
                </div>
              </div>
            </div>

            <div class="flex-1 min-w-0 space-y-3">
              <div>
                <h1 class="text-2xl md:text-3xl font-extrabold leading-tight">{{ entry.manga.title }}</h1>
                <div class="flex flex-wrap gap-1.5 mt-2">
                  <span class="badge badge-primary">{{ entry.manga.edition }}</span>
                  <span class="badge badge-outline">{{ entry.manga.language.toUpperCase() }}</span>
                  <span v-if="entry.manga.genre" class="badge badge-outline capitalize">{{ entry.manga.genre }}</span>
                </div>
                <p v-if="entry.manga.author" class="text-sm text-base-content/60 mt-1.5 font-medium">
                  {{ entry.manga.author }}
                </p>
              </div>

              <!-- Stats row -->
              <div class="flex flex-wrap gap-4 text-sm">
                <span class="flex items-center gap-1.5">
                  <span class="w-2.5 h-2.5 rounded-full bg-success inline-block" />
                  <span class="font-bold text-success">{{ entry.ownedCount }}</span>
                  <span class="text-base-content/50">possédé{{ entry.ownedCount !== 1 ? 's' : '' }}</span>
                </span>
                <span v-if="entry.wishedCount > 0" class="flex items-center gap-1.5">
                  <span class="w-2.5 h-2.5 rounded-full bg-warning inline-block" />
                  <span class="font-bold text-warning">{{ entry.wishedCount }}</span>
                  <span class="text-base-content/50">souhaité{{ entry.wishedCount !== 1 ? 's' : '' }}</span>
                </span>
                <span class="flex items-center gap-1.5">
                  <span class="w-2.5 h-2.5 rounded-full bg-info inline-block" />
                  <span class="font-bold text-info">{{ entry.readCount }}</span>
                  <span class="text-base-content/50">lu{{ entry.readCount !== 1 ? 's' : '' }}</span>
                </span>
                <span class="text-base-content/30">/ {{ entry.totalVolumes }} tomes</span>
              </div>

              <!-- Actions -->
              <div class="flex flex-wrap items-center gap-2">
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

                <button
                  v-if="missingVolumes.length > 0"
                  class="btn btn-warning btn-sm gap-1.5"
                  :class="{ loading: addToWishlistMutation.isPending.value }"
                  @click="addToWishlistMutation.mutate()"
                >
                  <svg v-if="!addToWishlistMutation.isPending.value" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                  </svg>
                  Ajouter les {{ missingVolumes.length }} manquants à la wishlist
                </button>

                <button
                  class="btn btn-ghost btn-sm text-error"
                  :class="{ loading: removeMutation.isPending.value }"
                  @click="removeMutation.mutate()"
                >
                  {{ t('common.remove') }}
                </button>
              </div>
            </div>
          </div>

          <!-- Summary text -->
          <p
            v-if="entry.manga.summary"
            class="mt-4 text-sm text-base-content/60 line-clamp-3 max-w-2xl"
          >
            {{ entry.manga.summary }}
          </p>
        </div>
      </div>

      <!-- Volume grid -->
      <div class="max-w-5xl mx-auto px-6 py-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-xs font-semibold uppercase tracking-widest text-base-content/40">
            {{ t('collection.volumes') }} — {{ entry.ownedCount }}/{{ entry.totalVolumes }}
          </h2>

          <!-- Legend -->
          <div class="flex gap-3 text-xs text-base-content/40">
            <span class="flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-sm bg-success ring-1 ring-success inline-block" />
              Possédé
            </span>
            <span class="flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-sm bg-warning ring-1 ring-warning inline-block" />
              Souhaité
            </span>
            <span class="flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-sm bg-base-300 opacity-40 inline-block" />
              Manquant
            </span>
          </div>
        </div>

        <div v-if="sortedVolumes.length" class="grid grid-cols-6 sm:grid-cols-8 md:grid-cols-10 lg:grid-cols-12 xl:grid-cols-14 gap-2">
          <div
            v-for="ve in sortedVolumes"
            :key="ve.id"
            class="group relative cursor-pointer select-none"
            :title="`Tome ${ve.number}${ve.priceCode ? ` — ${ve.priceCode.value.toFixed(2)}€` : ''}`"
          >
            <!-- Cover -->
            <div
              class="aspect-[2/3] rounded-lg overflow-hidden ring-2 transition-all duration-200"
              :class="volumeStatusClass(ve)"
              @click="toggleMutation.mutate({ veId: ve.id, field: 'isOwned' })"
            >
              <img
                v-if="ve.coverUrl"
                :src="ve.coverUrl"
                :alt="`Tome ${ve.number}`"
                class="w-full h-full object-cover"
                loading="lazy"
              />
              <div
                v-else
                class="w-full h-full flex items-center justify-center font-bold text-sm"
                :class="ve.isOwned ? 'bg-base-200 text-base-content' : ve.isWished ? 'bg-warning/10 text-warning' : 'bg-base-300 text-base-content/30'"
              >
                {{ ve.number }}
              </div>
            </div>

            <!-- Status badges -->
            <!-- Read indicator -->
            <div
              v-if="ve.isRead"
              class="absolute top-1 left-1 w-4 h-4 rounded-full bg-info flex items-center justify-center z-10"
              title="Lu"
              @click.stop="toggleMutation.mutate({ veId: ve.id, field: 'isRead' })"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5 text-info-content" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
              </svg>
            </div>

            <!-- Wished indicator -->
            <div
              v-if="ve.isWished && !ve.isOwned"
              class="absolute top-1 right-1 w-4 h-4 rounded-full bg-warning flex items-center justify-center z-10 cursor-pointer"
              title="Souhaité — cliquer pour marquer comme acheté"
              @click.stop="purchaseMutation.mutate(ve.id)"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5 text-warning-content" viewBox="0 0 20 20" fill="currentColor">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
              </svg>
            </div>

            <!-- Hover: toggle read (owned but not read) -->
            <button
              v-if="ve.isOwned && !ve.isRead"
              class="absolute top-1 left-1 w-4 h-4 rounded-full bg-base-300/80 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center z-10"
              title="Marquer comme lu"
              @click.stop="toggleMutation.mutate({ veId: ve.id, field: 'isRead' })"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5 text-base-content/60" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
              </svg>
            </button>

            <!-- Hover: wish toggle (not owned, not wished) -->
            <button
              v-if="!ve.isOwned && !ve.isWished"
              class="absolute top-1 right-1 w-4 h-4 rounded-full bg-warning/20 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center z-10"
              title="Ajouter à la wishlist"
              @click.stop="toggleMutation.mutate({ veId: ve.id, field: 'isWished' })"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5 text-warning" viewBox="0 0 20 20" fill="currentColor">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
              </svg>
            </button>

            <!-- Number label -->
            <div
              class="text-center text-[10px] mt-0.5 tabular-nums font-medium"
              :class="ve.isOwned ? 'text-base-content/70' : ve.isWished ? 'text-warning/70' : 'text-base-content/20'"
            >
              {{ ve.number }}
            </div>
          </div>
        </div>

        <div v-else class="text-sm text-base-content/40 italic py-4">
          Aucun tome enregistré pour cette série.
        </div>

        <!-- Interaction hints -->
        <div class="mt-5 flex flex-wrap gap-4 text-xs text-base-content/35">
          <span>Clic sur le tome → marquer comme possédé</span>
          <span>⭐ hover sur tome manquant → ajouter à la wishlist</span>
          <span>✓ hover sur tome possédé → marquer comme lu</span>
          <span>⭐ jaune → clic pour marquer comme acheté</span>
        </div>
      </div>
    </template>
  </div>
</template>
