<script setup lang="ts">
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { searchVolumeExternal, updateVolume } from '@/api/manga'
import { toggleVolume, purchaseVolume } from '@/api/collection'
import { useUiStore } from '@/stores/useUiStore'
import type { VolumeEntry } from '@/types'
import { coverUrl } from '@/utils/coverUrl'

const props = defineProps<{
  open: boolean
  collectionEntryId: string
  mangaId: string
  mangaTitle: string
  mangaEdition: string
  volume: VolumeEntry | null
}>()

const emit = defineEmits<{ close: [] }>()

const qc = useQueryClient()
const ui = useUiStore()

// ── Escape key + lightbox ──
const lightboxOpen = ref(false)

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    if (lightboxOpen.value) { lightboxOpen.value = false }
    else if (props.open) { emit('close') }
  }
}
onMounted(() => window.addEventListener('keydown', onKeydown))
onUnmounted(() => window.removeEventListener('keydown', onKeydown))

// ── Search state ──
const searchQuery = ref('')
const manualCoverUrl = ref('')
const searchResults = ref<{ externalId: string; title: string; edition: string | null; coverUrl: string | null }[]>([])
const isSearching = ref(false)
const isLoadingMore = ref(false)
const hasMore = ref(false)
const PAGE_SIZE = 20
let currentPage = 1
let lastQuery = ''
let searchTimer: ReturnType<typeof setTimeout> | null = null
let skipNextSearch = false

watch(() => [props.open, props.volume] as const, ([open, vol], prev) => {
  const wasOpen = prev?.[0] ?? false
  const justOpened = open && !wasOpen

  if (justOpened && vol) {
    skipNextSearch = true
    searchQuery.value = `${props.mangaTitle} tome ${vol.number} ${props.mangaEdition}`.trim()
    if (!vol.coverUrl) {
      runSearch(searchQuery.value)
    }
  }
  if (!open) {
    searchResults.value = []
    skipNextSearch = false
    manualCoverUrl.value = ''
    lightboxOpen.value = false
    hasMore.value = false
    currentPage = 1
    lastQuery = ''
  }
})

watch(searchQuery, (val) => {
  if (skipNextSearch) {
    skipNextSearch = false
    if (searchTimer) clearTimeout(searchTimer)
    return
  }
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => runSearch(val), 500)
})

async function runSearch(q: string) {
  if (q.trim().length < 2) { searchResults.value = []; hasMore.value = false; return }
  lastQuery = q.trim()
  currentPage = 1
  isSearching.value = true
  try {
    const data = await searchVolumeExternal(lastQuery, 1)
    searchResults.value = data
    hasMore.value = data.length >= PAGE_SIZE
  } catch {
    searchResults.value = []
    hasMore.value = false
    ui.addToast('Erreur lors de la recherche — réessayez', 'error')
  } finally {
    isSearching.value = false
  }
}

async function loadMore() {
  if (!hasMore.value || isLoadingMore.value || !lastQuery) return
  isLoadingMore.value = true
  try {
    const data = await searchVolumeExternal(lastQuery, currentPage + 1)
    if (data.length > 0) {
      currentPage++
      searchResults.value = [...searchResults.value, ...data]
      hasMore.value = data.length >= PAGE_SIZE
    } else {
      hasMore.value = false
    }
  } catch {
    // silent
  } finally {
    isLoadingMore.value = false
  }
}

function onResultsScroll(e: Event) {
  const el = e.target as HTMLElement
  if (el.scrollTop + el.clientHeight >= el.scrollHeight - 80) {
    loadMore()
  }
}

// ── Enrich mutation ──
const enrichMutation = useMutation({
  mutationFn: ({ coverUrl }: { coverUrl: string }) =>
    updateVolume(props.mangaId, props.volume!.volumeId, { coverUrl }),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['collection', props.collectionEntryId] })
    ui.addToast('Couverture mise à jour', 'success')
    emit('close')
  },
})

