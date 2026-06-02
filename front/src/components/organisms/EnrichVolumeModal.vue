<script setup lang="ts">
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { X, Search, RefreshCw, Book, ImageOff, Megaphone, Package, Star, BookOpen, Camera, Smartphone, QrCode, Info } from 'lucide-vue-next'
import { searchVolumeExternal, updateVolume, createScanSession } from '@/api/manga'
import { toggleVolume } from '@/api/collection'
import { useUiStore } from '@/stores/useUiStore'
import { useIsbnCoverSearch } from '@/composables/useIsbnCoverSearch'
import { useBarcodeScanner } from '@/composables/useBarcodeScanner'
import { useScanSession } from '@/composables/useScanSession'
import BaseQrCode from '@/components/atoms/BaseQrCode.vue'
import { useI18n } from 'vue-i18n'
import type { VolumeEntry, VolumeToggleField } from '@/types'
import { coverUrl } from '@/utils/coverUrl'

const { t } = useI18n()

const props = defineProps<{
  open: boolean
  collectionEntryId: string
  mangaId: string
  mangaTitle: string
  mangaEdition: string | null
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
const searchResults = ref<{ externalId: string | null; title: string; edition: string | null; coverUrl: string | null; spineUrl: string | null; isbn: string | null; source: string | null }[]>([])
const isSearching = ref(false)
const isLoadingMore = ref(false)
const hasMore = ref(false)
const PAGE_SIZE = 20
let currentPage = 1
let lastQuery = ''
let lastVolumeNumber: number | null = null
let lastEdition: string | null = null
let searchTimer: ReturnType<typeof setTimeout> | null = null

function buildContextQuery(title: string, volumeNumber: number, edition: string | null): string {
  return `${title} tome ${volumeNumber}${edition ? ' ' + edition : ''}`.trim()
}

watch(searchQuery, (val) => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    if (val.trim().length >= 2) {
      lastVolumeNumber = null
      lastEdition = null
      runSearch(val)
    }
  }, 500)
})

