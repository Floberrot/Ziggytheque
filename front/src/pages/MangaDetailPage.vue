<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import {
  ArrowLeft, Star, Book, BookOpen, Check, CheckSquare, Pencil, Trash2, Eye, Tag, Megaphone, Package, Info, Bell, BellOff, Plus, Sparkles, HelpCircle, Languages, MoreHorizontal, ChevronDown, X,
} from 'lucide-vue-next'
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
import { updateManga, autoFillCovers, translateSummary } from '@/api/manga'
import { useCoverBatchProgress } from '@/composables/useCoverBatchProgress'
import { useUiStore } from '@/stores/useUiStore'
import { useI18n } from 'vue-i18n'
import EnrichVolumeModal from '@/components/organisms/EnrichVolumeModal.vue'
import BaseLoader from '@/components/atoms/BaseLoader.vue'
import CollectionGuideModal from '@/components/organisms/CollectionGuideModal.vue'
import BaseHeartRating from '@/components/atoms/BaseHeartRating.vue'
import { FRENCH_EDITIONS } from '@/data/editions'
import BaseEditionSelector from '@/components/atoms/BaseEditionSelector.vue'
import BaseLazyImage from '@/components/atoms/BaseLazyImage.vue'
import type { CollectionEntryDetail, ReadingStatus, VolumeEntry, VolumeToggleField } from '@/types'
import { coverUrl } from '@/utils/coverUrl'

const route = useRoute()
const router = useRouter()
const qc = useQueryClient()
const ui = useUiStore()
const { t } = useI18n()

const id = route.params.id as string

// ── Guide / help modal ──
const showGuide = ref(false)

const { data: entry, isPending } = useQuery({
  queryKey: ['collection', id],
  queryFn: () => getCollectionEntry(id),
})

watch(entry, (e) => {
  if (e) document.title = `${e.manga.title} — Ziggy`
}, { immediate: true })

const sortedVolumes = computed<VolumeEntry[]>(() =>
  [...(entry.value?.volumes ?? [])].sort((a, b) => a.number - b.number),
)

const missingVolumes = computed(() => sortedVolumes.value.filter((v) => !v.isOwned && !v.isWished))

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

function toggleTranslation() {
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

// ── Modal state ──
const modalVolumeId = ref<string | null>(null)
const modalOpen = computed(() => modalVolumeId.value !== null)
const modalVolume = computed(() => sortedVolumes.value.find((v) => v.id === modalVolumeId.value) ?? null)

function openVolumeModal(ve: VolumeEntry) {
  modalVolumeId.value = ve.id
}
function closeModal() {
  modalVolumeId.value = null
}

// ── Inline title/edition/cover edit ──
const editingTitle = ref(false)
const editingEdition = ref(false)
const editingCover = ref(false)
const editTitleValue = ref('')
const editEditionValue = ref<string | null>(null)
const editCoverValue = ref('')

function startEditTitle() {
  editTitleValue.value = entry.value?.manga.title ?? ''
  editingTitle.value = true
}
function startEditEdition() {
  editEditionValue.value = entry.value?.manga.edition ?? null
  editingEdition.value = true
}
function cancelEditTitle() { editingTitle.value = false }
function cancelEditEdition() { editingEdition.value = false }

const editionLogo = computed(() =>
  FRENCH_EDITIONS.find((e) => e.name === entry.value?.manga.edition)?.logo ?? null,
)

function startEditCover() {
  editCoverValue.value = entry.value?.manga.coverUrl ?? ''
  editingCover.value = true
}
function cancelEditCover() { editingCover.value = false }
function saveCover() {
  updateMangaMutation.mutate({ coverUrl: editCoverValue.value })
  editingCover.value = false
}

// ── Reading status config ──
const STATUS_OPTIONS = [
  {
    value: 'not_started' as ReadingStatus,
    label: 'À lire',
    dot: 'bg-base-content/40',
    activeClass: 'bg-base-content/15 text-base-content border-base-content/20',
    hoverClass: 'hover:bg-base-content/10',
  },
  {
    value: 'in_progress' as ReadingStatus,
    label: 'En cours',
    dot: 'bg-primary',
    activeClass: 'bg-primary text-primary-content border-primary',
    hoverClass: 'hover:bg-primary/10 hover:text-primary hover:border-primary/40',
  },
  {
    value: 'on_hold' as ReadingStatus,
    label: 'Pause',
    dot: 'bg-warning',
    activeClass: 'bg-warning text-warning-content border-warning',
    hoverClass: 'hover:bg-warning/10 hover:text-warning hover:border-warning/40',
  },
  {
    value: 'completed' as ReadingStatus,
    label: 'Terminé',
    dot: 'bg-success',
    activeClass: 'bg-success text-success-content border-success',
    hoverClass: 'hover:bg-success/10 hover:text-success hover:border-success/40',
  },
  {
    value: 'dropped' as ReadingStatus,
    label: 'Abandonné',
    dot: 'bg-error',
    activeClass: 'bg-error text-error-content border-error',
    hoverClass: 'hover:bg-error/10 hover:text-error hover:border-error/40',
  },
] as const

const currentStatusOption = computed(
  () => STATUS_OPTIONS.find((s) => s.value === entry.value?.readingStatus) ?? STATUS_OPTIONS[0],
)

// ── Action bar : progressive-disclosure menus & on-demand price ──
const statusMenuOpen = ref(false)
const moreMenuOpen = ref(false)
const showPrice = ref(false)

function closeActionMenus() {
  statusMenuOpen.value = false
  moreMenuOpen.value = false
}

function pickStatus(status: ReadingStatus) {
  if (entry.value && entry.value.readingStatus !== status) statusMutation.mutate(status)
  statusMenuOpen.value = false
}

// ── Batch price ──
// v-model.number yields the raw string ('') when the input is empty or
// non-numeric, so the model can hold number | string | null. batchPriceValue
// normalises it to a usable number (or null) for guards and the mutation.
const batchPrice = ref<number | string | null>(null)
const batchPriceValue = computed<number | null>(() =>
  typeof batchPrice.value === 'number' && !Number.isNaN(batchPrice.value)
    ? batchPrice.value
    : null,
)

const batchPriceMutation = useMutation({
  mutationFn: (price: number) => batchSetVolumePrice(id, price),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['collection', id] })
    qc.invalidateQueries({ queryKey: ['stats'] })
    batchPrice.value = null
    ui.addToast('Prix appliqué à tous les tomes', 'success')
  },
})

// ── Sync panel state ──
const showSyncPanel = ref(false)
const syncTarget = ref<number | ''>('')
const syncPlaceholder = computed(() => {
  const total = entry.value?.totalVolumes ?? 0
  return `ex: ${total + 5}`
})
const syncMin = computed(() => (entry.value?.totalVolumes ?? 0) + 1)
const isSyncTargetValid = computed(() => {
  if (syncTarget.value === '') return false
  return Number(syncTarget.value) >= syncMin.value
})

