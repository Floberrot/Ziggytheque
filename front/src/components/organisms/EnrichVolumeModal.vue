<script setup lang="ts">
import { ref, watch, computed, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { Camera, HelpCircle, Megaphone, Package, QrCode, Search, Star, Tag, X } from 'lucide-vue-next'
import type { CoverProvider } from '@/api/manga'
import { useBarcodeScanner } from '@/composables/useBarcodeScanner'
import { useCoverTitleSearch } from '@/composables/useCoverTitleSearch'
import { useIsbnCoverSearch, type IsbnCoverResult } from '@/composables/useIsbnCoverSearch'
import { useScanSession } from '@/composables/useScanSession'
import { useVolumePrices } from '@/composables/useVolumePrices'
import { useUiStore } from '@/stores/useUiStore'
import type { VolumeEntry, VolumeToggleField } from '@/types'
import { coverUrl } from '@/utils/coverUrl'
import { normalizeIsbn13 } from '@/utils/isbn'
import { volumeHeadlineStatus } from '@/utils/volumeStyles'
import BaseModal from '@/components/atoms/BaseModal.vue'
import CoverUrlForm from '@/components/molecules/CoverUrlForm.vue'
import IsbnCoverResults from '@/components/molecules/IsbnCoverResults.vue'
import IsbnLookupForm from '@/components/molecules/IsbnLookupForm.vue'
import CollectionGuideModal from '@/components/organisms/CollectionGuideModal.vue'
import CoverSearchPanel from '@/components/organisms/CoverSearchPanel.vue'
import VolumePricesPanel from '@/components/organisms/VolumePricesPanel.vue'
import VolumeScanPanel from '@/components/organisms/VolumeScanPanel.vue'
import VolumeStatusRail from '@/components/organisms/VolumeStatusRail.vue'

type EnrichMode = 'search' | 'isbn' | 'scan' | 'prix'

/**
 * The tome tool: its statuses, a cover (by title, ISBN, scan or URL) and its prices.
 * Lookups (cover search, ISBN, prices) run here; every write is emitted — the series
 * page owns the mutations (status toggle, ISBN, cover) and their cache updates.
 */
const props = defineProps<{
  open: boolean
  mangaId: string
  mangaTitle: string
  mangaEdition: string | null
  volume: VolumeEntry | null
  initialMode?: EnrichMode
  /** The page is saving the ISBN: no second save of the same value. */
  savingIsbn: boolean
  /** The page is applying a cover. */
  applyingCover: boolean
}>()

const emit = defineEmits<{
  close: []
  toggle: [field: VolumeToggleField]
  saveIsbn: [isbn: string]
  applyCover: [cover: { coverUrl: string; isbn?: string }]
}>()

const { t } = useI18n()
const ui = useUiStore()

// ── Escape key + lightbox + guide ──
const lightboxOpen = ref(false)
const showGuide = ref(false)

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') {
    if (showGuide.value) showGuide.value = false
    else if (lightboxOpen.value) lightboxOpen.value = false
    else if (props.open) emit('close')
  }
}
onMounted(() => window.addEventListener('keydown', onKeydown))
onUnmounted(() => window.removeEventListener('keydown', onKeydown))

const headlineStatus = computed(() => (props.volume ? volumeHeadlineStatus(props.volume) : null))

// ── Mode switcher ──
const mode = ref<EnrichMode>(props.initialMode ?? 'search')

// ── Search by title ──
const titleSearch = useCoverTitleSearch({
  volumeNumber: () => props.volume?.number ?? null,
  edition: () => props.mangaEdition,
  onError: () => ui.addToast(t('enrich.searchError'), 'error'),
})
const {
  query: searchQuery,
  results: searchResults,
  isSearching,
  isLoadingMore,
  hasMore,
  provider: coverProvider,
  providers: coverProviders,
  providerLabel: coverProviderLabel,
} = titleSearch

function selectCoverProvider(provider: CoverProvider): void {
  coverProvider.value = provider
}

function onResultsScroll(event: Event): void {
  const element = event.target as HTMLElement
  if (element.scrollTop + element.clientHeight >= element.scrollHeight - 80) {
    titleSearch.loadMore()
  }
}

// ── Prices ──
const volumeIdForPrices = computed(() => props.volume?.volumeId ?? '')
const {
  offers: priceOffers,
  retailers: priceRetailers,
  hasIsbn: priceHasIsbn,
  isLoading: pricesLoading,
  error: pricesError,
  loaded: pricesLoaded,
  load: loadPrices,
} = useVolumePrices(() => props.mangaId, volumeIdForPrices)

