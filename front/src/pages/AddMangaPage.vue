<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { useI18n } from 'vue-i18n'
import { HelpCircle, PenLine, ScanBarcode, Search } from 'lucide-vue-next'
import {
  addFromCatalogue, getCatalogueEdition, scanIsbn, searchCatalogue,
  type AddFromCataloguePayload, type CatalogueEdition, type CatalogueRegistration, type CatalogueSearchMode,
} from '@/api/catalogue'
import { addRemainingToWishlist, addToCollection, toggleVolume } from '@/api/collection'
import { importManga } from '@/api/manga'
import { useUiStore } from '@/stores/useUiStore'
import type { CatalogueSelection, ManualSeriesDraft, ScanFeedItem } from '@/types'
import { normalizeIsbn13 } from '@/utils/isbn'
import AddedSeriesAlert from '@/components/molecules/AddedSeriesAlert.vue'
import CatalogueEditionSheet from '@/components/organisms/CatalogueEditionSheet.vue'
import CatalogueSearchPanel from '@/components/organisms/CatalogueSearchPanel.vue'
import CollectionGuideModal from '@/components/organisms/CollectionGuideModal.vue'
import ManualSeriesForm from '@/components/organisms/ManualSeriesForm.vue'
import ShelfScanPanel from '@/components/organisms/ShelfScanPanel.vue'

type AddTab = 'search' | 'scan' | 'manual'

const route = useRoute()
const router = useRouter()
const queryClient = useQueryClient()
const ui = useUiStore()
const { t } = useI18n()

const showGuide = ref(false)
const tab = ref<AddTab>(route.query.tab === 'scan' ? 'scan' : 'search')
const TABS: readonly AddTab[] = ['search', 'scan', 'manual']

function refreshCollection(): void {
  queryClient.invalidateQueries({ queryKey: ['collection'] })
  queryClient.invalidateQueries({ queryKey: ['stats'] })
  queryClient.invalidateQueries({ queryKey: ['catalogue'] })
}

function httpStatus(error: unknown): number | undefined {
  return (error as { response?: { status?: number } } | null)?.response?.status
}

// ── Search (title / author — an ISBN typed in the field is detected) ─────────

const searchMode = ref<'title' | 'author'>('title')
const searchInput = ref(typeof route.query.q === 'string' ? route.query.q : '')
const debouncedQuery = ref(searchInput.value.trim())
let debounceTimer: ReturnType<typeof setTimeout> | null = null

watch(searchInput, (value) => {
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    debouncedQuery.value = value.trim()
  }, 450)
})

const hasQuery = computed(() => debouncedQuery.value.length >= 2)
const effectiveMode = computed<CatalogueSearchMode>(() =>
  normalizeIsbn13(debouncedQuery.value) !== null ? 'isbn' : searchMode.value,
)

const {
  data: searchResult,
  isFetching: isSearching,
  error: searchError,
} = useQuery({
  queryKey: computed(() => ['catalogue', 'search', effectiveMode.value, debouncedQuery.value]),
  queryFn: () => searchCatalogue(debouncedQuery.value, effectiveMode.value),
  enabled: computed(() => hasQuery.value),
  staleTime: 5 * 60 * 1000,
  retry: false,
})

const searchErrorMessage = computed(() => {
  if (!searchError.value) return null
  const status = httpStatus(searchError.value)
  if (status === 404) return t('add.isbnNotFound')
  if (status === 422) return t('add.invalidQuery')
  if (status === 429) return t('add.tooManyRequests')
  return t('add.searchUnavailable')
})

// ── Series sheet: pick the tomes you own ─────────────────────────────────────

const selectedEdition = ref<CatalogueEdition | null>(null)
const requestedVolume = ref<number | null>(null)

function openEdition(edition: CatalogueEdition, volume: number | null = null): void {
  selectedEdition.value = edition
  requestedVolume.value = volume ?? searchResult.value?.requestedVolume ?? null
}

// The search only saw some tomes of each series — load the complete list once opened.
const { data: fullEdition, isFetching: isLoadingEdition } = useQuery({
  queryKey: computed(() => [
    'catalogue',
    'edition',
    selectedEdition.value?.workTitle,
    selectedEdition.value?.publisher,
    selectedEdition.value?.specialEdition,
  ]),
  queryFn: () => getCatalogueEdition(selectedEdition.value!),
  enabled: computed(() => selectedEdition.value !== null),
  staleTime: 5 * 60 * 1000,
  retry: false,
})

const sheetEdition = computed<CatalogueEdition | null>(() => {
  const selected = selectedEdition.value
  if (!selected) return null
  const complete = fullEdition.value
  // Never show fewer tomes than the search already found.
  return complete && complete.volumeCount >= selected.volumeCount ? complete : selected
})