// ── Delete confirm ──
const showDeleteConfirm = ref(false)

// ── Batch selection ──
const batchMode = ref(false)
const selectedIds = ref<Set<string>>(new Set())

const selectedVolumes = computed(() =>
  sortedVolumes.value.filter((v) => selectedIds.value.has(v.id)),
)

function toggleBatchMode() {
  batchMode.value = !batchMode.value
  if (!batchMode.value) selectedIds.value = new Set()
}

function toggleSelection(veId: string) {
  const next = new Set(selectedIds.value)
  if (next.has(veId)) next.delete(veId)
  else next.add(veId)
  selectedIds.value = next
}

function handleVolumeClick(ve: VolumeEntry) {
  if (batchMode.value) toggleSelection(ve.id)
  else openVolumeModal(ve)
}

function selectAll() {
  selectedIds.value = new Set(sortedVolumes.value.map((v) => v.id))
}
function selectOwned() {
  selectedIds.value = new Set(sortedVolumes.value.filter((v) => v.isOwned).map((v) => v.id))
}
function selectUnread() {
  selectedIds.value = new Set(sortedVolumes.value.filter((v) => v.isOwned && !v.isRead).map((v) => v.id))
}
function selectAnnounced() {
  selectedIds.value = new Set(sortedVolumes.value.filter((v) => v.isAnnounced && !v.isOwned).map((v) => v.id))
}

// ── Context menu ──
const contextMenu = ref<{ ve: VolumeEntry; x: number; y: number } | null>(null)

function openContextMenu(event: MouseEvent, ve: VolumeEntry) {
  const x = Math.min(event.clientX, window.innerWidth - 216)
  const y = Math.min(event.clientY, window.innerHeight - 200)
  contextMenu.value = { ve, x, y }
}

function closeContextMenu() {
  contextMenu.value = null
}

function openModalFromContext() {
  if (contextMenu.value) {
    openVolumeModal(contextMenu.value.ve)
    closeContextMenu()
  }
}

// ── Mutations ──
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

// Mirrors the backend ToggleVolumeHandler rules so the optimistic cache update
// matches exactly what the server persists (no flicker once the refetch settles).
function applyToggleField(volume: VolumeEntry, field: VolumeToggleField): VolumeEntry {
  const next = { ...volume }
  if (field === 'isOwned') {
    next.isOwned = !volume.isOwned
    if (next.isOwned) {
      next.isWished = false
      next.isAnnounced = false
    }
  } else if (field === 'isRead') {
    next.isRead = !volume.isRead
  } else if (field === 'isWished') {
    next.isWished = !volume.isWished
  } else {
    next.isAnnounced = !volume.isAnnounced
  }
  return next
}

function recomputeDetailCounts(volumes: VolumeEntry[]) {
  return {
    ownedCount:  volumes.filter((v) => v.isOwned).length,
    readCount:   volumes.filter((v) => v.isRead).length,
    wishedCount: volumes.filter((v) => v.isWished && !v.isOwned).length,
    ownedValue:  volumes.reduce((sum, v) => sum + (v.isOwned ? (v.price ?? 0) : 0), 0),
  }
}

const TOGGLE_MUTATION_KEY = ['toggle-volume', id]

const toggleMutation = useMutation({
  mutationKey: TOGGLE_MUTATION_KEY,
  mutationFn: ({ veId, field }: { veId: string; field: VolumeToggleField }) =>
    toggleVolume(id, veId, field),
  // Optimistic update so the volume reacts instantly, even on a slow request —
  // the previous behaviour only refetched on success, so a click could feel dead.
  onMutate: async ({ veId, field }: { veId: string; field: VolumeToggleField }) => {
    const key = ['collection', id]
    await qc.cancelQueries({ queryKey: key })
    const previous = qc.getQueryData<CollectionEntryDetail>(key)
    qc.setQueryData<CollectionEntryDetail>(key, (old) => {
      if (!old) return old
      const volumes = old.volumes.map((v) => (v.id === veId ? applyToggleField(v, field) : v))
      return { ...old, volumes, ...recomputeDetailCounts(volumes) }
    })
    closeContextMenu()
    return { previous, key }
  },
  onError: (_error, _vars, context) => {
    if (context?.previous) qc.setQueryData(context.key, context.previous)
    ui.addToast(t('enrich.statusUpdateError'), 'error')
  },
  onSettled: () => {
    // Only refetch once the last rapid toggle has settled — an in-flight refetch
    // from an earlier toggle would otherwise clobber the newer optimistic state.
    if (qc.isMutating({ mutationKey: TOGGLE_MUTATION_KEY }) === 1) {
      qc.invalidateQueries({ queryKey: ['collection', id] })
      qc.invalidateQueries({ queryKey: ['collection'] })
      qc.invalidateQueries({ queryKey: ['wishlist'] })
      qc.invalidateQueries({ queryKey: ['stats'] })
    }
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


const syncMutation = useMutation({
  mutationFn: () => syncVolumes(id, syncTarget.value !== '' ? Number(syncTarget.value) : undefined),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['collection', id] })
    qc.invalidateQueries({ queryKey: ['collection'] })
    showSyncPanel.value = false
    syncTarget.value = ''
    ui.addToast('Tomes mis à jour', 'success')
  },
})

const updateMangaMutation = useMutation({
  mutationFn: (payload: { title?: string; edition?: string; coverUrl?: string }) => updateManga(entry.value!.manga.id, payload),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['collection', id] })
    qc.invalidateQueries({ queryKey: ['collection'] })
    editingTitle.value = false
    editingEdition.value = false
    ui.addToast('Informations mises à jour', 'success')
  },
  onError: () => ui.addToast('Erreur lors de la mise à jour', 'error'),
})

// Bell wiggles on every click; the animation is keyed so rapid clicks restart it.
const bellRinging = ref(false)
function onFollowClick() {
  bellRinging.value = true
  followMutation.mutate()
}

const followMutation = useMutation({
  mutationFn: () => toggleFollow(id),
  onSuccess: (data) => {
    qc.invalidateQueries({ queryKey: ['collection'] })
    qc.invalidateQueries({ queryKey: ['collection', id] })
    ui.addToast(
      data.notificationsEnabled ? t('notifications.followOn') : t('notifications.followOff'),
      data.notificationsEnabled ? 'success' : 'info',
    )
  },
})

const ratingMutation = useMutation({
  mutationFn: (rating: number) => updateCollectionRating(id, rating),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['collection', id] })
    ui.addToast(t('rating.saved'), 'success')
  },
  onError: () => ui.addToast(t('rating.error'), 'error'),
})

const batchProgress = useCoverBatchProgress()