// A newly saved ISBN unlocks the price search: refresh the prices already shown.
watch(
  () => [props.volume?.id, props.volume?.isbn] as const,
  ([volumeEntryId, isbn], [previousVolumeEntryId, previousIsbn]) => {
    if (volumeEntryId === previousVolumeEntryId && isbn !== previousIsbn && pricesLoaded.value) loadPrices()
  },
)

// ── ISBN ──
const isbnInput = ref('')
const { covers: isbnCovers, isLoading: isbnLoading, error: isbnError, search: isbnSearch } = useIsbnCoverSearch(isbnInput)
// A "found" cover whose image turns out broken or blank is not offered at all.
const brokenCoverUrls = ref(new Set<string>())
const visibleIsbnCovers = computed(() => isbnCovers.value.filter((cover) => !brokenCoverUrls.value.has(cover.coverUrl)))
const isbnSearched = ref(false)

// Editing the ISBN invalidates the previous search result (hides a stale "no cover" message).
watch(isbnInput, () => {
  isbnSearched.value = false
})

// The typed / scanned ISBN is saved on its own (field blur + before every cover
// search), so it is never lost when no cover is found or the modal is closed.
function autoSaveIsbn(): void {
  const volume = props.volume
  if (!volume) return
  const normalized = normalizeIsbn13(isbnInput.value)
  if (!normalized || normalized === volume.isbn || props.savingIsbn) return
  emit('saveIsbn', normalized)
}

async function runIsbnSearch(): Promise<void> {
  if (!isbnInput.value.trim()) return
  autoSaveIsbn()
  await isbnSearch()
  isbnSearched.value = true
}

function applyIsbnCover(cover: IsbnCoverResult): void {
  // The ISBN the user typed/scanned wins over the one echoed back by the cover API.
  const typedIsbn = normalizeIsbn13(isbnInput.value)
  emit('applyCover', { coverUrl: cover.coverUrl, isbn: typedIsbn ?? cover.isbn ?? undefined })
}

function markCoverMissing(brokenCoverUrl: string): void {
  brokenCoverUrls.value.add(brokenCoverUrl)
}

// When no source has a cover for this ISBN, fall back to the title + volume
// search (served by MangaDex), which is far more reliable for manga.
function fallbackToTitleSearch(): void {
  if (!props.volume) return
  mode.value = 'search'
  // Seeding the field triggers the debounced search. It only carries the series
  // title: the tome number and edition are sent as their own parameters.
  searchQuery.value = props.mangaTitle.trim()
}

// ── Scan: the computer's camera or the phone ──
const scanPanel = ref<InstanceType<typeof VolumeScanPanel> | null>(null)
const { isScanning, errorKey: cameraErrorKey, start: startScanner, stop: stopScanner } = useBarcodeScanner()
const phoneSession = useScanSession()
const phoneUrl = ref('')
const isOpeningPhoneSession = ref(false)

async function toggleCamera(): Promise<void> {
  if (isScanning.value) {
    stopScanner()
    return
  }
  const video = scanPanel.value?.video
  if (!video) return
  await startScanner(video, (isbn) => {
    isbnInput.value = isbn
    runIsbnSearch()
  })
}

async function startPhoneScan(): Promise<void> {
  const volume = props.volume
  if (!volume) return
  isOpeningPhoneSession.value = true
  try {
    const session = await phoneSession.open({ mangaId: props.mangaId, volumeId: volume.volumeId }, {
      onResult: async (isbn) => {
        // Surface the hand-off immediately so it's clear the phone reached the PC,
        // even when no cover is found for the ISBN.
        isbnInput.value = isbn
        ui.addToast(t('enrich.isbnFromPhone', { isbn }), 'success')
        await runIsbnSearch()
        if (isbnCovers.value.length === 1) {
          // The page toasts "cover updated" and closes the modal once it is applied.
          applyIsbnCover(isbnCovers.value[0])
        }
      },
    })
    phoneUrl.value = `${window.location.origin}/scan/${session.scanToken}`
  } catch {
    ui.addToast(t('enrich.scanExpired'), 'error')
  } finally {
    isOpeningPhoneSession.value = false
  }
}

// ── Cover by URL (footer) ──
const manualCoverUrl = ref('')

// Clears every per-tome result so switching tomes never shows the previous one's.
function resetTransientState(): void {
  titleSearch.reset()
  manualCoverUrl.value = ''
  isbnInput.value = ''
  isbnSearched.value = false
  isbnCovers.value = []
  phoneUrl.value = ''
  lightboxOpen.value = false
  stopScanner()
  mode.value = props.initialMode ?? 'search'
}

watch(() => props.open, (open) => {
  if (!open) resetTransientState()
})