interface AddedSummary {
  registration: CatalogueRegistration
  workTitle: string
  publisher: string | null
  specialEdition: string | null
}

const lastAdded = ref<AddedSummary | null>(null)

const addMutation = useMutation({
  mutationFn: (payload: AddFromCataloguePayload) => addFromCatalogue(payload),
  onSuccess: (registration, payload) => {
    refreshCollection()
    lastAdded.value = {
      registration,
      workTitle: payload.workTitle,
      publisher: payload.publisher,
      specialEdition: payload.specialEdition,
    }
    selectedEdition.value = null
  },
  onError: () => ui.addToast(t('add.addError'), 'error'),
})

function addSelection(selection: CatalogueSelection): void {
  const edition = sheetEdition.value
  if (!edition) return
  addMutation.mutate({
    workTitle: selection.workTitle,
    publisher: selection.publisher,
    specialEdition: selection.specialEdition,
    author: edition.author,
    coverUrl: edition.coverUrl,
    volumeCount: edition.volumeCount,
    volumes: edition.volumes,
    ownedNumbers: selection.ownedNumbers,
  })
}

const wishlistMutation = useMutation({
  mutationFn: (collectionEntryId: string) => addRemainingToWishlist(collectionEntryId),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['wishlist'] })
    refreshCollection()
    ui.addToast(t('wishlist.allAdded'), 'success')
    lastAdded.value = null
  },
})

function openSeries(collectionEntryId: string): void {
  router.push({ name: 'collection-detail', params: { id: collectionEntryId } })
}

function openLastAddedSeries(): void {
  if (lastAdded.value) openSeries(lastAdded.value.registration.collectionEntryId)
}

function sendMissingToWishlist(): void {
  if (lastAdded.value) wishlistMutation.mutate(lastAdded.value.registration.collectionEntryId)
}

function openScannedSeries(item: ScanFeedItem): void {
  if (item.collectionEntryId) openSeries(item.collectionEntryId)
}

// ── Scan: every barcode read adds its tome — the series follows ──────────────
// The camera and the phone live in the scan panel (leaving the tab unmounts it, which
// stops both); the feed stays here, still listed when the user comes back to the tab.

const readCount = ref(0)
const scanFeed = ref<ScanFeedItem[]>([])
let nextScanId = 1

const scannedCount = computed(() => scanFeed.value.filter((item) => item.status === 'added').length)

function updateScan(scanId: number, patch: Partial<ScanFeedItem>): void {
  scanFeed.value = scanFeed.value.map((item) => (item.id === scanId ? { ...item, ...patch } : item))
}

async function onBarcode(code: string): Promise<void> {
  const isbn = normalizeIsbn13(code)
  // A price sticker or a shop label read again and again is listed once.
  if (!isbn && scanFeed.value.some((item) => item.code === code)) return

  const scanId = nextScanId++
  readCount.value++
  scanFeed.value = [
    {
      id: scanId,
      code,
      status: isbn ? 'pending' : 'invalid',
      workTitle: null,
      publisher: null,
      specialEdition: null,
      volumeNumber: null,
      coverUrl: null,
      seriesCreated: false,
      totalVolumes: null,
      collectionEntryId: null,
      volumeEntryId: null,
    },
    ...scanFeed.value,
  ]
  if (!isbn) return

  try {
    const result = await scanIsbn(isbn)
    const volume = result.edition.volumes.find((candidate) => candidate.number === result.volumeNumber)
    updateScan(scanId, {
      status: result.alreadyOwned ? 'owned' : 'added',
      workTitle: result.edition.workTitle,
      publisher: result.edition.publisher,
      specialEdition: result.edition.specialEdition,
      volumeNumber: result.volumeNumber,
      coverUrl: volume?.coverUrl ?? result.edition.coverUrl,
      seriesCreated: result.registration.seriesCreated,
      totalVolumes: result.registration.totalVolumes,
      collectionEntryId: result.registration.collectionEntryId,
      volumeEntryId: result.registration.volumeEntryIds[String(result.volumeNumber)] ?? null,
    })
    refreshCollection()
  } catch (error) {
    updateScan(scanId, { status: httpStatus(error) === 404 ? 'notFound' : 'error' })
  }
}

async function undoScan(item: ScanFeedItem): Promise<void> {
  if (!item.collectionEntryId || !item.volumeEntryId) return
  try {
    await toggleVolume(item.collectionEntryId, item.volumeEntryId, 'isOwned')
    updateScan(item.id, { status: 'undone' })
    refreshCollection()
  } catch {
    ui.addToast(t('scanBatch.undoError'), 'error')
  }
}

function searchScannedCode(item: ScanFeedItem): void {
  searchInput.value = item.code
  tab.value = 'search'
}

onUnmounted(() => {
  if (debounceTimer) clearTimeout(debounceTimer)
})