// ── Toggle mutations (owned / wished / read) ──
const toggleMutation = useMutation({
  mutationFn: ({ field }: { field: 'isOwned' | 'isRead' | 'isWished' }) =>
    toggleVolume(props.collectionEntryId, props.volume!.id, field),
  onSuccess: (_, { field }) => {
    qc.invalidateQueries({ queryKey: ['collection', props.collectionEntryId] })
    qc.invalidateQueries({ queryKey: ['collection'] })
    qc.invalidateQueries({ queryKey: ['wishlist'] })
    qc.invalidateQueries({ queryKey: ['stats'] })
    if (field === 'isOwned') {
      ui.addToast(props.volume?.isOwned ? 'Tome retiré de la collection' : 'Tome marqué comme possédé', 'success')
    } else if (field === 'isWished') {
      ui.addToast(props.volume?.isWished ? 'Retiré de la wishlist' : 'Ajouté à la wishlist', 'success')
    }
  },
})

const purchaseMutation = useMutation({
  mutationFn: () => purchaseVolume(props.collectionEntryId, props.volume!.id),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['collection', props.collectionEntryId] })
    qc.invalidateQueries({ queryKey: ['collection'] })
    qc.invalidateQueries({ queryKey: ['wishlist'] })
    qc.invalidateQueries({ queryKey: ['stats'] })
    ui.addToast('Tome marqué comme acheté', 'success')
    emit('close')
  },
})

const volumeStatus = computed(() => {
  const v = props.volume
  if (!v) return null
  if (v.isOwned) return 'owned'
  if (v.isWished) return 'wished'
  return 'none'
})
</script>