async function runSearch(q: string) {
  if (q.trim().length < 2) { searchResults.value = []; hasMore.value = false; return }
  lastQuery = q.trim()
  currentPage = 1
  isSearching.value = true
  try {
    const data = await searchVolumeExternal(lastQuery, 1, lastVolumeNumber, lastEdition)
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
    const data = await searchVolumeExternal(lastQuery, currentPage + 1, null, null)
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

// ── Mode switcher ──
const mode = ref<'search' | 'isbn' | 'scan'>('search')

// ── ISBN mode state ──
const isbnInput = ref('')
const { covers: isbnCovers, isLoading: isbnLoading, error: isbnError, search: isbnSearch } = useIsbnCoverSearch(isbnInput)
const videoRef = ref<HTMLVideoElement | null>(null)
const { isScanning, errorMessage: cameraError, start: startScanner, stop: stopScanner } = useBarcodeScanner()
const { start: startScanSession } = useScanSession()
const scanQrValue = ref<string>('')
const isFetchingSession = ref(false)
const isbnSearched = ref(false)

// Editing the ISBN invalidates the previous search result (hides a stale "no cover" message).
watch(isbnInput, () => {
  isbnSearched.value = false
})

async function runIsbnSearch(): Promise<void> {
  if (!isbnInput.value.trim()) return
  await isbnSearch()
  isbnSearched.value = true
}

function applyIsbnCover(cover: { coverUrl: string; spineUrl: string | null; isbn: string | null }): void {
  enrichMutation.mutate({
    coverUrl: cover.coverUrl,
    spineUrl: cover.spineUrl ?? undefined,
    isbn: cover.isbn ?? undefined,
  })
}

// Friendly labels for the grouped ISBN cover results.
const ISBN_SOURCE_LABELS: Record<string, string> = {
  bnf: 'BnF',
  open_library: 'Open Library',
  google_books: 'Google Books',
  hardcover: 'Hardcover',
  mangadex: 'MangaDex',
}
function isbnSourceLabel(source: string): string {
  return ISBN_SOURCE_LABELS[source] ?? source
}

// When no source has a cover for this ISBN, fall back to the title + volume
// search (served by MangaDex), which is far more reliable for manga.
function fallbackToTitleSearch(): void {
  if (!props.volume) return
  lastVolumeNumber = props.volume.number
  lastEdition = props.mangaEdition
  mode.value = 'search'
  runSearch(buildContextQuery(props.mangaTitle, props.volume.number, props.mangaEdition))
}

// Clears every per-tome result so switching tomes never shows the previous one's.
function resetTransientState(): void {
  searchResults.value = []
  searchQuery.value = ''
  manualCoverUrl.value = ''
  hasMore.value = false
  currentPage = 1
  lastQuery = ''
  lastVolumeNumber = null
  lastEdition = null
  isbnInput.value = ''
  isbnSearched.value = false
  isbnCovers.value = []
  scanQrValue.value = ''
  lightboxOpen.value = false
  stopScanner()
  mode.value = 'search'
}

watch(() => props.open, (open) => {
  if (!open) resetTransientState()
})

// Reset + (re)launch the title search whenever the targeted tome changes — covers
// both opening the modal and switching from one tome to another while it stays open.
watch(() => props.volume?.id ?? null, (id, previousId) => {
  if (id === previousId) return
  resetTransientState()
  const vol = props.volume
  if (props.open && vol && !vol.coverUrl) {
    lastVolumeNumber = vol.number
    lastEdition = props.mangaEdition
    runSearch(buildContextQuery(props.mangaTitle, vol.number, props.mangaEdition))
  }
})

// Stop the camera when leaving the Scan tab, and auto-fill the ISBN search from
// the stored ISBN when the ISBN tab is opened.
watch(mode, async (currentMode, previousMode) => {
  if (previousMode === 'scan' && currentMode !== 'scan') stopScanner()
  if (currentMode !== 'isbn') return
  const vol = props.volume
  if (vol?.isbn && !isbnInput.value) {
    isbnInput.value = vol.isbn
    await runIsbnSearch()
    if (isbnCovers.value.length === 1 && !vol.coverUrl) {
      applyIsbnCover(isbnCovers.value[0])
    }
  }
})

async function startCameraScanner(): Promise<void> {
  if (!videoRef.value) return
  await startScanner(videoRef.value, (isbn) => {
    isbnInput.value = isbn
    runIsbnSearch()
  })
}

async function startPhoneScan(): Promise<void> {
  if (!props.volume) return
  isFetchingSession.value = true
  try {
    const session = await createScanSession({ mangaId: props.mangaId, volumeId: props.volume.volumeId })
    scanQrValue.value = `${window.location.origin}/scan/${session.scanToken}`
    startScanSession(session, {
      onResult: async (isbn) => {
        // Surface the hand-off immediately so it's clear the phone reached the PC,
        // even when no cover is found for the ISBN.
        isbnInput.value = isbn
        ui.addToast(t('enrich.isbnFromPhone', { isbn }), 'success')
        await runIsbnSearch()
        if (isbnCovers.value.length === 1) {
          // enrichMutation toasts "cover updated" and closes the modal on success.
          applyIsbnCover(isbnCovers.value[0])
        }
      },
    })
  } catch {
    ui.addToast(t('enrich.scanExpired'), 'error')
  } finally {
    isFetchingSession.value = false
  }
}

// ── Enrich mutation ──
const enrichMutation = useMutation({
  mutationFn: ({ coverUrl, spineUrl, isbn }: { coverUrl: string; spineUrl?: string; isbn?: string }) =>
    updateVolume(props.mangaId, props.volume!.volumeId, { coverUrl, spineUrl, isbn }),
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
        <div class="relative z-10 w-full sm:max-w-5xl bg-base-100 rounded-t-3xl sm:rounded-2xl shadow-2xl overflow-hidden flex flex-col h-[90dvh] sm:h-[680px]">
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

            <!-- ── Left rail : aperçu cover + statut + ISBN du tome ──
                 Mobile : bande horizontale en haut · Desktop : colonne latérale -->
            <div class="shrink-0 sm:w-72 flex flex-col gap-4 p-4 sm:p-5 border-b sm:border-b-0 sm:border-r border-base-200 sm:overflow-y-auto">
              <div class="flex flex-row sm:flex-col gap-4">
                <!-- Cover preview -->
                <div
                  class="shrink-0 w-28 sm:w-44 sm:mx-auto aspect-[2/3] rounded-xl overflow-hidden ring-2 bg-base-200 transition-transform duration-150 relative"
                  :class="[
                    volumeStatus === 'owned' ? 'ring-success/60' : volumeStatus === 'wished' ? 'ring-warning/60' : volumeStatus === 'announced' ? 'ring-base-content/40 ring-dashed' : 'ring-base-300',
                    volume.coverUrl ? 'cursor-zoom-in hover:scale-105' : ''
                  ]"
                  @click="volume.coverUrl && (lightboxOpen = true)"
                >
                  <img v-if="volume.coverUrl" :src="coverUrl(volume.coverUrl)!" :alt="`Tome ${volume.number}`" class="w-full h-full object-cover" />
                  <div v-else-if="volume.isAnnounced && !volume.isOwned" class="w-full h-full flex items-end justify-center bg-base-300" style="background-image: repeating-linear-gradient(45deg, transparent, transparent 4px, rgba(0,0,0,.06) 4px, rgba(0,0,0,.06) 8px);">
                    <span class="badge badge-neutral mb-2 text-[9px]">Annoncé</span>
                  </div>
                  <div v-else class="w-full h-full flex items-center justify-center text-base-content/20">
                    <Book class="h-10 w-10" stroke-width="1.5" />
                  </div>
                </div>

                <!-- Status toggles -->
                <div class="flex flex-col gap-2 flex-1 justify-center sm:justify-start">
                  <button
                    v-if="!volume.isOwned"
                    class="btn btn-sm gap-2"
                    :class="volume.isAnnounced ? 'btn-neutral' : 'btn-neutral btn-outline'"
                    :disabled="toggleMutation.isPending.value"
                    @click="toggleMutation.mutate({ field: 'isAnnounced' })"
                  >
                    <Megaphone class="h-4 w-4" />
                    Annoncé
                  </button>
                  <button
                    class="btn btn-sm gap-2"
                    :class="volume.isOwned ? 'btn-success' : 'btn-success btn-outline'"
                    :disabled="toggleMutation.isPending.value"
                    @click="toggleMutation.mutate({ field: 'isOwned' })"
                  >
                    <Package class="h-4 w-4" />
                    Possédé
                  </button>
                  <button
                    v-if="!volume.isOwned"
                    class="btn btn-sm gap-2"
                    :class="volume.isWished ? 'btn-warning' : 'btn-warning btn-outline'"
                    :disabled="toggleMutation.isPending.value"
                    @click="toggleMutation.mutate({ field: 'isWished' })"
                  >
                    <Star class="h-4 w-4" />
                    Wishlist
                  </button>
                  <button
                    v-if="volume.isOwned"
                    class="btn btn-sm gap-2"
                    :class="volume.isRead ? 'btn-info' : 'btn-info btn-outline'"
                    :disabled="toggleMutation.isPending.value"
                    @click="toggleMutation.mutate({ field: 'isRead' })"
                  >
                    <BookOpen class="h-4 w-4" />
                    Lu
                  </button>
                </div>
              </div>

              <!-- ISBN du tome + aide (desktop) -->
              <div class="hidden sm:block mt-auto pt-4 border-t border-base-200">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-base-content/40">{{ t('enrich.isbnOfVolume') }}</p>
                <p class="text-sm font-semibold mt-1 tabular-nums">{{ volume.isbn || t('enrich.isbnUnknown') }}</p>
                <p class="flex items-start gap-1.5 text-[11px] text-base-content/40 mt-1.5 leading-snug">
                  <Info class="h-3.5 w-3.5 shrink-0 mt-px text-primary/70" />
                  {{ t('enrich.isbnHint') }}
                </p>
              </div>
            </div>

            <!-- ── Right zone : trouver une couverture ── -->
            <div class="flex-1 min-w-0 flex flex-col overflow-hidden">
              <div class="px-4 sm:px-5 pt-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-base-content/40 mb-2">{{ t('enrich.findCover') }}</p>
                <!-- Segmented switcher -->
                <div class="inline-flex p-1 bg-base-200 rounded-xl gap-1">
                  <button class="btn btn-sm border-0 gap-1.5" :class="mode === 'search' ? 'btn-primary' : 'btn-ghost'" @click="mode = 'search'">
                    <Search class="h-4 w-4" />
                    {{ t('enrich.tabSearch') }}
                  </button>
                  <button class="btn btn-sm border-0 gap-1.5" :class="mode === 'isbn' ? 'btn-primary' : 'btn-ghost'" @click="mode = 'isbn'">
                    <QrCode class="h-4 w-4" />
                    {{ t('enrich.tabIsbn') }}
                  </button>
                  <button class="btn btn-sm border-0 gap-1.5" :class="mode === 'scan' ? 'btn-primary' : 'btn-ghost'" @click="mode = 'scan'">
                    <Camera class="h-4 w-4" />
                    {{ t('enrich.tabScan') }}
                  </button>
                </div>
              </div>

              <!-- Scrollable content -->
              <div class="flex-1 overflow-y-auto px-4 sm:px-5 py-4 min-h-0" @scroll="onResultsScroll">
                <!-- Titre : recherche par titre + résultats -->
                <template v-if="mode === 'search'">
                  <div class="flex gap-2 items-center mb-4">
                    <label class="input input-bordered input-sm flex items-center gap-2 flex-1">
                      <Search class="h-4 w-4 opacity-40 shrink-0" />
                      <input v-model="searchQuery" type="text" class="grow text-sm" placeholder="Affiner si nécessaire — sinon, laissez vide" />
                      <span v-if="isSearching" class="loading loading-spinner loading-xs opacity-40" />
                    </label>
                    <button class="btn btn-square btn-outline btn-sm shrink-0" :class="{ loading: isSearching }" :disabled="isSearching || searchQuery.trim().length < 2" title="Relancer" @click="runSearch(searchQuery)">
                      <RefreshCw v-if="!isSearching" class="h-4 w-4" />
                    </button>
                  </div>
                  <p v-if="!searchResults.length && !isSearching" class="text-sm text-base-content/30 text-center py-10">
                    Suggestions de couvertures — appuyez sur une couverture pour l'appliquer
                  </p>
                  <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                    <button
                      v-for="(result, idx) in searchResults"
                      :key="result.externalId ?? result.coverUrl ?? idx"
                      class="group flex flex-col gap-1.5 text-left"
                      :disabled="!result.coverUrl"
                      @click="result.coverUrl && enrichMutation.mutate({ coverUrl: result.coverUrl, spineUrl: result.spineUrl ?? undefined, isbn: result.isbn ?? undefined })"
                    >
                      <div
                        class="w-full aspect-[2/3] rounded-lg overflow-hidden bg-base-200 ring-2 ring-transparent transition-all duration-150"
                        :class="result.coverUrl
                          ? 'group-hover:ring-primary group-hover:scale-[1.03] group-hover:shadow-lg cursor-pointer active:scale-95'
                          : 'opacity-40'"
                      >
                        <img v-if="result.coverUrl" :src="coverUrl(result.coverUrl)!" :alt="result.title" class="w-full h-full object-cover" />
                        <div v-else class="w-full h-full flex items-center justify-center text-base-content/20">
                          <ImageOff class="h-9 w-9" stroke-width="1.5" />
                        </div>
                      </div>
                      <div class="px-0.5">
                        <p class="text-xs font-medium line-clamp-2 leading-tight">{{ result.title }}</p>
                        <p v-if="result.edition" class="text-[10px] text-base-content/40 truncate">{{ result.edition }}</p>
                      </div>
                    </button>
                  </div>
                  <div v-if="isLoadingMore || hasMore" class="py-3 flex items-center justify-center gap-2 text-xs text-base-content/40">
                    <span v-if="isLoadingMore" class="loading loading-spinner loading-xs" />
                    <span v-else>Défiler pour plus</span>
                  </div>
                </template>

                <!-- ISBN : saisie manuelle -->
                <template v-else-if="mode === 'isbn'">
                  <div class="flex gap-2">
                    <input
                      v-model="isbnInput"
                      type="text"
                      class="input input-bordered input-sm flex-1"
                      :placeholder="t('enrich.isbnPlaceholder')"
                      @keyup.enter="runIsbnSearch()"
                    />
                    <button class="btn btn-sm btn-primary shrink-0" :class="{ loading: isbnLoading }" :disabled="!isbnInput.trim() || isbnLoading" @click="runIsbnSearch()">
                      {{ t('enrich.searchIsbn') }}
                    </button>
                  </div>
                  <p v-if="isbnError" class="text-error text-xs mt-2">{{ isbnError }}</p>
                </template>

                <!-- Résultats ISBN/Scan regroupés par source — affichés EN PRIORITÉ, au-dessus du scan -->
                <div v-if="mode !== 'search' && isbnCovers.length" :class="mode === 'isbn' ? 'mt-4' : ''">
                  <p class="text-sm font-medium mb-2">{{ t('enrich.coverFound') }}</p>
                  <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                    <button
                      v-for="(cover, idx) in isbnCovers"
                      :key="cover.source + idx"
                      class="group flex flex-col gap-1.5 text-left"
                      :disabled="enrichMutation.isPending.value"
                      @click="applyIsbnCover(cover)"
                    >
                      <div class="w-full aspect-[2/3] rounded-lg overflow-hidden bg-base-200 ring-2 ring-transparent transition-all duration-150 cursor-pointer group-hover:ring-primary group-hover:scale-[1.03] group-hover:shadow-lg active:scale-95">
                        <img :src="coverUrl(cover.coverUrl)!" :alt="cover.source" class="w-full h-full object-cover" />
                      </div>
                      <span class="badge badge-sm badge-ghost w-full justify-center font-medium">{{ isbnSourceLabel(cover.source) }}</span>
                    </button>
                  </div>
                </div>
                <div v-else-if="mode === 'isbn' && isbnSearched && !isbnLoading && !isbnError && !isbnCovers.length" class="mt-4 flex flex-col gap-2 items-start">
                  <p class="text-sm text-base-content/40">{{ t('enrich.noCoverForIsbn') }}</p>
                  <button class="btn btn-sm btn-outline gap-2" @click="fallbackToTitleSearch()">
                    <Search class="h-4 w-4" />
                    {{ t('enrich.searchByTitle') }}
                  </button>
                </div>

                <!-- Scan : caméra + téléphone — placés SOUS les résultats -->
                <div v-if="mode === 'scan'" class="flex flex-col gap-3" :class="isbnCovers.length ? 'mt-5 pt-5 border-t border-base-200' : ''">
                  <button class="btn btn-sm btn-outline gap-2 w-full" :class="{ 'btn-active': isScanning }" @click="isScanning ? stopScanner() : startCameraScanner()">
                    <Camera class="h-4 w-4" />
                    {{ t('enrich.scanCamera') }}
                  </button>
                  <p v-if="cameraError" class="text-error text-xs">{{ cameraError }}</p>
                  <video v-show="isScanning" ref="videoRef" class="w-full rounded-lg aspect-video object-cover bg-base-200" autoplay muted playsinline />
                  <button class="btn btn-sm btn-outline gap-2 w-full" :class="{ loading: isFetchingSession }" :disabled="isFetchingSession" @click="startPhoneScan()">
                    <Smartphone class="h-4 w-4" />
                    {{ t('enrich.scanPhone') }}
                  </button>
                  <div v-if="scanQrValue" class="flex flex-col items-center gap-2 pt-1">
                    <BaseQrCode :value="scanQrValue" :size="180" />
                    <a :href="scanQrValue" target="_blank" class="link link-primary text-xs">{{ t('enrich.scanLinkTitle') }}</a>
                  </div>
                </div>
              </div>

              <!-- URL fallback (footer, partagé) -->
              <div class="shrink-0 px-4 sm:px-5 pb-4 pt-3 border-t border-base-200">
                <p class="text-[11px] text-base-content/40 mb-1.5 font-semibold uppercase tracking-wide">Ou coller une URL</p>
                <div class="flex gap-2 items-center">
                  <input v-model="manualCoverUrl" type="url" class="input input-bordered input-xs flex-1 min-w-0" placeholder="https://…" />
                  <button
                    class="btn btn-primary btn-xs shrink-0"
                    :class="{ loading: enrichMutation.isPending.value }"
                    :disabled="!manualCoverUrl.trim() || enrichMutation.isPending.value"
                    @click="manualCoverUrl.trim() && enrichMutation.mutate({ coverUrl: manualCoverUrl.trim() })"
                  >
                    Appliquer
                  </button>
                  <div v-if="manualCoverUrl.trim()" class="w-9 aspect-[2/3] rounded overflow-hidden bg-base-200 ring-1 ring-base-300 shrink-0">
                    <img :src="manualCoverUrl.trim()" class="w-full h-full object-cover" />
                  </div>
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
