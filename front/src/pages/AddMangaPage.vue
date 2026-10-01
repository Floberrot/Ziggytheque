<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { useI18n } from 'vue-i18n'
import {
  Camera, CheckCircle2, HelpCircle, PenLine, ScanBarcode, Search, Smartphone, Star, X,
} from 'lucide-vue-next'
import {
  addFromCatalogue, getCatalogueEdition, scanIsbn, searchCatalogue,
  type AddFromCataloguePayload, type CatalogueEdition, type CatalogueRegistration, type CatalogueSearchMode,
} from '@/api/catalogue'
import { addRemainingToWishlist, addToCollection, toggleVolume } from '@/api/collection'
import { createScanSession, importManga } from '@/api/manga'
import { useBarcodeScanner } from '@/composables/useBarcodeScanner'
import { useScanSession } from '@/composables/useScanSession'
import { useUiStore } from '@/stores/useUiStore'
import type { CatalogueSelection, ScanFeedItem } from '@/types'
import { normalizeIsbn13 } from '@/utils/isbn'
import BaseEditionSelector from '@/components/atoms/BaseEditionSelector.vue'
import BaseLoader from '@/components/atoms/BaseLoader.vue'
import BaseQrCode from '@/components/atoms/BaseQrCode.vue'
import EditionBadge from '@/components/molecules/EditionBadge.vue'
import ScanViewfinder from '@/components/molecules/ScanViewfinder.vue'
import CatalogueEditionCard from '@/components/organisms/CatalogueEditionCard.vue'
import CatalogueEditionSheet from '@/components/organisms/CatalogueEditionSheet.vue'
import CollectionGuideModal from '@/components/organisms/CollectionGuideModal.vue'
import ScanFeed from '@/components/organisms/ScanFeed.vue'

const route = useRoute()
const router = useRouter()
const queryClient = useQueryClient()
const ui = useUiStore()
const { t } = useI18n()

const showGuide = ref(false)
const tab = ref<'search' | 'scan' | 'manual'>(route.query.tab === 'scan' ? 'scan' : 'search')

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

const viewfinder = ref<InstanceType<typeof ScanViewfinder> | null>(null)
const cameraOn = ref(false)
const readCount = ref(0)
const scanner = useBarcodeScanner()
const phoneSession = useScanSession()
const phoneQrValue = ref<string | null>(null)
const isOpeningPhoneSession = ref(false)
const scanFeed = ref<ScanFeedItem[]>([])
let nextScanId = 1

const scannedCount = computed(() => scanFeed.value.filter((item) => item.status === 'added').length)

function updateScan(id: number, patch: Partial<ScanFeedItem>): void {
  scanFeed.value = scanFeed.value.map((item) => (item.id === id ? { ...item, ...patch } : item))
}

