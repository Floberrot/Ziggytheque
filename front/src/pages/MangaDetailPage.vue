<script setup lang="ts">
import { ref, computed, watch, defineAsyncComponent } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { useI18n } from 'vue-i18n'
import { ArrowLeft, Bell, Languages, Plus } from 'lucide-vue-next'
import {
  getCollectionEntry,
  removeFromCollection,
  updateReadingStatus,
  toggleVolume,
  addRemainingToWishlist,
  syncVolumes,
  batchSetVolumePrice,
  updateCollectionRating,
  toggleFollow,
} from '@/api/collection'
import { updateManga, updateVolume, autoFillCovers, translateSummary, type MangaUpdatePayload } from '@/api/manga'
import { searchCatalogue, type CatalogueEdition } from '@/api/catalogue'
import { useCoverBatchProgress, type CoverBatchProgress } from '@/composables/useCoverBatchProgress'
import { useIsMobile } from '@/composables/useMediaQuery'
import { useVolumeToggle } from '@/composables/useVolumeToggle'
import { useUiStore } from '@/stores/useUiStore'
import type { ReadingStatus, VolumeEntry, VolumeToggleField } from '@/types'
import { coverUrl } from '@/utils/coverUrl'
import type { ScreenPoint } from '@/utils/pointer'
import { VOLUME_BATCH_RULES, batchTargets, type VolumeBatchAction } from '@/utils/volumeBatch'
import BaseButton from '@/components/atoms/BaseButton.vue'
import BaseLoader from '@/components/atoms/BaseLoader.vue'
import BatchPricePanel from '@/components/molecules/BatchPricePanel.vue'
import ReadingStatusMenu from '@/components/molecules/ReadingStatusMenu.vue'
import RemoveSeriesDialog from '@/components/molecules/RemoveSeriesDialog.vue'
import SeriesMoreMenu from '@/components/molecules/SeriesMoreMenu.vue'
import SeriesProgressMeters from '@/components/molecules/SeriesProgressMeters.vue'
import SyncVolumesPanel from '@/components/molecules/SyncVolumesPanel.vue'
import CollectionGuideModal from '@/components/organisms/CollectionGuideModal.vue'
import SeriesCoverEditor from '@/components/organisms/SeriesCoverEditor.vue'
import SeriesEditionsList from '@/components/organisms/SeriesEditionsList.vue'
import SeriesIdentityEditor from '@/components/organisms/SeriesIdentityEditor.vue'
import VolumeActionSheet from '@/components/organisms/VolumeActionSheet.vue'
import VolumeBatchBar from '@/components/organisms/VolumeBatchBar.vue'
import VolumeContextMenu from '@/components/organisms/VolumeContextMenu.vue'
import VolumeGrid from '@/components/organisms/VolumeGrid.vue'
import VolumePriceGrid from '@/components/organisms/VolumePriceGrid.vue'

// The cover / ISBN / scan / price tool (and its QR code library) is fetched once the
// page is up, not with it; it handles being mounted already open.
const EnrichVolumeModal = defineAsyncComponent(() => import('@/components/organisms/EnrichVolumeModal.vue'))

type DetailTab = 'volumes' | 'editions' | 'prix'
type EnrichMode = 'search' | 'isbn' | 'scan' | 'prix'

const route = useRoute()
const router = useRouter()
const queryClient = useQueryClient()
const ui = useUiStore()
const { t } = useI18n()

const collectionEntryId = route.params.id as string

// Below `sm`, the tome quick actions open as a bottom sheet instead of a context menu.
const isMobile = useIsMobile()

// ── Tab navigation (the URL keeps the historical "prix") ──
const TABS: readonly DetailTab[] = ['volumes', 'editions', 'prix']
const TAB_LABEL_KEYS: Record<DetailTab, string> = {
  volumes: 'manga.tabs.volumes',
  editions: 'manga.tabs.editions',
  prix: 'manga.tabs.prices',
}