// Reset + (re)launch the title search whenever the targeted tome changes — covers
// both opening the modal and switching from one tome to another while it stays open.
// Immediate: the page loads this component lazily, so it can mount already open.
watch(() => props.volume?.id ?? null, (volumeEntryId, previousVolumeEntryId) => {
  if (volumeEntryId === (previousVolumeEntryId ?? null)) return
  resetTransientState()
  const volume = props.volume
  if (props.open && volume && !volume.coverUrl && mode.value !== 'prix') {
    // Seed the field with the series title — visible and editable — which triggers
    // the (debounced) search.
    searchQuery.value = props.mangaTitle.trim()
  }
  if (props.open && mode.value === 'prix') {
    loadPrices()
  }
}, { immediate: true })

// Stop the camera when leaving the Scan tab, auto-fill the ISBN from the stored one,
// and load the prices on the first opening of the prices tab.
watch(mode, async (currentMode, previousMode) => {
  if (previousMode === 'scan' && currentMode !== 'scan') stopScanner()
  if (currentMode === 'prix' && !pricesLoaded.value) {
    loadPrices()
  }
  if (currentMode !== 'isbn') return
  const volume = props.volume
  if (volume?.isbn && !isbnInput.value) {
    isbnInput.value = volume.isbn
    await runIsbnSearch()
    if (isbnCovers.value.length === 1 && !volume.coverUrl) {
      applyIsbnCover(isbnCovers.value[0])
    }
  }
})

const MODES: { key: EnrichMode; icon: typeof Search; labelKey: string }[] = [
  { key: 'search', icon: Search, labelKey: 'enrich.tabSearch' },
  { key: 'isbn', icon: QrCode, labelKey: 'enrich.tabIsbn' },
  { key: 'scan', icon: Camera, labelKey: 'enrich.tabScan' },
  { key: 'prix', icon: Tag, labelKey: 'prices.tabLabel' },
]
</script>