async function onBarcode(code: string): Promise<void> {
  const isbn = normalizeIsbn13(code)
  // A price sticker or a shop label read again and again is listed once.
  if (!isbn && scanFeed.value.some((item) => item.code === code)) return

  const id = nextScanId++
  readCount.value++
  scanFeed.value = [
    {
      id,
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
    updateScan(id, {
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
    updateScan(id, { status: httpStatus(error) === 404 ? 'notFound' : 'error' })
  }
}

async function startCamera(): Promise<void> {
  cameraOn.value = true
  // The <video> is rendered once cameraOn flips — wait for it.
  await nextTick()
  const video = viewfinder.value?.video
  if (video) {
    await scanner.startContinuous(video, onBarcode)
  }
}

function stopCamera(): void {
  scanner.stop()
  cameraOn.value = false
}

async function startPhoneScan(): Promise<void> {
  isOpeningPhoneSession.value = true
  try {
    const session = await createScanSession()
    phoneQrValue.value = `${window.location.origin}/scan/${session.scanToken}?batch=1`
    phoneSession.start(session, { onResult: onBarcode })
  } catch {
    ui.addToast(t('scanBatch.phoneError'), 'error')
  } finally {
    isOpeningPhoneSession.value = false
  }
}

function stopPhoneScan(): void {
  phoneSession.close()
  phoneQrValue.value = null
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

watch(tab, (next) => {
  if (next !== 'scan') {
    stopCamera()
    stopPhoneScan()
  }
})

onUnmounted(() => {
  if (debounceTimer) clearTimeout(debounceTimer)
})

// ── Manual entry: last resort when no catalogue knows the book ───────────────

const manual = ref({
  title: '',
  publisher: '',
  specialEdition: '',
  author: '',
  totalVolumes: '' as string | number,
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
        v-for="option in (['search', 'scan', 'manual'] as const)"
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
    <div v-if="lastAdded" class="alert alert-success items-start">
      <CheckCircle2 class="h-6 w-6 shrink-0" />
      <div class="flex-1 min-w-0 space-y-1">
        <p class="font-semibold">{{ lastAdded.workTitle }}</p>
        <EditionBadge :publisher="lastAdded.publisher" :special-edition="lastAdded.specialEdition" />
        <p class="text-sm">
          <template v-if="lastAdded.registration.seriesCreated">
            {{ t('add.seriesCreated', { count: lastAdded.registration.totalVolumes }) }}
          </template>
          <template v-if="lastAdded.registration.addedNumbers.length">
            {{ t('add.tomesAdded', { list: lastAdded.registration.addedNumbers.join(', ') }) }}
          </template>
          <template v-else>{{ t('add.seriesFollowed') }}</template>
        </p>
        <div class="flex flex-wrap gap-2 pt-1">
          <button class="btn btn-sm" @click="openLastAddedSeries">
            {{ t('add.openSeries') }}
          </button>
          <button
            class="btn btn-sm btn-ghost gap-1"
            :disabled="wishlistMutation.isPending.value"
            @click="sendMissingToWishlist"
          >
            <BaseLoader v-if="wishlistMutation.isPending.value" size="xs" />
            <Star v-else class="h-4 w-4" />
            {{ t('add.missingToWishlist') }}
          </button>
        </div>
      </div>
      <button class="btn btn-ghost btn-xs btn-circle" :aria-label="t('common.close')" @click="lastAdded = null">
        <X class="h-4 w-4" />
      </button>
    </div>

    <!-- ── Search ── -->
    <section v-if="tab === 'search'" class="space-y-3">
      <div class="flex flex-col sm:flex-row gap-2">
        <label class="input input-bordered flex items-center gap-2 flex-1">
          <Search class="h-4 w-4 opacity-50 shrink-0" />
          <input
            v-model="searchInput"
            type="search"
            class="grow"
            :placeholder="searchMode === 'title' ? t('add.searchPlaceholderTitle') : t('add.searchPlaceholderAuthor')"
            autocomplete="off"
            autofocus
          />
          <BaseLoader v-if="isSearching" size="xs" class="opacity-50" />
        </label>
        <div class="join shrink-0">
          <button
            v-for="option in (['title', 'author'] as const)"
            :key="option"
            class="btn join-item"
            :class="searchMode === option ? 'btn-primary' : 'btn-outline'"
            @click="searchMode = option"
          >
            {{ t(`add.mode.${option}`) }}
          </button>
        </div>
      </div>
      <p class="text-xs text-base-content/40">{{ t('add.searchHint') }}</p>

      <template v-if="hasQuery">
        <div v-if="searchErrorMessage" class="alert alert-warning text-sm py-2">{{ searchErrorMessage }}</div>

        <div v-else-if="searchResult && searchResult.editions.length" class="space-y-2">
          <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">
            {{ t('add.resultsCount', { count: searchResult.editions.length }, searchResult.editions.length) }}
          </p>
          <CatalogueEditionCard
            v-for="edition in searchResult.editions"
            :key="`${edition.workTitle}|${edition.publisher}|${edition.specialEdition}`"
            :edition="edition"
            @select="openEdition"
          />
        </div>

        <div
          v-else-if="searchResult && !isSearching"
          class="text-center py-8 space-y-3"
        >
          <p class="text-sm text-base-content/50">{{ t('add.noResults') }}</p>
          <button class="btn btn-outline btn-sm" @click="tab = 'manual'">{{ t('add.fillManually') }}</button>
        </div>
      </template>
    </section>

    <!-- ── Scan ── -->
    <section v-else-if="tab === 'scan'" class="space-y-4">
      <p class="text-sm text-base-content/60">{{ t('scanBatch.intro') }}</p>

      <div class="grid gap-2 sm:grid-cols-2">
        <button v-if="!cameraOn" class="btn btn-primary gap-2" @click="startCamera">
          <Camera class="h-5 w-5" />
          {{ t('scanBatch.startCamera') }}
        </button>
        <button v-else class="btn btn-outline gap-2" @click="stopCamera">
          <X class="h-5 w-5" />
          {{ t('scanBatch.stopCamera') }}
        </button>

        <button v-if="!phoneQrValue" class="btn btn-outline gap-2" :disabled="isOpeningPhoneSession" @click="startPhoneScan">
          <BaseLoader v-if="isOpeningPhoneSession" size="xs" />
          <Smartphone v-else class="h-5 w-5" />
          {{ t('scanBatch.usePhone') }}
        </button>
        <button v-else class="btn btn-outline gap-2" @click="stopPhoneScan">
          <X class="h-5 w-5" />
          {{ t('scanBatch.stopPhone') }}
        </button>
      </div>

      <div v-if="cameraOn" class="space-y-2">
        <ScanViewfinder
          ref="viewfinder"
          :read-count="readCount"
          :torch-available="scanner.torchAvailable.value"
          :torch-on="scanner.torchOn.value"
          @toggle-torch="scanner.toggleTorch()"
        />
        <p v-if="scanner.errorMessage.value" class="alert alert-error text-sm py-2">{{ scanner.errorMessage.value }}</p>
        <p v-else class="text-xs text-center text-base-content/50">{{ t('scanBatch.cameraHint') }}</p>
      </div>

      <div v-if="phoneQrValue" class="flex flex-col items-center gap-2 p-4 bg-base-100 rounded-2xl border border-base-200">
        <BaseQrCode :value="phoneQrValue" :size="200" />
        <p class="text-xs text-center text-base-content/60 max-w-xs">{{ t('scanBatch.phoneHint') }}</p>
      </div>

      <div v-if="scanFeed.length" class="space-y-2">
        <div class="flex items-center justify-between">
          <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">
            {{ t('scanBatch.summary', { count: scannedCount }, scannedCount) }}
          </p>
          <button class="btn btn-ghost btn-xs" @click="scanFeed = []">{{ t('scanBatch.clear') }}</button>
        </div>
        <ScanFeed
          :items="scanFeed"
          @undo="undoScan"
          @open-series="openScannedSeries"
          @search-manually="searchScannedCode"
        />
      </div>
    </section>

    <!-- ── Manual entry ── -->
    <section v-else class="space-y-4">
      <p class="text-sm text-base-content/60">{{ t('add.manualIntro') }}</p>
      <form class="space-y-3" @submit.prevent="manualMutation.mutate()">
        <label class="flex flex-col gap-1">
          <span class="text-xs font-semibold text-base-content/60">{{ t('manga.title') }} *</span>
          <input v-model="manual.title" type="text" class="input input-bordered w-full" maxlength="255" required />
        </label>
        <div class="grid gap-3 sm:grid-cols-2">
          <div class="flex flex-col gap-1">
            <span class="text-xs font-semibold text-base-content/60">{{ t('catalogue.publisher') }}</span>
            <BaseEditionSelector
              :model-value="manual.publisher || null"
              input-class="input input-bordered w-full"
              @update:model-value="manual.publisher = $event ?? ''"
            />
          </div>
          <label class="flex flex-col gap-1">
            <span class="text-xs font-semibold text-base-content/60">{{ t('catalogue.specialEdition') }}</span>
            <input
              v-model="manual.specialEdition"
              type="text"
              class="input input-bordered w-full"
              maxlength="150"
              :placeholder="t('catalogue.standardEdition')"
            />
          </label>
          <label class="flex flex-col gap-1">
            <span class="text-xs font-semibold text-base-content/60">{{ t('manga.author') }}</span>
            <input v-model="manual.author" type="text" class="input input-bordered w-full" />
          </label>
          <label class="flex flex-col gap-1">
            <span class="text-xs font-semibold text-base-content/60">{{ t('manga.totalVolumes') }}</span>
            <input v-model="manual.totalVolumes" type="number" min="0" max="500" class="input input-bordered w-full" />
          </label>
        </div>
        <label class="flex flex-col gap-1">
          <span class="text-xs font-semibold text-base-content/60">{{ t('manga.coverUrl') }}</span>
          <input v-model="manual.coverUrl" type="url" class="input input-bordered w-full" placeholder="https://…" />
        </label>
        <button type="submit" class="btn btn-primary w-full" :disabled="manualMutation.isPending.value || !manual.title.trim()">
          <BaseLoader v-if="manualMutation.isPending.value" size="xs" />
          {{ t('add.createManually') }}
        </button>
      </form>
    </section>

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