const tabParam = computed<DetailTab>(() => {
  const tab = route.query.tab as string | undefined
  if (tab === 'editions' || tab === 'prix') return tab
  return 'volumes'
})

function setTab(tab: DetailTab): void {
  router.replace({ query: { ...route.query, tab: tab === 'volumes' ? undefined : tab } })
}

// ── Guide / help modal ──
const showGuide = ref(false)

const { data: entry, isPending } = useQuery({
  queryKey: ['collection', collectionEntryId],
  queryFn: () => getCollectionEntry(collectionEntryId),
})

watch(entry, (mangaEntry) => {
  if (mangaEntry) document.title = `${mangaEntry.manga.title} — Ziggy`
}, { immediate: true })

// ── Editions tab: every French edition of the work, straight from the catalogues ──
const {
  data: workEditions,
  isFetching: editionsLoading,
  isError: editionsFailed,
} = useQuery({
  queryKey: computed(() => ['catalogue', 'search', 'title', entry.value?.manga.title ?? '']),
  queryFn: () => searchCatalogue(entry.value!.manga.title, 'title'),
  enabled: computed(() => tabParam.value === 'editions' && (entry.value?.manga.title.length ?? 0) >= 2),
  staleTime: 5 * 60 * 1000,
  retry: false,
})

function openCatalogueEdition(edition: CatalogueEdition): void {
  if (edition.collection) {
    if (edition.collection.entryId !== collectionEntryId) {
      router.push({ name: 'collection-detail', params: { id: edition.collection.entryId } })
    }
    return
  }
  router.push({
    name: 'add',
    query: { q: [edition.workTitle, edition.specialEdition].filter(Boolean).join(' ') },
  })
}

const sortedVolumes = computed<VolumeEntry[]>(() =>
  [...(entry.value?.volumes ?? [])].sort((left, right) => left.number - right.number),
)

const missingVolumes = computed(() => sortedVolumes.value.filter((volume) => !volume.isOwned && !volume.isWished))

// ── Summary translation (EN → FR, on demand) ──
const showTranslation = ref(false)
const translatedSummary = ref<string | null>(null)

const translateMutation = useMutation({
  mutationFn: (text: string) => translateSummary(text),
  onSuccess: (text) => {
    translatedSummary.value = text
    showTranslation.value = true
  },
  onError: () => ui.addToast(t('manga.translateError'), 'error'),
})

const displayedSummary = computed(() =>
  showTranslation.value && translatedSummary.value
    ? translatedSummary.value
    : entry.value?.manga.summary ?? '',
)

function toggleTranslation(): void {
  if (showTranslation.value) {
    showTranslation.value = false
    return
  }
  if (translatedSummary.value) {
    showTranslation.value = true
    return
  }
  const summary = entry.value?.manga.summary
  if (summary) translateMutation.mutate(summary)
}

// ── Tome modal (cover / ISBN / scan / prices) ──
const modalVolumeId = ref<string | null>(null)
const modalInitialMode = ref<EnrichMode>('search')
const modalOpen = computed(() => modalVolumeId.value !== null)
const modalVolume = computed(() => sortedVolumes.value.find((volume) => volume.id === modalVolumeId.value) ?? null)

function openVolumeModal(volume: VolumeEntry, mode: EnrichMode = 'search'): void {
  modalInitialMode.value = mode
  modalVolumeId.value = volume.id
}

function closeModal(): void {
  modalVolumeId.value = null
  modalInitialMode.value = 'search'
}

// ── Inline edits of the series (title, publisher, special edition, cover) ──
const editingTitle = ref(false)
const editingEdition = ref(false)
const editingSpecialEdition = ref(false)
const editingCover = ref(false)

function saveCover(newCoverUrl: string): void {
  updateMangaMutation.mutate({ coverUrl: newCoverUrl })
  editingCover.value = false
}