// ── Manual entry: last resort when no catalogue knows the book ───────────────

const manual = ref<ManualSeriesDraft>({
  title: '',
  publisher: '',
  specialEdition: '',
  author: '',
  totalVolumes: '',
  coverUrl: '',
})

const manualMutation = useMutation({
  mutationFn: async () => {
    const created = await importManga({
      title: manual.value.title.trim(),
      language: 'fr',
      edition: manual.value.publisher.trim() || undefined,
      specialEdition: manual.value.specialEdition.trim() || undefined,
      author: manual.value.author.trim() || undefined,
      coverUrl: manual.value.coverUrl.trim() || undefined,
      totalVolumes: manual.value.totalVolumes !== '' ? Number(manual.value.totalVolumes) : undefined,
    })
    return addToCollection(created.id)
  },
  onSuccess: (entry) => {
    refreshCollection()
    ui.addToast(t('collection.added'), 'success')
    openSeries(entry.id)
  },
  onError: () => ui.addToast(t('add.addError'), 'error'),
})
</script>

<template>
  <div class="p-4 md:p-6 max-w-3xl mx-auto space-y-5">
    <!-- Header -->
    <div class="flex items-start gap-3">
      <div class="flex-1">
        <h1 class="text-2xl font-bold">{{ t('add.title') }}</h1>
        <p class="text-sm text-base-content/60 mt-1">{{ t('add.subtitle') }}</p>
      </div>
      <button
        type="button"
        class="btn btn-ghost btn-sm gap-1.5 text-base-content/60 hover:text-primary"
        :title="t('guide.openTooltip')"
        :aria-label="t('guide.openTooltip')"
        @click="showGuide = true"
      >
        <HelpCircle class="h-4 w-4" />
        <span class="hidden sm:inline">{{ t('guide.openLabel') }}</span>
      </button>
    </div>

    <!-- How it works -->
    <ol class="grid grid-cols-3 gap-2 text-[11px] sm:text-xs text-base-content/60">
      <li v-for="step in 3" :key="step" class="flex items-start gap-2 bg-base-100 rounded-xl p-2.5 border border-base-200">
        <span class="h-5 w-5 shrink-0 rounded-full bg-primary/15 text-primary font-bold flex items-center justify-center">
          {{ step }}
        </span>
        <span>{{ t(`add.step${step}`) }}</span>
      </li>
    </ol>

    <!-- Tabs -->
    <div role="tablist" class="grid grid-cols-3 p-1 bg-base-200 rounded-xl gap-1">
      <button
        v-for="option in TABS"
        :key="option"
        role="tab"
        :aria-selected="tab === option"
        class="btn btn-sm border-0 gap-1.5"
        :class="tab === option ? 'btn-primary' : 'btn-ghost'"
        @click="tab = option"
      >
        <Search v-if="option === 'search'" class="h-4 w-4" />
        <ScanBarcode v-else-if="option === 'scan'" class="h-4 w-4" />
        <PenLine v-else class="h-4 w-4" />
        {{ t(`add.tab.${option}`) }}
      </button>
    </div>

    <!-- Confirmation after an addition -->
    <AddedSeriesAlert
      v-if="lastAdded"
      :summary="lastAdded"
      :sending-to-wishlist="wishlistMutation.isPending.value"
      @open-series="openLastAddedSeries"
      @send-to-wishlist="sendMissingToWishlist"
      @dismiss="lastAdded = null"
    />

    <!-- ── Search ── -->
    <CatalogueSearchPanel
      v-if="tab === 'search'"
      v-model:query="searchInput"
      v-model:mode="searchMode"
      :is-searching="isSearching"
      :has-query="hasQuery"
      :error-message="searchErrorMessage"
      :result="searchResult"
      @select="openEdition"
      @fill-manually="tab = 'manual'"
    />

    <!-- ── Scan ── -->
    <ShelfScanPanel
      v-else-if="tab === 'scan'"
      :feed="scanFeed"
      :read-count="readCount"
      :scanned-count="scannedCount"
      @barcode="onBarcode"
      @undo="undoScan"
      @open-series="openScannedSeries"
      @search-manually="searchScannedCode"
      @clear-feed="scanFeed = []"
    />

    <!-- ── Manual entry ── -->
    <ManualSeriesForm
      v-else
      v-model="manual"
      :submitting="manualMutation.isPending.value"
      @submit="manualMutation.mutate()"
    />

    <CatalogueEditionSheet
      :open="selectedEdition !== null"
      :edition="sheetEdition"
      :requested-volume="requestedVolume"
      :loading-volumes="isLoadingEdition"
      :adding="addMutation.isPending.value"
      @close="selectedEdition = null"
      @add="addSelection"
    />

    <CollectionGuideModal :open="showGuide" @close="showGuide = false" />
  </div>
</template>