<template>
  <!-- Escape is handled by this component's own keydown listener (guide and
       lightbox close first), so BaseModal's Escape handling is disabled. -->
  <BaseModal
    :open="open && volume !== null"
    max-width-class="sm:max-w-5xl"
    panel-class="h-[90dvh] sm:h-[680px]"
    z-class="z-50"
    :close-on-escape="false"
    @close="emit('close')"
  >
    <template v-if="volume">
      <!-- Header -->
      <div class="flex items-center justify-between px-5 py-4 border-b border-base-200">
        <div class="flex items-center gap-2.5">
          <div>
            <h2 class="font-bold text-lg">{{ t('catalogue.tome', { number: volume.number }) }}</h2>
            <p class="text-sm text-base-content/50">{{ mangaTitle }}</p>
          </div>
          <!-- Status badge -->
          <span v-if="headlineStatus === 'announced'" class="badge badge-neutral gap-1">
            <Megaphone class="h-3 w-3" />
            {{ t('enrich.statusAnnouncedLabel') }}
          </span>
          <span v-else-if="headlineStatus === 'owned'" class="badge badge-success gap-1">
            <Package class="h-3 w-3" />
            {{ t('enrich.statusOwnedLabel') }}
          </span>
          <span v-else-if="headlineStatus === 'wished'" class="badge badge-warning gap-1">
            <Star class="h-3 w-3" fill="currentColor" stroke-width="0" />
            {{ t('enrich.statusWishedLabel') }}
          </span>
          <span v-else class="badge badge-ghost">{{ t('volume.untracked') }}</span>
        </div>
        <div class="flex items-center gap-1">
          <div class="tooltip tooltip-left" :data-tip="t('guide.openTooltip')">
            <button
              class="btn btn-ghost btn-sm btn-circle text-base-content/60 hover:text-primary"
              :aria-label="t('guide.openTooltip')"
              @click="showGuide = true"
            >
              <HelpCircle class="h-5 w-5" />
            </button>
          </div>
          <button class="btn btn-ghost btn-sm btn-circle" :aria-label="t('common.close')" @click="emit('close')">
            <X class="h-4 w-4" />
          </button>
        </div>
      </div>

      <!-- Layout: single scroll on mobile, side-by-side on desktop -->
      <div class="flex flex-col sm:flex-row gap-0 overflow-y-auto sm:overflow-hidden flex-1 min-h-0" @scroll="onResultsScroll">
        <VolumeStatusRail
          :volume="volume"
          @toggle="emit('toggle', $event)"
          @open-guide="showGuide = true"
          @zoom="lightboxOpen = true"
        />

        <!-- ── Right zone: find a cover ── -->
        <div class="min-w-0 flex flex-col sm:flex-1 sm:overflow-hidden">
          <div class="px-4 sm:px-5 pt-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-base-content/40 mb-2">{{ t('enrich.findCover') }}</p>
            <!-- Segmented switcher — full width with flex-1 tabs on mobile so
                 the 4 tabs never overflow at 360px (icons hidden when cramped) -->
            <div class="flex sm:inline-flex w-full sm:w-auto p-1 bg-base-200 rounded-xl gap-1" role="tablist">
              <button
                v-for="option in MODES"
                :key="option.key"
                class="btn btn-sm border-0 gap-1.5 flex-1 sm:flex-none min-w-0 max-[440px]:px-1 max-[440px]:text-xs"
                :class="mode === option.key ? 'btn-primary' : 'btn-ghost'"
                role="tab"
                :aria-selected="mode === option.key"
                @click="mode = option.key"
              >
                <component :is="option.icon" class="h-4 w-4 shrink-0 max-[400px]:hidden" />
                <span class="truncate">{{ t(option.labelKey) }}</span>
              </button>
            </div>
          </div>

          <!-- Scrollable content (desktop only — mobile scrolls the outer wrapper) -->
          <div class="sm:flex-1 sm:overflow-y-auto px-4 sm:px-5 py-4 sm:min-h-0" @scroll="onResultsScroll">
            <!-- Title: search by title + results -->
            <CoverSearchPanel
              v-if="mode === 'search'"
              v-model:query="searchQuery"
              :results="searchResults"
              :is-searching="isSearching"
              :is-loading-more="isLoadingMore"
              :has-more="hasMore"
              :provider="coverProvider"
              :providers="coverProviders"
              :provider-label="coverProviderLabel"
              @select-provider="selectCoverProvider"
              @refresh="titleSearch.runSearch(searchQuery)"
              @apply="emit('applyCover', $event)"
            />

            <!-- ISBN: typed by hand -->
            <IsbnLookupForm
              v-else-if="mode === 'isbn'"
              v-model:isbn="isbnInput"
              :loading="isbnLoading"
              :error-key="isbnError"
              @search="runIsbnSearch()"
              @commit="autoSaveIsbn()"
            />

            <!-- ISBN / scan results grouped by source — shown FIRST, above the scan tools -->
            <Transition name="fade">
              <IsbnCoverResults
                v-if="mode !== 'search' && visibleIsbnCovers.length"
                :class="mode === 'isbn' ? 'mt-4' : ''"
                :covers="visibleIsbnCovers"
                :applying="applyingCover"
                @apply="applyIsbnCover"
                @missing="markCoverMissing"
              />
            </Transition>
            <div v-if="mode === 'isbn' && isbnSearched && !isbnLoading && !isbnError && !visibleIsbnCovers.length" class="mt-4 flex flex-col gap-2 items-start">
              <p class="text-sm text-base-content/40">{{ t('enrich.noCoverForIsbn') }}</p>
              <button class="btn btn-sm btn-outline gap-2" @click="fallbackToTitleSearch()">
                <Search class="h-4 w-4" />
                {{ t('enrich.searchByTitle') }}
              </button>
            </div>

            <!-- Scan: camera + phone — placed BELOW the results -->
            <VolumeScanPanel
              v-if="mode === 'scan'"
              ref="scanPanel"
              :is-scanning="isScanning"
              :camera-error-key="cameraErrorKey"
              :opening-phone-session="isOpeningPhoneSession"
              :phone-url="phoneUrl"
              :below-results="visibleIsbnCovers.length > 0"
              @toggle-camera="toggleCamera"
              @start-phone="startPhoneScan"
            />

            <!-- Prices: merchant offers by ISBN -->
            <VolumePricesPanel
              v-if="mode === 'prix'"
              :loading="pricesLoading"
              :error-key="pricesError"
              :loaded="pricesLoaded"
              :has-isbn="priceHasIsbn"
              :retailers="priceRetailers"
              :offers="priceOffers"
              @enter-isbn="mode = 'isbn'"
            />
          </div>

          <!-- URL fallback (shared footer — hidden in prices mode) -->
          <CoverUrlForm
            v-if="mode !== 'prix'"
            v-model:url="manualCoverUrl"
            :applying="applyingCover"
            @apply="emit('applyCover', { coverUrl: $event })"
          />
        </div>
      </div>
    </template>
  </BaseModal>

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
          :alt="t('catalogue.tome', { number: volume.number })"
          class="max-h-[90dvh] max-w-[90vw] object-contain rounded-xl shadow-2xl"
          @click.stop
        />
      </div>
    </Transition>
  </Teleport>

  <!-- Guide / tutorial -->
  <CollectionGuideModal :open="showGuide" @close="showGuide = false" />
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.18s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