<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="open && volume" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4">
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="emit('close')" />

        <!-- Modal -->
        <div class="relative z-10 w-full sm:max-w-2xl bg-base-100 rounded-t-3xl sm:rounded-2xl shadow-2xl overflow-hidden flex flex-col h-[90dvh] sm:h-[580px]">
          <!-- Header -->
          <div class="flex items-center justify-between px-5 py-4 border-b border-base-200">
            <div>
              <h2 class="font-bold text-lg">Tome {{ volume.number }}</h2>
              <p class="text-sm text-base-content/50">{{ mangaTitle }}</p>
            </div>
            <button class="btn btn-ghost btn-sm btn-circle" @click="emit('close')">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Layout: stacked on mobile (cover→search→url), side-by-side on desktop -->
          <div class="flex flex-col sm:flex-row gap-0 overflow-hidden flex-1 min-h-0">

            <!-- ── Section cover + actions ──
                 Mobile : bande horizontale en haut (cover à gauche, actions à droite)
                 Desktop : colonne latérale gauche -->
            <div class="shrink-0 sm:w-48 flex flex-row sm:flex-col gap-3 sm:gap-3 p-4 border-b sm:border-b-0 sm:border-r border-base-200 sm:overflow-y-auto">
              <!-- Cover -->
              <div
                class="shrink-0 w-24 sm:w-28 sm:mx-auto aspect-[2/3] rounded-xl overflow-hidden ring-2 bg-base-200 transition-transform duration-150"
                :class="[
                  volumeStatus === 'owned' ? 'ring-success/60' : volumeStatus === 'wished' ? 'ring-warning/60' : 'ring-base-300',
                  volume.coverUrl ? 'cursor-zoom-in hover:scale-105' : ''
                ]"
                @click="volume.coverUrl && (lightboxOpen = true)"
              >
                <img v-if="volume.coverUrl" :src="coverUrl(volume.coverUrl)!" :alt="`Tome ${volume.number}`" class="w-full h-full object-cover" />
                <div v-else class="w-full h-full flex items-center justify-center text-base-content/20">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                  </svg>
                </div>
              </div>

              <!-- Status + actions (beside cover on mobile, below on desktop) -->
              <div class="flex flex-col gap-2 flex-1 justify-center sm:justify-start">
                <!-- Status badge -->
                <div>
                  <span v-if="volumeStatus === 'owned'" class="badge badge-success gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    Possédé
                  </span>
                  <span v-else-if="volumeStatus === 'wished'" class="badge badge-warning gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                    </svg>
                    Souhaité
                  </span>
                  <span v-else class="badge badge-ghost">Non suivi</span>
                </div>

                <!-- Quick actions -->
                <div class="flex flex-col gap-1.5">
                  <button
                    v-if="!volume.isOwned"
                    class="btn btn-success btn-sm gap-1"
                    :class="{ loading: toggleMutation.isPending.value }"
                    @click="toggleMutation.mutate({ field: 'isOwned' })"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    Possédé
                  </button>
                  <button
                    v-if="volume.isOwned"
                    class="btn btn-error btn-outline btn-sm gap-1"
                    :class="{ loading: toggleMutation.isPending.value }"
                    @click="toggleMutation.mutate({ field: 'isOwned' })"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                    Retirer
                  </button>
                  <button
                    v-if="volume.isWished && !volume.isOwned"
                    class="btn btn-success btn-sm gap-1"
                    :class="{ loading: purchaseMutation.isPending.value }"
                    @click="purchaseMutation.mutate()"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    Acheté
                  </button>
                  <button
                    v-if="!volume.isWished && !volume.isOwned"
                    class="btn btn-warning btn-outline btn-sm gap-1"
                    :class="{ loading: toggleMutation.isPending.value }"
                    @click="toggleMutation.mutate({ field: 'isWished' })"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                    </svg>
                    Wishlist
                  </button>
                  <!-- Read toggle -->
                  <button
                    v-if="volume.isOwned"
                    class="flex items-center gap-2 w-full px-3 py-2 rounded-lg border transition-all duration-150 text-sm font-medium"
                    :class="volume.isRead
                      ? 'bg-info/15 border-info/40 text-info hover:bg-info/25'
                      : 'bg-base-200/60 border-base-300 text-base-content/60 hover:bg-base-200'"
                    :disabled="toggleMutation.isPending.value"
                    @click="toggleMutation.mutate({ field: 'isRead' })"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <span class="flex-1 text-left">{{ volume.isRead ? 'Lu' : 'Non lu' }}</span>
                    <div
                      class="w-8 h-4 rounded-full transition-colors relative shrink-0"
                      :class="volume.isRead ? 'bg-info' : 'bg-base-300'"
                    >
                      <div
                        class="absolute top-0.5 w-3 h-3 rounded-full bg-white shadow transition-transform duration-200"
                        :class="volume.isRead ? 'translate-x-4' : 'translate-x-0.5'"
                      />
                    </div>
                  </button>
                </div>
              </div>
            </div>

            <!-- ── Section recherche + résultats + URL ── -->
            <div class="flex-1 min-w-0 flex flex-col overflow-hidden">
              <!-- Search input -->
              <div class="p-3 sm:p-4 border-b border-base-200">
                <p class="text-xs text-base-content/50 mb-2 font-semibold uppercase tracking-wide">
                  Chercher une couverture
                </p>
                <div class="flex gap-2 items-center">
                  <label class="input input-bordered input-sm flex items-center gap-2 flex-1">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-40 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input
                      v-model="searchQuery"
                      type="text"
                      class="grow text-sm"
                      placeholder="ex: GTO tome 1 Pika"
                    />
                    <span v-if="isSearching" class="loading loading-spinner loading-xs opacity-40" />
                  </label>
                  <button
                    class="btn btn-square btn-outline btn-sm shrink-0"
                    :class="{ loading: isSearching }"
                    :disabled="isSearching || searchQuery.trim().length < 2"
                    title="Relancer"
                    @click="runSearch(searchQuery)"
                  >
                    <svg v-if="!isSearching" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Results (scrollable) -->
              <div class="flex-1 overflow-y-auto p-3" @scroll="onResultsScroll">
                <p v-if="!searchResults.length && !isSearching" class="text-sm text-base-content/30 text-center py-8">
                  Résultats Google Books — appuyez sur une couverture pour l'appliquer
                </p>

                <div class="grid grid-cols-3 sm:grid-cols-4 gap-2.5">
                  <button
                    v-for="result in searchResults"
                    :key="result.externalId"
                    class="group flex flex-col gap-1 text-left"
                    :disabled="!result.coverUrl"
                    @click="result.coverUrl && enrichMutation.mutate({ coverUrl: result.coverUrl })"
                  >
                    <div
                      class="w-full aspect-[2/3] rounded-lg overflow-hidden bg-base-200 ring-2 ring-transparent transition-all duration-150"
                      :class="result.coverUrl
                        ? 'group-hover:ring-primary group-hover:scale-105 group-hover:shadow-lg cursor-pointer active:scale-95'
                        : 'opacity-40'"
                    >
                      <img
                        v-if="result.coverUrl"
                        :src="coverUrl(result.coverUrl)!"
                        :alt="result.title"
                        class="w-full h-full object-cover"
                      />
                      <div v-else class="w-full h-full flex items-center justify-center text-base-content/20">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                      </div>
                    </div>
                    <div class="px-0.5">
                      <p class="text-[10px] font-medium line-clamp-2 leading-tight">{{ result.title }}</p>
                      <p v-if="result.edition" class="text-[9px] text-base-content/40 truncate">{{ result.edition }}</p>
                    </div>
                  </button>
                </div>

                <div v-if="isLoadingMore || hasMore" class="py-3 flex items-center justify-center gap-2 text-xs text-base-content/40">
                  <span v-if="isLoadingMore" class="loading loading-spinner loading-xs" />
                  <span v-else>Défiler pour plus</span>
                </div>
              </div>

              <!-- URL fallback (footer) -->
              <div class="shrink-0 px-3 sm:px-4 pb-4 pt-3 border-t border-base-200">
                <p class="text-xs text-base-content/40 mb-1.5 font-medium uppercase tracking-wide">
                  Ou coller une URL
                </p>
                <div class="flex gap-2 items-center">
                  <input
                    v-model="manualCoverUrl"
                    type="url"
                    class="input input-bordered input-xs flex-1 min-w-0"
                    placeholder="https://…"
                  />
                  <button
                    class="btn btn-primary btn-xs shrink-0"
                    :class="{ loading: enrichMutation.isPending.value }"
                    :disabled="!manualCoverUrl.trim() || enrichMutation.isPending.value"
                    @click="manualCoverUrl.trim() && enrichMutation.mutate({ coverUrl: manualCoverUrl.trim() })"
                  >
                    Appliquer
                  </button>
                </div>
                <div v-if="manualCoverUrl.trim()" class="mt-2 w-14 aspect-[2/3] rounded-md overflow-hidden bg-base-200 ring-1 ring-base-300">
                  <img :src="manualCoverUrl.trim()" class="w-full h-full object-cover" />
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>

  <!-- Lightbox -->
  <Teleport to="body">
    <Transition name="fade">
      <div
        v-if="lightboxOpen && volume?.coverUrl"
        class="fixed inset-0 z-[60] flex items-center justify-center bg-black/90 backdrop-blur-sm cursor-zoom-out"
        @click="lightboxOpen = false"
      >
        <img
          :src="coverUrl(volume.coverUrl)!"
          :alt="`Tome ${volume.number}`"
          class="max-h-[90dvh] max-w-[90vw] object-contain rounded-xl shadow-2xl"
          @click.stop
        />
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.2s ease;
}
.modal-enter-active .relative,
.modal-leave-active .relative {
  transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
.modal-enter-from .relative {
  transform: translateY(40px) scale(0.97);
}
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