const autoFillMutation = useMutation({
  mutationFn: () => autoFillCovers(entry.value!.manga.id),
  onSuccess: (response) => {
    const toastId = ui.addProgressToast('Démarrage…', 0)
    batchProgress.start(response, {
      onUpdate: (p) => {
        const active = p.resolved + p.failed
        let line: string
        if (p.lastType === 'batch_started') {
          line = `${p.total} tome(s) à traiter…`
        } else if (p.lastType === 'volume_resolved') {
          line = `Tome ${p.volumeNumber} — trouvée · ${p.resolved} ok · ${p.failed} raté(s) (${active}/${p.total})`
        } else if (p.lastType === 'volume_failed') {
          line = `Tome ${p.volumeNumber} — introuvable · ${p.resolved} ok · ${p.failed} raté(s) (${active}/${p.total})`
        } else {
          line = `${active}/${p.total} traité(s)`
        }
        ui.updateProgressToast(toastId, line, active, p.total)
      },
      onDone: (p) => {
        const parts: string[] = []
        if (p.resolved > 0) parts.push(`${p.resolved} trouvée(s)`)
        if (p.failed > 0) parts.push(`${p.failed} introuvable(s)`)
        if (p.skipped > 0) parts.push(`${p.skipped} ignorée(s)`)
        ui.closeProgressToast(
          toastId,
          parts.length > 0 ? parts.join(' · ') : 'Terminé',
          p.failed > 0 && p.resolved === 0 ? 'error' : 'success',
        )
        qc.invalidateQueries({ queryKey: ['collection', id] })
        qc.invalidateQueries({ queryKey: ['collection'] })
      },
      onError: () => {
        // The SSE stream died or never completed — the covers were still filled
        // server-side, so resync silently instead of leaving the toast hanging.
        ui.closeProgressToast(toastId, 'Couvertures mises à jour', 'info')
        qc.invalidateQueries({ queryKey: ['collection', id] })
        qc.invalidateQueries({ queryKey: ['collection'] })
      },
    })
  },
  onError: () => ui.addToast('Erreur lors de la complétion automatique des couvertures', 'error'),
})

// ── Batch operations ──
const isBatchProcessing = ref(false)

async function batchToggle(field: 'isOwned' | 'isRead' | 'isWished' | 'isAnnounced') {
  if (selectedIds.value.size === 0) return
  const count = selectedIds.value.size
  const ids = [...selectedIds.value]
  isBatchProcessing.value = true
  try {
    await Promise.all(ids.map((veId) => toggleVolume(id, veId, field)))
    await qc.invalidateQueries({ queryKey: ['collection', id] })
    await qc.invalidateQueries({ queryKey: ['collection'] })
    await qc.invalidateQueries({ queryKey: ['wishlist'] })
    await qc.invalidateQueries({ queryKey: ['stats'] })
    selectedIds.value = new Set()
    ui.addToast(`${count} tome${count > 1 ? 's' : ''} mis à jour`, 'success')
  } finally {
    isBatchProcessing.value = false
  }
}

function volumeRingClass(ve: VolumeEntry): string {
  if (batchMode.value && selectedIds.value.has(ve.id)) return 'ring-primary'
  if (ve.isOwned && ve.isRead) return 'ring-info/80'
  if (ve.isOwned) return 'ring-success/70'
  if (ve.isWished) return 'ring-warning/60'
  if (ve.isAnnounced && !ve.isOwned) return 'ring-secondary/60'
  return 'ring-base-300/30'
}

function volumeOpacityClass(ve: VolumeEntry): string {
  if (ve.isOwned) return 'opacity-100'
  if (ve.isWished) return 'opacity-65'
  if (ve.isAnnounced && !ve.isOwned) return 'opacity-60'
  return 'opacity-25 grayscale'
}
</script>