// ── Action bar: progressive-disclosure menus & on-demand price ──
const statusMenuOpen = ref(false)
const moreMenuOpen = ref(false)
const showPrice = ref(false)

function closeActionMenus(): void {
  statusMenuOpen.value = false
  moreMenuOpen.value = false
}

function toggleStatusMenu(): void {
  statusMenuOpen.value = !statusMenuOpen.value
  moreMenuOpen.value = false
}

function toggleMoreMenu(): void {
  moreMenuOpen.value = !moreMenuOpen.value
  statusMenuOpen.value = false
}

function pickStatus(status: ReadingStatus): void {
  if (entry.value && entry.value.readingStatus !== status) statusMutation.mutate(status)
  statusMenuOpen.value = false
}

// ── Batch price (the panel normalises the typed value) ──
const batchPrice = ref<number | string | null>(null)

// ── "Add tomes" panel ──
const showSyncPanel = ref(false)
const syncTarget = ref<number | ''>('')

// ── Delete confirm ──
const showDeleteConfirm = ref(false)

// ── Batch selection ──
const batchMode = ref(false)
const selectedIds = ref<Set<string>>(new Set())

const selectedVolumes = computed(() =>
  sortedVolumes.value.filter((volume) => selectedIds.value.has(volume.id)),
)

function toggleBatchMode(): void {
  batchMode.value = !batchMode.value
  if (!batchMode.value) selectedIds.value = new Set()
}

function toggleSelection(volumeEntryId: string): void {
  const next = new Set(selectedIds.value)
  if (next.has(volumeEntryId)) next.delete(volumeEntryId)
  else next.add(volumeEntryId)
  selectedIds.value = next
}

function onVolumeActivate(volume: VolumeEntry): void {
  if (batchMode.value) toggleSelection(volume.id)
  else openVolumeModal(volume)
}

// ── Tome quick actions ──
// Desktop: right-click (or Menu key) context menu. Mobile: bottom sheet, opened either
// by the visible "⋯" button of each tile or by a long press (contextmenu event).
const contextMenu = ref<{ volume: VolumeEntry; point: ScreenPoint } | null>(null)
const actionSheetVolumeId = ref<string | null>(null)

// Derived from the query cache so optimistic toggles refresh the open sheet.
const actionSheetVolume = computed(() =>
  sortedVolumes.value.find((volume) => volume.id === actionSheetVolumeId.value) ?? null,
)

function openVolumeActions(volume: VolumeEntry): void {
  actionSheetVolumeId.value = volume.id
}

function openContextMenu(volume: VolumeEntry, point: ScreenPoint): void {
  if (isMobile.value) {
    openVolumeActions(volume)
    return
  }
  contextMenu.value = { volume, point }
}

function closeContextMenu(): void {
  contextMenu.value = null
}

function closeActionSheet(): void {
  actionSheetVolumeId.value = null
}

function openModalFromContext(): void {
  if (contextMenu.value) {
    openVolumeModal(contextMenu.value.volume)
    closeContextMenu()
  }
}

function openModalFromActionSheet(): void {
  const volume = actionSheetVolume.value
  if (volume) {
    closeActionSheet()
    openVolumeModal(volume)
  }
}

// ── Mutations ──
const removeMutation = useMutation({
  mutationFn: () => removeFromCollection(collectionEntryId),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['collection'] })
    ui.addToast(t('collection.removed'), 'success')
    router.push({ name: 'collection' })
  },
})

const statusMutation = useMutation({
  mutationFn: (status: ReadingStatus) => updateReadingStatus(collectionEntryId, status),
  onSuccess: () => queryClient.invalidateQueries({ queryKey: ['collection', collectionEntryId] }),
})

// Optimistic flag toggle of a tome — from the context menu, the sheet or the tome modal.
const toggleMutation = useVolumeToggle(collectionEntryId, { onMutate: closeContextMenu })

