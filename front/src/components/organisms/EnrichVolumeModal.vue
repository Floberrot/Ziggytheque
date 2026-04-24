<script setup lang="ts">
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { X, Search, RefreshCw, Book, ImageOff, Megaphone, Package, Star, BookOpen } from 'lucide-vue-next'
import { searchVolumeExternal, updateVolume } from '@/api/manga'
import { toggleVolume } from '@/api/collection'
import { useUiStore } from '@/stores/useUiStore'
import type { VolumeEntry, VolumeToggleField } from '@/types'
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

// ── Toggle mutations ──
const toggleMutation = useMutation({
  mutationFn: ({ field }: { field: VolumeToggleField }) =>
    toggleVolume(props.collectionEntryId, props.volume!.id, field),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['collection', props.collectionEntryId] })
    qc.invalidateQueries({ queryKey: ['collection'] })
    qc.invalidateQueries({ queryKey: ['wishlist'] })
    qc.invalidateQueries({ queryKey: ['stats'] })
  },
})

const volumeStatus = computed(() => {
  const v = props.volume
  if (!v) return null
  if (v.isAnnounced && !v.isOwned) return 'announced'
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
            <div class="flex items-center gap-2.5">
              <div>
                <h2 class="font-bold text-lg">Tome {{ volume.number }}</h2>
                <p class="text-sm text-base-content/50">{{ mangaTitle }}</p>
              </div>
              <!-- Status badge -->
              <span
                v-if="volumeStatus === 'announced'"
                class="badge badge-neutral gap-1"
              >
                <Megaphone class="h-3 w-3" />
                Annoncé
              </span>
              <span
                v-else-if="volumeStatus === 'owned'"
                class="badge badge-success gap-1"
              >
                <Package class="h-3 w-3" />
                Possédé
              </span>
              <span
                v-else-if="volumeStatus === 'wished'"
                class="badge badge-warning gap-1"
              >
                <Star class="h-3 w-3" fill="currentColor" stroke-width="0" />
                Souhaité
              </span>
              <span v-else class="badge badge-ghost">Non suivi</span>
            </div>
            <button class="btn btn-ghost btn-sm btn-circle" @click="emit('close')">
              <X class="h-4 w-4" />
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
                class="shrink-0 w-24 sm:w-28 sm:mx-auto aspect-[2/3] rounded-xl overflow-hidden ring-2 bg-base-200 transition-transform duration-150 relative"
                :class="[
                  volumeStatus === 'owned' ? 'ring-success/60' : volumeStatus === 'wished' ? 'ring-warning/60' : volumeStatus === 'announced' ? 'ring-base-content/40 ring-dashed' : 'ring-base-300',
                  volume.coverUrl ? 'cursor-zoom-in hover:scale-105' : ''
                ]"
                @click="volume.coverUrl && (lightboxOpen = true)"
              >
                <img v-if="volume.coverUrl" :src="coverUrl(volume.coverUrl)!" :alt="`Tome ${volume.number}`" class="w-full h-full object-cover" />
                <!-- Announced placeholder -->
                <div v-else-if="volume.isAnnounced && !volume.isOwned" class="w-full h-full flex items-end justify-center bg-base-300 relative" style="background-image: repeating-linear-gradient(45deg, transparent, transparent 4px, rgba(0,0,0,.06) 4px, rgba(0,0,0,.06) 8px);">
                  <span class="badge badge-neutral mb-2 text-[9px]">Annoncé</span>
                </div>
                <div v-else class="w-full h-full flex items-center justify-center text-base-content/20">
                  <Book class="h-8 w-8" stroke-width="1.5" />
                </div>
              </div>

              <!-- 4-button action row -->
              <div class="flex flex-col gap-1.5 flex-1 justify-center sm:justify-start">
                <!-- Annoncé (only when not owned) -->
                <button
                  v-if="!volume.isOwned"
                  class="btn btn-sm gap-1"
                  :class="volume.isAnnounced ? 'btn-neutral' : 'btn-neutral btn-outline'"
                  :disabled="toggleMutation.isPending.value"
                  @click="toggleMutation.mutate({ field: 'isAnnounced' })"
                >
                  <Megaphone class="h-4 w-4" />
                  Annoncé
                </button>
                <!-- Possédé (always) -->
                <button
                  class="btn btn-sm gap-1"
                  :class="volume.isOwned ? 'btn-success' : 'btn-success btn-outline'"
                  :disabled="toggleMutation.isPending.value"
                  @click="toggleMutation.mutate({ field: 'isOwned' })"
                >
                  <Package class="h-4 w-4" />
                  Possédé
                </button>
                <!-- Wishlist (only when not owned) -->
                <button
                  v-if="!volume.isOwned"
                  class="btn btn-sm gap-1"
                  :class="volume.isWished ? 'btn-warning' : 'btn-warning btn-outline'"
                  :disabled="toggleMutation.isPending.value"
                  @click="toggleMutation.mutate({ field: 'isWished' })"
                >
                  <Star class="h-4 w-4" />
                  Wishlist
                </button>
                <!-- Lu (only when owned) -->
                <button
                  v-if="volume.isOwned"
                  class="btn btn-sm gap-1"
                  :class="volume.isRead ? 'btn-info' : 'btn-info btn-outline'"
                  :disabled="toggleMutation.isPending.value"
                  @click="toggleMutation.mutate({ field: 'isRead' })"
                >
                  <BookOpen class="h-4 w-4" />
                  Lu
                </button>
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
                    <Search class="h-4 w-4 opacity-40 shrink-0" />
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
                    <RefreshCw v-if="!isSearching" class="h-4 w-4" />
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
                        <ImageOff class="h-8 w-8" stroke-width="1.5" />
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