<template>
  <div class="min-h-screen" @click="closeContextMenu(); cancelEditCover(); closeActionMenus()">
    <div v-if="isPending" class="flex justify-center py-20">
      <BaseLoader size="lg" class="text-primary" />
    </div>

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
            Collection
          </RouterLink>
          <div class="flex flex-col sm:flex-row gap-5 sm:gap-6">
            <!-- Cover -->
            <div class="shrink-0 group/cover relative flex justify-center sm:block">
              <div
                class="tooltip tooltip-right w-40 sm:w-28 md:w-36 aspect-[2/3] rounded-2xl overflow-hidden shadow-2xl ring-2 ring-base-content/10 cursor-pointer"
                data-tip="Modifier la couverture (URL)"
                @click.stop="startEditCover"
              >
                <img v-if="entry.manga.coverUrl" :src="coverUrl(entry.manga.coverUrl)!" :alt="entry.manga.title" class="w-full h-full object-cover" />
                <div v-else class="w-full h-full flex items-center justify-center bg-base-200 text-base-content/20">
                  <Book class="h-10 w-10" stroke-width="1.5" />
                </div>
                <!-- Edit overlay -->
                <div class="absolute inset-0 bg-black/50 opacity-0 group-hover/cover:opacity-100 transition-opacity flex items-center justify-center rounded-2xl pointer-events-none">
                  <Pencil class="h-7 w-7 text-white" />
                </div>
              </div>
              <!-- Cover URL edit popover -->
              <div
                v-if="editingCover"
                class="absolute top-full left-0 mt-2 z-30 bg-base-100 border border-base-300 rounded-xl shadow-2xl p-3 w-[min(16rem,calc(100vw-2rem))]"
                @click.stop
              >
                <p class="text-xs text-base-content/50 mb-1.5 font-medium">URL de la couverture</p>
                <input
                  v-model="editCoverValue"
                  type="url"
                  class="input input-bordered input-xs w-full font-mono text-[11px]"
                  placeholder="https://..."
                  autofocus
                  @keydown.enter="saveCover"
                  @keydown.escape="cancelEditCover"
                />
                <div class="flex gap-1.5 mt-2">
                  <button class="btn btn-primary btn-xs flex-1" @click="saveCover">Enregistrer</button>
                  <button class="btn btn-ghost btn-xs" @click="cancelEditCover">Annuler</button>
                </div>
              </div>
            </div>

            <div class="flex-1 min-w-0 space-y-3">
              <div>
                <!-- Inline title edit -->
                <div v-if="editingTitle" class="flex items-center gap-2">
                  <input
                    v-model="editTitleValue"
                    class="input input-bordered input-sm text-2xl md:text-3xl font-extrabold leading-tight w-full"
                    autofocus
                    @keydown.enter="updateMangaMutation.mutate({ title: editTitleValue })"
                    @keydown.escape="cancelEditTitle"
                  />
                  <button class="btn btn-primary btn-sm" @click="updateMangaMutation.mutate({ title: editTitleValue })">✓</button>
                  <button class="btn btn-ghost btn-sm" @click="cancelEditTitle">✕</button>
                </div>
                <div v-else class="group/title flex items-center gap-2">
                  <h1 class="text-2xl md:text-3xl font-extrabold leading-tight">{{ entry.manga.title }}</h1>
                  <div class="tooltip tooltip-right" data-tip="Renommer la série">
                    <button
                      class="btn btn-ghost btn-xs opacity-0 group-hover/title:opacity-60 transition-opacity"
                      @click="startEditTitle"
                    >
                      <Pencil class="h-4 w-4" />
                    </button>
                  </div>
                </div>

                <!-- Inline edition edit -->
                <div class="flex flex-wrap gap-1.5 mt-2">
                  <div v-if="editingEdition" class="flex items-center gap-1.5">
                    <BaseEditionSelector
                      :model-value="editEditionValue"
                      input-class="input input-bordered input-xs font-medium w-40"
                      :autofocus="true"
                      @update:model-value="editEditionValue = $event"
                      @confirm="updateMangaMutation.mutate({ edition: editEditionValue ?? '' })"
                      @cancel="cancelEditEdition"
                    />
                    <button class="btn btn-primary btn-xs" @click="updateMangaMutation.mutate({ edition: editEditionValue ?? '' })">✓</button>
                    <button class="btn btn-ghost btn-xs" @click="cancelEditEdition">✕</button>
                  </div>
                  <div v-else class="group/edition flex items-center gap-1">
                    <div class="tooltip tooltip-bottom" data-tip="Cliquer pour changer l'édition (Kurokawa, Glénat, Pika, …)">
                      <span
                        class="badge cursor-pointer gap-1.5"
                        :class="entry.manga.edition ? 'badge-primary' : 'badge-ghost'"
                        @click="startEditEdition"
                      >
                        <img
                          v-if="editionLogo"
                          :src="editionLogo"
                          :alt="entry.manga.edition!"
                          class="w-3.5 h-3.5 rounded-sm object-contain"
                        />
                        {{ entry.manga.edition ?? 'Édition inconnue' }}
                      </span>
                    </div>
                    <button
                      class="btn btn-ghost btn-xs opacity-0 group-hover/edition:opacity-60 transition-opacity p-0 min-h-0 h-auto"
                      @click="startEditEdition"
                    >
                      <Pencil class="h-3 w-3" />
                    </button>
                  </div>
                  <span class="badge badge-outline">{{ entry.manga.language.toUpperCase() }}</span>
                  <span v-if="entry.manga.genre" class="badge badge-outline capitalize">{{ entry.manga.genre }}</span>

                  <!-- Rating : à droite du genre -->
                  <div class="tooltip tooltip-top ml-1" :data-tip="entry.rating !== null ? 'Cliquer une demi-coeur pour modifier ta note' : 'Donne une note à cette série'">
                    <BaseHeartRating
                      :model-value="entry.rating"
                      @update:model-value="ratingMutation.mutate($event)"
                    />
                  </div>
                </div>
                <p v-if="entry.manga.author" class="text-sm text-base-content/60 mt-1.5 font-medium">{{ entry.manga.author }}</p>
              </div>

              <!-- Stats — progress meters (possédés / lus, + souhaités when relevant) -->
              <div class="flex flex-wrap gap-x-8 gap-y-4">
                <div class="flex-1 min-w-[150px]">
                  <div class="flex items-baseline gap-1.5 mb-2 whitespace-nowrap">
                    <b class="text-success font-extrabold text-lg leading-none">{{ entry.ownedCount }}</b>
                    <span class="text-sm text-base-content/70 font-semibold">possédé{{ entry.ownedCount !== 1 ? 's' : '' }}</span>
                    <span class="text-xs text-base-content/40 font-bold">/ {{ entry.totalVolumes }}</span>
                  </div>
                  <div class="h-1.5 rounded-full bg-base-content/10 overflow-hidden">
                    <div
                      class="h-full rounded-full bg-success/80 transition-[width] duration-500"
                      :style="{ width: (entry.totalVolumes ? Math.round((entry.ownedCount / entry.totalVolumes) * 100) : 0) + '%' }"
                    />
                  </div>
                </div>
                <div class="flex-1 min-w-[150px]">
                  <div class="flex items-baseline gap-1.5 mb-2 whitespace-nowrap">
                    <b class="text-info font-extrabold text-lg leading-none">{{ entry.readCount }}</b>
                    <span class="text-sm text-base-content/70 font-semibold">lu{{ entry.readCount !== 1 ? 's' : '' }}</span>
                    <span class="text-xs text-base-content/40 font-bold">/ {{ entry.totalVolumes }}</span>
                  </div>
                  <div class="h-1.5 rounded-full bg-base-content/10 overflow-hidden">
                    <div
                      class="h-full rounded-full bg-info/80 transition-[width] duration-500"
                      :style="{ width: (entry.totalVolumes ? Math.round((entry.readCount / entry.totalVolumes) * 100) : 0) + '%' }"
                    />
                  </div>
                </div>
                <div v-if="entry.wishedCount > 0" class="flex-1 min-w-[150px]">
                  <div class="flex items-baseline gap-1.5 mb-2 whitespace-nowrap">
                    <b class="text-warning font-extrabold text-lg leading-none">{{ entry.wishedCount }}</b>
                    <span class="text-sm text-base-content/70 font-semibold">souhaité{{ entry.wishedCount !== 1 ? 's' : '' }}</span>
                    <span class="text-xs text-base-content/40 font-bold">/ {{ entry.totalVolumes }}</span>
                  </div>
                  <div class="h-1.5 rounded-full bg-base-content/10 overflow-hidden">
                    <div
                      class="h-full rounded-full bg-warning/80 transition-[width] duration-500"
                      :style="{ width: (entry.totalVolumes ? Math.round((entry.wishedCount / entry.totalVolumes) * 100) : 0) + '%' }"
                    />
                  </div>
                </div>
              </div>

              <!-- Action bar — progressive disclosure: primary action + status menu + follow + overflow -->
              <div class="flex flex-wrap items-center gap-2.5">
                <!-- Primary : add volumes (toggles the sync panel) -->
                <button
                  class="btn btn-sm gap-1.5"
                  :class="showSyncPanel ? 'btn-primary' : 'btn-outline btn-primary'"
                  @click="showSyncPanel = !showSyncPanel"
                >
                  <Plus class="h-4 w-4" stroke-width="2.4" />
                  Ajouter des tomes
                </button>

                <!-- Reading status dropdown -->
                <div class="relative" @click.stop>
                  <button
                    class="inline-flex items-center gap-2 h-9 pl-3 pr-2.5 rounded-full border bg-base-100/60 text-sm font-bold transition-colors"
                    :class="statusMenuOpen ? 'border-primary' : 'border-base-content/15 hover:border-base-content/30'"
                    :disabled="statusMutation.isPending.value"
                    aria-haspopup="menu"
                    :aria-expanded="statusMenuOpen"
                    @click="statusMenuOpen = !statusMenuOpen; moreMenuOpen = false"
                  >
                    <span class="w-2.5 h-2.5 rounded-full shrink-0" :class="currentStatusOption.dot" />
                    <span>{{ currentStatusOption.label }}</span>
                    <BaseLoader v-if="statusMutation.isPending.value" size="xs" />
                    <ChevronDown v-else class="h-3.5 w-3.5 text-base-content/40 transition-transform" :class="statusMenuOpen ? 'rotate-180' : ''" />
                  </button>
                  <Transition name="menu-pop">
                    <div
                      v-if="statusMenuOpen"
                      class="absolute left-0 top-[calc(100%+6px)] z-30 min-w-[230px] rounded-2xl border border-base-300 bg-base-100 shadow-2xl p-1.5"
                      role="menu"
                    >
                      <div class="px-2.5 py-2 text-[10px] font-bold uppercase tracking-widest text-base-content/40 flex items-center justify-between">
                        Statut de lecture
                        <button class="text-base-content/35 hover:text-primary" :aria-label="t('guide.openTooltip')" @click="statusMenuOpen = false; showGuide = true">
                          <HelpCircle class="h-3.5 w-3.5" />
                        </button>
                      </div>
                      <button
                        v-for="s in STATUS_OPTIONS"
                        :key="s.value"
                        class="flex items-center gap-2.5 w-full px-2.5 py-2 rounded-xl text-sm font-semibold text-left transition-colors hover:bg-base-200"
                        :class="entry.readingStatus === s.value ? 'text-base-content' : 'text-base-content/70'"
                        role="menuitem"
                        @click="pickStatus(s.value)"
                      >
                        <span class="w-2.5 h-2.5 rounded-full shrink-0" :class="s.dot" />
                        {{ s.label }}
                        <Check v-if="entry.readingStatus === s.value" class="h-4 w-4 ml-auto text-primary" />
                      </button>
                    </div>
                  </Transition>
                </div>

                <div class="flex-1 min-w-2" />

                <!-- Follow / unfollow (icon button) -->
                <div
                  class="tooltip tooltip-top"
                  :data-tip="entry.notificationsEnabled
                    ? 'Suivi — tu seras notifié des sorties. Cliquer pour arrêter.'
                    : 'Suivre les sorties de nouveaux tomes'"
                >
                  <button
                    class="btn btn-circle btn-sm w-9 h-9"
                    :class="entry.notificationsEnabled ? 'btn-secondary' : 'btn-ghost border border-base-content/15'"
                    :disabled="followMutation.isPending.value"
                    :aria-label="entry.notificationsEnabled ? t('notifications.following') : t('notifications.follow')"
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
                <div class="relative" @click.stop>
                  <div class="tooltip tooltip-top" data-tip="Plus d'options">
                    <button
                      class="btn btn-circle btn-sm w-9 h-9"
                      :class="moreMenuOpen ? 'btn-active border border-base-content/30' : 'btn-ghost border border-base-content/15'"
                      aria-haspopup="menu"
                      :aria-expanded="moreMenuOpen"
                      aria-label="Plus d'options"
                      @click="moreMenuOpen = !moreMenuOpen; statusMenuOpen = false"
                    >
                      <MoreHorizontal class="h-5 w-5" />
                    </button>
                  </div>
                  <Transition name="menu-pop">
                    <div
                      v-if="moreMenuOpen"
                      class="absolute right-0 top-[calc(100%+6px)] z-30 min-w-[250px] rounded-2xl border border-base-300 bg-base-100 shadow-2xl p-1.5"
                      role="menu"
                    >
                      <button
                        v-if="missingVolumes.length > 0"
                        class="flex items-center gap-3 w-full px-2.5 py-2.5 rounded-xl text-sm font-semibold text-left transition-colors hover:bg-base-200"
                        role="menuitem"
                        @click="moreMenuOpen = false; addToWishlistMutation.mutate()"
                      >
                        <Star class="h-[18px] w-[18px] text-base-content/50" />
                        Souhaiter les {{ missingVolumes.length }} manquant{{ missingVolumes.length > 1 ? 's' : '' }}
                      </button>
                      <button
                        class="flex items-center gap-3 w-full px-2.5 py-2.5 rounded-xl text-sm font-semibold text-left transition-colors hover:bg-base-200 disabled:opacity-50"
                        role="menuitem"
                        :disabled="autoFillMutation.isPending.value || (batchProgress.progress.value !== null && !batchProgress.progress.value.done)"
                        @click="moreMenuOpen = false; autoFillMutation.mutate()"
                      >
                        <Sparkles class="h-[18px] w-[18px] text-base-content/50" />
                        Compléter les couvertures
                      </button>
                      <button
                        class="flex items-center gap-3 w-full px-2.5 py-2.5 rounded-xl text-sm font-semibold text-left transition-colors hover:bg-base-200"
                        role="menuitem"
                        @click="moreMenuOpen = false; showPrice = true"
                      >
                        <Tag class="h-[18px] w-[18px] text-base-content/50" />
                        Définir le prix (en lot)
                      </button>
                      <div class="h-px bg-base-200 my-1 mx-2" />
                      <button
                        class="flex items-center gap-3 w-full px-2.5 py-2.5 rounded-xl text-sm font-semibold text-left transition-colors hover:bg-base-200"
                        role="menuitem"
                        @click="moreMenuOpen = false; showGuide = true"
                      >
                        <HelpCircle class="h-[18px] w-[18px] text-base-content/50" />
                        {{ t('guide.openLabel') }}
                      </button>
                      <button
                        class="flex items-center gap-3 w-full px-2.5 py-2.5 rounded-xl text-sm font-semibold text-left transition-colors text-error hover:bg-error/10"
                        role="menuitem"
                        @click="moreMenuOpen = false; showDeleteConfirm = true"
                      >
                        <Trash2 class="h-[17px] w-[17px]" />
                        Retirer la série
                      </button>
                    </div>
                  </Transition>
                </div>
              </div>

              <!-- Sync panel (Ajouter des tomes) -->
              <Transition name="panel-fade">
                <div
                  v-if="showSyncPanel"
                  class="rounded-xl bg-primary/5 border border-primary/20 p-3.5 space-y-2.5"
                  @click.stop
                >
                  <div class="flex items-start gap-2 text-xs text-base-content/65 leading-relaxed">
                    <Info class="h-3.5 w-3.5 mt-0.5 shrink-0 text-primary" />
                    <p>
                      Cette série compte actuellement
                      <strong class="text-base-content">{{ entry.totalVolumes }} tome{{ entry.totalVolumes > 1 ? 's' : '' }}</strong>.
                      Saisis le numéro du <strong>dernier</strong> tome à créer : les tomes manquants seront ajoutés sans statut (à compléter plus tard).
                    </p>
                  </div>
                  <div class="flex items-center gap-2 flex-wrap">
                    <label for="sync-target" class="text-sm font-medium text-base-content/80 shrink-0">
                      Aller jusqu'au tome
                    </label>
                    <input
                      id="sync-target"
                      v-model="syncTarget"
                      type="number"
                      :min="syncMin"
                      max="9999"
                      class="input input-sm input-bordered w-24 tabular-nums"
                      :placeholder="syncPlaceholder"
                      @keydown.enter="isSyncTargetValid && syncMutation.mutate()"
                    />
                    <div
                      class="tooltip tooltip-top"
                      :data-tip="isSyncTargetValid
                        ? `Créer les tomes ${syncMin} à ${syncTarget}`
                        : `Le numéro doit être supérieur à ${entry.totalVolumes}`"
                    >
                      <button
                        class="btn btn-primary btn-sm gap-1.5"
                        :class="{ loading: syncMutation.isPending.value }"
                        :disabled="!isSyncTargetValid || syncMutation.isPending.value"
                        @click="syncMutation.mutate()"
                      >
                        <Plus v-if="!syncMutation.isPending.value" class="h-3.5 w-3.5" />
                        Créer les tomes
                      </button>
                    </div>
                    <button class="btn btn-ghost btn-sm" @click="showSyncPanel = false">
                      Annuler
                    </button>
                  </div>
                </div>
              </Transition>

              <!-- Batch price : revealed on demand via the overflow menu -->
              <Transition name="panel-fade">
                <div v-if="showPrice" class="rounded-xl bg-base-200/40 border border-base-content/8 p-3 flex flex-wrap items-center gap-3">
                  <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 rounded-lg bg-secondary/15 text-secondary flex items-center justify-center shrink-0">
                      <Tag class="h-4 w-4" />
                    </div>
                    <div class="min-w-0">
                      <div class="text-sm font-semibold leading-tight flex items-center gap-1.5">
                        Prix unitaire (en lot)
                        <div class="tooltip tooltip-top" data-tip="Définit le même prix pour tous les tomes de la série en une seule action. Tu peux toujours ajuster le prix d'un tome individuellement.">
                          <HelpCircle class="h-3.5 w-3.5 text-base-content/35 cursor-help" />
                        </div>
                      </div>
                      <div class="text-[11px] text-base-content/50 leading-tight mt-0.5">
                        S'applique à tous les tomes ({{ entry.totalVolumes }})
                      </div>
                    </div>
                  </div>
                  <div class="flex items-center gap-2 ml-auto">
                    <div class="relative">
                      <input
                        v-model.number="batchPrice"
                        type="number"
                        step="0.01"
                        min="0"
                        class="input input-sm input-bordered w-28 pr-7 tabular-nums font-mono [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
                        placeholder="0.00"
                        @keydown.enter="batchPriceValue !== null && batchPriceMutation.mutate(batchPriceValue)"
                      />
                      <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-base-content/40 pointer-events-none font-medium">€</span>
                    </div>
                    <div
                      class="tooltip tooltip-top tooltip-secondary"
                      :data-tip="batchPriceValue === null
                        ? 'Saisis un prix pour activer'
                        : `Appliquer ${batchPriceValue.toFixed(2)} € à chaque tome`"
                    >
                      <button
                        class="btn btn-secondary btn-sm gap-1.5"
                        :class="{ loading: batchPriceMutation.isPending.value }"
                        :disabled="batchPriceValue === null || batchPriceMutation.isPending.value"
                        @click="batchPriceValue !== null && batchPriceMutation.mutate(batchPriceValue)"
                      >
                        <Check v-if="!batchPriceMutation.isPending.value" class="h-3.5 w-3.5" stroke-width="3" />
                        Appliquer à tous
                      </button>
                    </div>
                    <button class="btn btn-ghost btn-sm btn-circle" aria-label="Fermer" @click="showPrice = false">
                      <X class="h-4 w-4" />
                    </button>
                  </div>
                </div>
              </Transition>
            </div>
          </div>

          <div v-if="entry.manga.summary" class="mt-4 max-w-2xl">
            <p class="text-sm text-base-content/60 line-clamp-3">
              {{ displayedSummary }}
            </p>
            <button
              class="btn btn-ghost btn-xs gap-1 mt-1 px-1 text-base-content/50 hover:text-base-content"
              :class="{ loading: translateMutation.isPending.value }"
              :disabled="translateMutation.isPending.value"
              @click="toggleTranslation"
            >
              <Languages v-if="!translateMutation.isPending.value" class="h-3 w-3" />
              {{ showTranslation ? t('manga.showOriginal') : t('manga.translate') }}
            </button>
          </div>
        </div>
      </div>

      <!-- Volume grid -->
      <div class="max-w-5xl mx-auto px-4 sm:px-6 py-6">
        <!-- Grid header -->
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-xs font-semibold uppercase tracking-widest text-base-content/40">
            {{ t('collection.volumes') }} — {{ entry.ownedCount }}/{{ entry.totalVolumes }}
          </h2>
          <div class="flex items-center gap-3">
            <div class="hidden sm:flex gap-3 text-xs text-base-content/40">
              <span class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-sm bg-info ring-1 ring-info inline-block" />Lu
              </span>
              <span class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-sm bg-success ring-1 ring-success inline-block" />Possédé
              </span>
              <span class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-sm bg-warning ring-1 ring-warning inline-block" />Souhaité
              </span>
              <span class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-sm bg-secondary ring-1 ring-secondary inline-block" />Annoncé
              </span>
            </div>
            <button
              class="btn btn-xs gap-1"
              :class="batchMode ? 'btn-primary' : 'btn-ghost'"
              @click="toggleBatchMode"
            >
              <CheckSquare class="w-3 h-3" />
              {{ batchMode ? 'Terminer' : 'Sélectionner' }}
            </button>
          </div>
        </div>

        <!-- Batch quick-select row -->
        <div v-if="batchMode" class="flex flex-wrap gap-1.5 mb-3">
          <span class="text-xs text-base-content/40 self-center mr-1">Sélectionner :</span>
          <button class="btn btn-xs btn-ghost" @click="selectAll">Tout</button>
          <button class="btn btn-xs btn-ghost" @click="selectOwned">Possédés</button>
          <button class="btn btn-xs btn-ghost" @click="selectUnread">Non lus</button>
          <button class="btn btn-xs btn-ghost" @click="selectAnnounced">Annoncés</button>
          <button class="btn btn-xs btn-ghost text-base-content/30" @click="selectedIds = new Set()">Vider</button>
        </div>

        <div v-if="sortedVolumes.length" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-7 xl:grid-cols-8 gap-3">
          <div
            v-for="ve in sortedVolumes"
            :key="ve.id"
            class="group relative cursor-pointer select-none"
            @click="handleVolumeClick(ve)"
            @contextmenu.prevent="openContextMenu($event, ve)"
          >
            <!-- Selection indicator (batch mode) -->
            <div
              v-if="batchMode"
              class="absolute top-1 left-1 z-20 w-4 h-4 rounded-full border-2 flex items-center justify-center transition-all duration-150 pointer-events-none shadow-sm"
              :class="selectedIds.has(ve.id)
                ? 'bg-primary border-primary text-primary-content'
                : 'bg-base-100/80 border-base-content/30'"
            >
              <svg v-if="selectedIds.has(ve.id)" class="w-2.5 h-2.5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
              </svg>
            </div>

            <!-- Cover card -->
            <div
              class="aspect-[2/3] rounded-xl overflow-hidden ring-2 transition-all duration-200 relative shadow-sm"
              :class="[
                volumeRingClass(ve),
                volumeOpacityClass(ve),
                batchMode && selectedIds.has(ve.id)
                  ? 'ring-offset-2 ring-offset-base-100 scale-105 shadow-lg shadow-primary/20'
                  : 'group-hover:scale-105 group-hover:shadow-lg group-hover:z-10',
              ]"
            >
              <BaseLazyImage
                v-if="ve.coverUrl"
                :src="coverUrl(ve.coverUrl)!"
                :alt="`Tome ${ve.number}`"
              >
                <template #fallback>
                  <div class="w-full h-full flex items-center justify-center bg-base-200">
                    <span
                      class="font-bold text-xl"
                      :class="ve.isOwned ? 'text-base-content/50' : ve.isWished ? 'text-warning/60' : ve.isAnnounced ? 'text-secondary/50' : 'text-base-content/15'"
                    >
                      {{ ve.number }}
                    </span>
                  </div>
                </template>
              </BaseLazyImage>
              <div
                v-else
                class="w-full h-full flex items-center justify-center bg-base-200"
              >
                <span
                  class="font-bold text-xl"
                  :class="ve.isOwned ? 'text-base-content/50' : ve.isWished ? 'text-warning/60' : ve.isAnnounced ? 'text-secondary/50' : 'text-base-content/15'"
                >
                  {{ ve.number }}
                </span>
              </div>

              <!-- Read indicator band at bottom -->
              <div
                v-if="ve.isRead"
                class="absolute bottom-0 left-0 right-0 bg-info/90 backdrop-blur-sm text-info-content text-[7px] font-black tracking-widest text-center py-[3px] leading-none uppercase"
              >
                Lu
              </div>

              <!-- Hover overlay (non-batch mode) -->
              <div
                v-if="!batchMode"
                class="absolute inset-0 bg-primary/20 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center"
              >
                <div class="bg-white/80 rounded-full p-1 shadow">
                  <Eye class="h-4 w-4 text-primary" />
                </div>
              </div>
            </div>

            <!-- Announced badge (top-left) -->
            <div
              v-if="ve.isAnnounced && !ve.isOwned"
              class="absolute top-0.5 left-0.5 w-3.5 h-3.5 rounded-full bg-secondary flex items-center justify-center z-10 pointer-events-none shadow-sm"
            >
              <Megaphone class="w-2 h-2 text-secondary-content" />
            </div>

            <!-- Wished badge (top-right) -->
            <div
              v-if="ve.isWished && !ve.isOwned"
              class="absolute top-0.5 right-0.5 w-3.5 h-3.5 rounded-full bg-warning flex items-center justify-center z-10 pointer-events-none shadow-sm"
            >
              <Star class="w-2 h-2 text-warning-content" fill="currentColor" stroke-width="0" />
            </div>

            <!-- Number label -->
            <div
              class="text-center text-[10px] sm:text-[9px] mt-0.5 tabular-nums font-semibold leading-tight"
              :class="ve.isOwned ? 'text-base-content/60' : ve.isWished ? 'text-warning/60' : ve.isAnnounced ? 'text-secondary/60' : 'text-base-content/20'"
            >
              T{{ ve.number }}
            </div>
          </div>
        </div>

        <p v-else class="text-sm text-base-content/40 italic py-4">
          Aucun tome enregistré. Utilisez "Ajouter tomes" pour en créer.
        </p>

        <p v-if="!batchMode" class="mt-5 text-xs text-base-content/30 hidden sm:block">
          Clic gauche pour gérer · Clic droit pour actions rapides · Sélectionner pour modifications en lot
        </p>
        <p v-if="!batchMode" class="mt-5 text-xs text-base-content/30 sm:hidden">
          Appuyez sur un tome pour le gérer
        </p>
      </div>

      <!-- Enrich Volume Modal -->
      <EnrichVolumeModal
        :open="modalOpen"
        :collection-entry-id="id"
        :manga-id="entry.manga.id"
        :manga-title="entry.manga.title"
        :manga-edition="entry.manga.edition"
        :volume="modalVolume"
        @close="closeModal"
      />

      <!-- Guide / help modal -->
      <CollectionGuideModal :open="showGuide" @close="showGuide = false" />
    </template>
  </div>

  <!-- ── Context Menu ── -->
  <Teleport to="body">
    <div v-if="contextMenu" class="fixed inset-0 z-[90]" @click="closeContextMenu">
      <div
        class="absolute bg-base-100 rounded-xl shadow-2xl border border-base-300 overflow-hidden w-48 py-1"
        :style="{ top: `${contextMenu.y}px`, left: `${contextMenu.x}px` }"
        @click.stop
      >
        <div class="px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-base-content/40 border-b border-base-200">
          Tome {{ contextMenu.ve.number }}
        </div>
        <ul class="menu menu-xs p-1 gap-0.5">
          <!-- Annoncé (only when not owned) -->
          <li v-if="!contextMenu.ve.isOwned">
            <a
              class="gap-2 text-sm"
              :class="[
                { 'pointer-events-none opacity-50': toggleMutation.isPending.value },
                contextMenu.ve.isAnnounced ? 'text-base-content font-semibold' : '',
              ]"
              @click="toggleMutation.mutate({ veId: contextMenu.ve.id, field: 'isAnnounced' })"
            >
              <Megaphone class="h-4 w-4" :class="contextMenu.ve.isAnnounced ? 'text-base-content' : 'text-base-content/50'" />
              Annoncé
              <span v-if="contextMenu.ve.isAnnounced" class="ml-auto badge badge-neutral badge-xs">●</span>
            </a>
          </li>
          <!-- Possédé (toggle in both directions) -->
          <li>
            <a
              class="gap-2 text-sm"
              :class="[
                { 'pointer-events-none opacity-50': toggleMutation.isPending.value },
                contextMenu.ve.isOwned ? 'text-success font-semibold' : '',
              ]"
              @click="toggleMutation.mutate({ veId: contextMenu.ve.id, field: 'isOwned' })"
            >
              <Package class="h-4 w-4" :class="contextMenu.ve.isOwned ? 'text-success' : 'text-base-content/50'" />
              Possédé
              <span v-if="contextMenu.ve.isOwned" class="ml-auto badge badge-success badge-xs">●</span>
            </a>
          </li>
          <!-- Lu (only when owned) -->
          <li v-if="contextMenu.ve.isOwned">
            <a
              class="gap-2 text-sm"
              :class="[
                { 'pointer-events-none opacity-50': toggleMutation.isPending.value },
                contextMenu.ve.isRead ? 'text-info font-semibold' : '',
              ]"
              @click="toggleMutation.mutate({ veId: contextMenu.ve.id, field: 'isRead' })"
            >
              <BookOpen class="h-4 w-4" :class="contextMenu.ve.isRead ? 'text-info' : 'text-base-content/50'" />
              Lu
              <span v-if="contextMenu.ve.isRead" class="ml-auto badge badge-info badge-xs">●</span>
            </a>
          </li>
          <!-- Wishlist (only when not owned) -->
          <li v-if="!contextMenu.ve.isOwned">
            <a
              class="gap-2 text-sm"
              :class="[
                { 'pointer-events-none opacity-50': toggleMutation.isPending.value },
                contextMenu.ve.isWished ? 'text-warning font-semibold' : '',
              ]"
              @click="toggleMutation.mutate({ veId: contextMenu.ve.id, field: 'isWished' })"
            >
              <Star
                class="h-4 w-4"
                :class="contextMenu.ve.isWished ? 'text-warning' : 'text-base-content/50'"
                :fill="contextMenu.ve.isWished ? 'currentColor' : 'none'"
              />
              Wishlist
              <span v-if="contextMenu.ve.isWished" class="ml-auto badge badge-warning badge-xs">●</span>
            </a>
          </li>
          <div class="h-px bg-base-200 my-0.5 mx-2" />
          <li>
            <a class="gap-2 text-sm" @click="openModalFromContext">
              <Info class="h-4 w-4" />
              Détails
            </a>
          </li>
        </ul>
      </div>
    </div>
  </Teleport>

  <!-- ── Batch Action Bar ── -->
  <Teleport to="body">
    <Transition name="slide-up">
      <div
        v-if="batchMode && selectedIds.size > 0"
        class="fixed bottom-16 lg:bottom-0 left-0 right-0 z-50 bg-base-100/95 backdrop-blur-sm border-t-2 border-primary/40 shadow-2xl"
      >
        <div class="max-w-5xl mx-auto px-4 py-3 flex items-center gap-3 flex-wrap">
          <span class="badge badge-primary badge-lg shrink-0">
            {{ selectedIds.size }} tome{{ selectedIds.size > 1 ? 's' : '' }}
          </span>
          <div class="flex flex-wrap gap-2 flex-1 min-w-0">
            <button
              v-if="selectedVolumes.some((v) => v.isOwned && !v.isRead)"
              class="btn btn-info btn-sm gap-1.5"
              :disabled="isBatchProcessing"
              @click="batchToggle('isRead')"
            >
              <BookOpen class="h-4 w-4" />
              Marquer lus
            </button>
            <button
              v-if="selectedVolumes.some((v) => v.isOwned && v.isRead)"
              class="btn btn-info btn-sm btn-outline gap-1.5"
              :disabled="isBatchProcessing"
              @click="batchToggle('isRead')"
            >
              <BookOpen class="h-4 w-4" />
              Marquer non lus
            </button>
            <button
              v-if="selectedVolumes.some((v) => !v.isOwned)"
              class="btn btn-success btn-sm gap-1.5"
              :disabled="isBatchProcessing"
              @click="batchToggle('isOwned')"
            >
              <Package class="h-4 w-4" />
              Marquer possédés
            </button>
            <button
              v-if="selectedVolumes.some((v) => !v.isOwned && !v.isWished)"
              class="btn btn-warning btn-sm btn-outline gap-1.5"
              :disabled="isBatchProcessing"
              @click="batchToggle('isWished')"
            >
              <Star class="h-4 w-4" />
              Wishlist
            </button>
            <button
              v-if="selectedVolumes.some((v) => !v.isOwned && !v.isAnnounced)"
              class="btn btn-secondary btn-sm btn-outline gap-1.5"
              :disabled="isBatchProcessing"
              @click="batchToggle('isAnnounced')"
            >
              <Bell class="h-4 w-4" />
              Marquer annoncés
            </button>
            <button
              v-if="selectedVolumes.some((v) => !v.isOwned && v.isAnnounced)"
              class="btn btn-secondary btn-sm gap-1.5"
              :disabled="isBatchProcessing"
              @click="batchToggle('isAnnounced')"
            >
              <BellOff class="h-4 w-4" />
              Retirer annoncés
            </button>
          </div>
          <button class="btn btn-ghost btn-sm shrink-0" @click="selectedIds = new Set()">
            Vider
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>

  <!-- ── Delete Confirm Dialog ── -->
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="showDeleteConfirm" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="showDeleteConfirm = false">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" />
        <div class="relative z-10 bg-base-100 rounded-2xl shadow-2xl p-6 max-w-sm w-full">
          <div class="flex items-start gap-3 mb-4">
            <div class="w-10 h-10 rounded-full bg-error/15 flex items-center justify-center shrink-0 text-error">
              <Trash2 class="h-5 w-5" />
            </div>
            <div>
              <h3 class="font-bold text-lg leading-tight">Retirer de la collection ?</h3>
              <p class="text-sm text-base-content/60 mt-1 leading-relaxed">
                <strong class="text-base-content">{{ entry?.manga.title }}</strong> et tous ses tomes seront retirés de votre bibliothèque. Cette action est irréversible.
              </p>
            </div>
          </div>
          <div class="flex gap-3 justify-end">
            <button class="btn btn-ghost" @click="showDeleteConfirm = false">Annuler</button>
            <button
              class="btn btn-error gap-2"
              :class="{ loading: removeMutation.isPending.value }"
              @click="removeMutation.mutate()"
            >
              Supprimer
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.slide-up-enter-active,
.slide-up-leave-active {
  transition: transform 0.25s ease, opacity 0.2s ease;
}
.slide-up-enter-from,
.slide-up-leave-to {
  transform: translateY(100%);
  opacity: 0;
}

.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.2s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
.modal-fade-enter-active .relative,
.modal-fade-leave-active .relative {
  transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.modal-fade-enter-from .relative {
  transform: translateY(20px) scale(0.97);
}

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

.menu-pop-enter-active,
.menu-pop-leave-active {
  transition: opacity 0.14s ease, transform 0.16s cubic-bezier(0.22, 0.61, 0.36, 1);
  transform-origin: top;
}
.menu-pop-enter-from,
.menu-pop-leave-to {
  opacity: 0;
  transform: translateY(-6px) scale(0.97);
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