function toggleVolumeField(volume: VolumeEntry | null | undefined, field: VolumeToggleField): void {
  if (volume) toggleMutation.mutate({ volumeEntryId: volume.id, field })
}

function toggleFromContext(field: VolumeToggleField): void {
  toggleVolumeField(contextMenu.value?.volume, field)
}

function toggleFromActionSheet(field: VolumeToggleField): void {
  toggleVolumeField(actionSheetVolume.value, field)
}

function toggleFromModal(field: VolumeToggleField): void {
  toggleVolumeField(modalVolume.value, field)
}

const addToWishlistMutation = useMutation({
  mutationFn: () => addRemainingToWishlist(collectionEntryId),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['collection', collectionEntryId] })
    queryClient.invalidateQueries({ queryKey: ['wishlist'] })
    queryClient.invalidateQueries({ queryKey: ['stats'] })
    ui.addToast(t('manga.missingWished'), 'success')
  },
})

const syncMutation = useMutation({
  mutationFn: () => syncVolumes(collectionEntryId, syncTarget.value !== '' ? Number(syncTarget.value) : undefined),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['collection', collectionEntryId] })
    queryClient.invalidateQueries({ queryKey: ['collection'] })
    showSyncPanel.value = false
    syncTarget.value = ''
    ui.addToast(t('manga.volumesSynced'), 'success')
  },
})

const batchPriceMutation = useMutation({
  mutationFn: (price: number) => batchSetVolumePrice(collectionEntryId, price),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['collection', collectionEntryId] })
    queryClient.invalidateQueries({ queryKey: ['stats'] })
    batchPrice.value = null
    ui.addToast(t('manga.batchPriceApplied'), 'success')
  },
})

const updateMangaMutation = useMutation({
  mutationFn: (payload: MangaUpdatePayload) => updateManga(entry.value!.manga.id, payload),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['collection', collectionEntryId] })
    queryClient.invalidateQueries({ queryKey: ['collection'] })
    editingTitle.value = false
    editingEdition.value = false
    editingSpecialEdition.value = false
    ui.addToast(t('manga.updated'), 'success')
  },
  onError: () => ui.addToast(t('manga.updateError'), 'error'),
})

// Bell wiggles on every click; the animation is keyed so rapid clicks restart it.
const bellRinging = ref(false)
function onFollowClick(): void {
  bellRinging.value = true
  followMutation.mutate()
}

const followMutation = useMutation({
  mutationFn: () => toggleFollow(collectionEntryId),
  onSuccess: (data) => {
    queryClient.invalidateQueries({ queryKey: ['collection'] })
    queryClient.invalidateQueries({ queryKey: ['collection', collectionEntryId] })
    ui.addToast(
      data.notificationsEnabled ? t('notifications.followOn') : t('notifications.followOff'),
      data.notificationsEnabled ? 'success' : 'info',
    )
  },
})

const ratingMutation = useMutation({
  mutationFn: (rating: number) => updateCollectionRating(collectionEntryId, rating),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['collection', collectionEntryId] })
    ui.addToast(t('rating.saved'), 'success')
  },
  onError: () => ui.addToast(t('rating.error'), 'error'),
})

// ── Tome modal writes: the modal emits, the page saves ──
const isbnSaveMutation = useMutation({
  mutationFn: ({ volumeId, isbn }: { volumeId: string; isbn: string }) =>
    updateVolume(entry.value!.manga.id, volumeId, { isbn }),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['collection', collectionEntryId] })
    ui.addToast(t('enrich.isbnSaved'), 'success')
  },
  onError: () => {
    ui.addToast(t('enrich.isbnSaveError'), 'error')
  },
})

function saveModalVolumeIsbn(isbn: string): void {
  const volume = modalVolume.value
  if (volume) isbnSaveMutation.mutate({ volumeId: volume.volumeId, isbn })
}

const applyCoverMutation = useMutation({
  mutationFn: ({ volumeId, coverUrl: newCoverUrl, isbn }: { volumeId: string; coverUrl: string; isbn?: string }) =>
    updateVolume(entry.value!.manga.id, volumeId, { coverUrl: newCoverUrl, isbn }),
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['collection', collectionEntryId] })
    ui.addToast(t('enrich.coverUpdated'), 'success')
    closeModal()
  },
})

function applyModalVolumeCover(cover: { coverUrl: string; isbn?: string }): void {
  const volume = modalVolume.value
  if (volume) applyCoverMutation.mutate({ volumeId: volume.volumeId, ...cover })
}

// ── Automatic covers of every tome (progress streamed in a toast) ──
const batchProgress = useCoverBatchProgress()

const autoFillBusy = computed(
  () => autoFillMutation.isPending.value
    || (batchProgress.progress.value !== null && !batchProgress.progress.value.done),
)

function autoFillProgressLine(progress: CoverBatchProgress): string {
  const active = progress.resolved + progress.failed
  const counts = { resolved: progress.resolved, failed: progress.failed, active, total: progress.total }
  if (progress.lastType === 'batch_started') {
    return t('manga.autoFill.queued', { total: progress.total })
  }
  if (progress.lastType === 'volume_resolved') {
    return t('manga.autoFill.resolved', { number: progress.volumeNumber ?? '', ...counts })
  }
  if (progress.lastType === 'volume_failed') {
    return t('manga.autoFill.failed', { number: progress.volumeNumber ?? '', ...counts })
  }
  return t('manga.autoFill.processed', { active, total: progress.total })
}

function autoFillSummary(progress: CoverBatchProgress): string {
  const parts: string[] = []
  if (progress.resolved > 0) parts.push(t('manga.autoFill.found', { count: progress.resolved }))
  if (progress.failed > 0) parts.push(t('manga.autoFill.notFound', { count: progress.failed }))
  if (progress.skipped > 0) parts.push(t('manga.autoFill.skipped', { count: progress.skipped }))
  return parts.length > 0 ? parts.join(' · ') : t('manga.autoFill.done')
}

const autoFillMutation = useMutation({
  mutationFn: () => autoFillCovers(entry.value!.manga.id),
  onSuccess: (response) => {
    const toastId = ui.addProgressToast(t('manga.autoFill.starting'), 0)
    batchProgress.start(response, {
      onUpdate: (progress) => {
        ui.updateProgressToast(toastId, autoFillProgressLine(progress), progress.resolved + progress.failed, progress.total)
      },
      onDone: (progress) => {
        ui.closeProgressToast(
          toastId,
          autoFillSummary(progress),
          progress.failed > 0 && progress.resolved === 0 ? 'error' : 'success',
        )
        queryClient.invalidateQueries({ queryKey: ['collection', collectionEntryId] })
        queryClient.invalidateQueries({ queryKey: ['collection'] })
      },
      onError: () => {
        // The SSE stream died or never completed — the covers were still filled
        // server-side, so resync silently instead of leaving the toast hanging.
        ui.closeProgressToast(toastId, t('manga.autoFill.synced'), 'info')
        queryClient.invalidateQueries({ queryKey: ['collection', collectionEntryId] })
        queryClient.invalidateQueries({ queryKey: ['collection'] })
      },
    })
  },
  onError: () => ui.addToast(t('manga.autoFill.error'), 'error'),
})

// ── Batch operations ──
const isBatchProcessing = ref(false)

/**
 * The API flips a flag, so each action only reaches the tomes not yet in the wanted
 * state (`batchTargets`). Sent one after the other: concurrent toggles of the same
 * series would race on its reading status.
 */
async function batchApply(action: VolumeBatchAction): Promise<void> {
  const targets = batchTargets(selectedVolumes.value, action)
  if (targets.length === 0) return
  const { field } = VOLUME_BATCH_RULES[action]
  isBatchProcessing.value = true
  let updated = 0
  try {
    for (const volume of targets) {
      await toggleVolume(collectionEntryId, volume.id, field)
      updated++
    }
    selectedIds.value = new Set()
    ui.addToast(t('volume.batchUpdated', { count: updated }, updated), 'success')
  } catch {
    ui.addToast(t('volume.batchFailed', { done: updated, total: targets.length }), 'error')
  } finally {
    isBatchProcessing.value = false
    await queryClient.invalidateQueries({ queryKey: ['collection', collectionEntryId] })
    await queryClient.invalidateQueries({ queryKey: ['collection'] })
    await queryClient.invalidateQueries({ queryKey: ['wishlist'] })
    await queryClient.invalidateQueries({ queryKey: ['stats'] })
  }
}
</script>

<template>
  <div class="min-h-screen" @click="closeContextMenu(); editingCover = false; closeActionMenus()">
    <BaseLoader v-if="isPending" variant="page" />

    <template v-else-if="entry">
      <!-- Hero header with blurred cover bg -->
      <div class="relative">
        <!-- Clip only the blurred background, so action-bar menus can overflow the hero -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
          <div
            v-if="entry.manga.coverUrl"
            class="absolute inset-0 bg-cover bg-center blur-3xl scale-110 opacity-20"
            :style="{ backgroundImage: `url(${coverUrl(entry.manga.coverUrl)})` }"
          />
          <div class="absolute inset-0 bg-gradient-to-b from-base-100/60 to-base-100" />
        </div>

        <div class="relative max-w-5xl mx-auto px-4 sm:px-6 pt-6 sm:pt-8 pb-6">
          <RouterLink
            :to="{ name: 'collection' }"
            class="md:hidden inline-flex items-center gap-1.5 text-sm text-base-content/50 hover:text-base-content mb-4 transition-colors"
          >
            <ArrowLeft class="h-4 w-4" />
            {{ t('nav.collection') }}
          </RouterLink>
          <div class="flex flex-col sm:flex-row gap-5 sm:gap-6">
            <SeriesCoverEditor
              :cover-url="entry.manga.coverUrl"
              :title="entry.manga.title"
              :editing="editingCover"
              @edit="editingCover = true"
              @save="saveCover"
              @cancel="editingCover = false"
            />

            <div class="flex-1 min-w-0 space-y-3">
              <SeriesIdentityEditor
                v-model:editing-title="editingTitle"
                v-model:editing-edition="editingEdition"
                v-model:editing-special-edition="editingSpecialEdition"
                :manga="entry.manga"
                :rating="entry.rating"
                @save="updateMangaMutation.mutate($event)"
                @rate="ratingMutation.mutate($event)"
              />

              <!-- Stats — progress meters (owned / read, + wished when relevant) -->
              <SeriesProgressMeters
                :owned-count="entry.ownedCount"
                :read-count="entry.readCount"
                :wished-count="entry.wishedCount"
                :total-volumes="entry.totalVolumes"
              />

              <!-- Action bar — progressive disclosure: primary action + status menu + follow + overflow -->
              <div class="flex flex-wrap items-center gap-2.5">
                <!-- Primary : add volumes (toggles the sync panel) -->
                <button
                  class="btn btn-sm gap-1.5"
                  :class="showSyncPanel ? 'btn-primary' : 'btn-outline btn-primary'"
                  :aria-expanded="showSyncPanel"
                  @click="showSyncPanel = !showSyncPanel"
                >
                  <Plus class="h-4 w-4" stroke-width="2.4" />
                  {{ t('manga.addVolumes') }}
                </button>

                <ReadingStatusMenu
                  :status="entry.readingStatus"
                  :open="statusMenuOpen"
                  :pending="statusMutation.isPending.value"
                  @toggle="toggleStatusMenu"
                  @close="statusMenuOpen = false"
                  @pick="pickStatus"
                  @open-guide="statusMenuOpen = false; showGuide = true"
                />

                <div class="flex-1 min-w-2" />

                <!-- Follow / unfollow (icon button) -->
                <div
                  class="tooltip tooltip-top"
                  :data-tip="entry.notificationsEnabled ? t('manga.followingTooltip') : t('manga.followTooltip')"
                >
                  <button
                    class="btn btn-circle btn-sm w-9 h-9"
                    :class="entry.notificationsEnabled ? 'btn-secondary' : 'btn-ghost border border-base-content/15'"
                    :disabled="followMutation.isPending.value"
                    :aria-label="entry.notificationsEnabled ? t('notifications.following') : t('notifications.follow')"
                    :aria-pressed="entry.notificationsEnabled"
                    @click="onFollowClick()"
                  >
                    <Bell
                      class="h-4 w-4 origin-top"
                      :class="{ 'bell-ring': bellRinging }"
                      :fill="entry.notificationsEnabled ? 'currentColor' : 'none'"
                      @animationend="bellRinging = false"
                    />
                  </button>
                </div>

                <!-- Overflow menu : secondary actions tucked away -->
                <SeriesMoreMenu
                  :open="moreMenuOpen"
                  :missing-count="missingVolumes.length"
                  :auto-fill-disabled="autoFillBusy"
                  @toggle="toggleMoreMenu"
                  @close="moreMenuOpen = false"
                  @wish-missing="moreMenuOpen = false; addToWishlistMutation.mutate()"
                  @auto-fill="moreMenuOpen = false; autoFillMutation.mutate()"
                  @show-price="moreMenuOpen = false; showPrice = true"
                  @open-guide="moreMenuOpen = false; showGuide = true"
                  @remove="moreMenuOpen = false; showDeleteConfirm = true"
                />
              </div>

              <!-- "Add tomes" panel -->
              <Transition name="panel-fade">
                <SyncVolumesPanel
                  v-if="showSyncPanel"
                  v-model:target="syncTarget"
                  :total-volumes="entry.totalVolumes"
                  :pending="syncMutation.isPending.value"
                  @submit="syncMutation.mutate()"
                  @cancel="showSyncPanel = false"
                />
              </Transition>

              <!-- Batch price : revealed on demand via the overflow menu -->
              <Transition name="panel-fade">
                <BatchPricePanel
                  v-if="showPrice"
                  v-model:price="batchPrice"
                  :total-volumes="entry.totalVolumes"
                  :pending="batchPriceMutation.isPending.value"
                  @apply="batchPriceMutation.mutate($event)"
                  @close="showPrice = false"
                />
              </Transition>
            </div>
          </div>

          <div v-if="entry.manga.summary" class="mt-4 max-w-2xl">
            <p class="text-sm text-base-content/60 line-clamp-3">
              {{ displayedSummary }}
            </p>
            <BaseButton
              class="btn btn-ghost btn-xs gap-1 mt-1 px-1 text-base-content/50 hover:text-base-content"
              :loading="translateMutation.isPending.value"
              @click="toggleTranslation"
            >
              <template #icon><Languages class="h-3 w-3" /></template>
              {{ showTranslation ? t('manga.showOriginal') : t('manga.translate') }}
            </BaseButton>
          </div>
        </div>
      </div>

      <!-- Tab bar -->
      <div class="max-w-5xl mx-auto px-4 sm:px-6 pt-4 pb-0">
        <div class="flex gap-0 border-b border-base-300" role="tablist">
          <button
            v-for="tab in TABS"
            :key="tab"
            role="tab"
            :aria-selected="tabParam === tab"
            class="px-4 py-2 text-sm font-semibold border-b-2 transition-colors"
            :class="tabParam === tab
              ? 'border-primary text-primary'
              : 'border-transparent text-base-content/50 hover:text-base-content hover:border-base-content/20'"
            @click="setTab(tab)"
          >
            {{ t(TAB_LABEL_KEYS[tab]) }}
          </button>
        </div>
      </div>

      <!-- Volume grid -->
      <VolumeGrid
        v-if="tabParam === 'volumes'"
        class="max-w-5xl mx-auto px-4 sm:px-6 py-6"
        :volumes="sortedVolumes"
        :owned-count="entry.ownedCount"
        :total-volumes="entry.totalVolumes"
        :batch-mode="batchMode"
        :selected-ids="selectedIds"
        @toggle-batch-mode="toggleBatchMode"
        @select="selectedIds = $event"
        @activate="onVolumeActivate"
        @context-menu="openContextMenu"
        @open-actions="openVolumeActions"
      />

      <!-- Editions tab -->
      <SeriesEditionsList
        v-if="tabParam === 'editions'"
        class="max-w-3xl mx-auto px-4 sm:px-6 py-6"
        :work-title="entry.manga.title"
        :editions="workEditions?.editions ?? []"
        :loading="editionsLoading"
        :failed="editionsFailed"
        :current-entry-id="collectionEntryId"
        @select="openCatalogueEdition"
      />

      <!-- Prices tab -->
      <VolumePriceGrid
        v-if="tabParam === 'prix'"
        class="max-w-5xl mx-auto px-4 sm:px-6 py-6"
        :volumes="sortedVolumes"
        :selected-ids="selectedIds"
        @select="openVolumeModal($event, 'prix')"
      />

      <!-- Enrich Volume Modal -->
      <EnrichVolumeModal
        :open="modalOpen"
        :manga-id="entry.manga.id"
        :manga-title="entry.manga.title"
        :manga-edition="entry.manga.edition"
        :volume="modalVolume"
        :initial-mode="modalInitialMode"
        :saving-isbn="isbnSaveMutation.isPending.value"
        :applying-cover="applyCoverMutation.isPending.value"
        @close="closeModal"
        @toggle="toggleFromModal"
        @save-isbn="saveModalVolumeIsbn"
        @apply-cover="applyModalVolumeCover"
      />

      <!-- Guide / help modal -->
      <CollectionGuideModal :open="showGuide" @close="showGuide = false" />
    </template>
  </div>

  <!-- Tome quick actions: context menu (desktop) and bottom sheet (mobile) -->
  <VolumeContextMenu
    :request="contextMenu"
    :pending="toggleMutation.isPending.value"
    @toggle="toggleFromContext"
    @details="openModalFromContext"
    @close="closeContextMenu"
  />
  <VolumeActionSheet
    :volume="actionSheetVolume"
    :pending="toggleMutation.isPending.value"
    @toggle="toggleFromActionSheet"
    @details="openModalFromActionSheet"
    @close="closeActionSheet"
  />

  <VolumeBatchBar
    :open="batchMode && selectedIds.size > 0"
    :selected-volumes="selectedVolumes"
    :processing="isBatchProcessing"
    @apply="batchApply"
    @clear="selectedIds = new Set()"
  />

  <RemoveSeriesDialog
    :open="showDeleteConfirm"
    :title="entry?.manga.title ?? ''"
    :pending="removeMutation.isPending.value"
    @confirm="removeMutation.mutate()"
    @close="showDeleteConfirm = false"
  />
</template>

<style scoped>
.panel-fade-enter-active,
.panel-fade-leave-active {
  transition: opacity 0.18s ease, transform 0.18s ease, max-height 0.22s ease;
  overflow: hidden;
  max-height: 400px;
}
.panel-fade-enter-from,
.panel-fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
  max-height: 0;
}

/* Bell wiggle on follow toggle */
.bell-ring {
  animation: bell-ring 0.6s cubic-bezier(0.36, 0.07, 0.19, 0.97);
}
@keyframes bell-ring {
  0% { transform: rotate(0); }
  15% { transform: rotate(14deg); }
  30% { transform: rotate(-12deg); }
  45% { transform: rotate(9deg); }
  60% { transform: rotate(-6deg); }
  75% { transform: rotate(3deg); }
  100% { transform: rotate(0); }
}
</style>
