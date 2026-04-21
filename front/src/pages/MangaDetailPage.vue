<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import {
  getCollectionEntry,
  removeFromCollection,
  updateReadingStatus,
  toggleVolume,
  addRemainingToWishlist,
  purchaseVolume,
  syncVolumes,
  batchSetVolumePrice,
  updateCollectionRating,
} from '@/api/collection'
import { updateManga } from '@/api/manga'
import { useUiStore } from '@/stores/useUiStore'
import { useI18n } from 'vue-i18n'
import EnrichVolumeModal from '@/components/organisms/EnrichVolumeModal.vue'
import BaseHeartRating from '@/components/atoms/BaseHeartRating.vue'
import type { ReadingStatus, VolumeEntry } from '@/types'
import { coverUrl } from '@/utils/coverUrl'

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

watch(entry, (e) => {
  if (e) document.title = `${e.manga.title} — Ziggy`
}, { immediate: true })

const sortedVolumes = computed<VolumeEntry[]>(() =>
  [...(entry.value?.volumes ?? [])].sort((a, b) => a.number - b.number),
)

const missingVolumes = computed(() => sortedVolumes.value.filter((v) => !v.isOwned && !v.isWished))

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
const editEditionValue = ref('')
const editCoverValue = ref('')

function startEditTitle() {
  editTitleValue.value = entry.value?.manga.title ?? ''
  editingTitle.value = true
}
function startEditEdition() {
  editEditionValue.value = entry.value?.manga.edition ?? ''
  editingEdition.value = true
}
function cancelEditTitle() { editingTitle.value = false }
function cancelEditEdition() { editingEdition.value = false }

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
    activeClass: 'bg-base-content/15 text-base-content border-base-content/20',
    hoverClass: 'hover:bg-base-content/10',
  },
  {
    value: 'in_progress' as ReadingStatus,
    label: 'En cours',
    activeClass: 'bg-primary text-primary-content border-primary',
    hoverClass: 'hover:bg-primary/10 hover:text-primary hover:border-primary/40',
  },
  {
    value: 'on_hold' as ReadingStatus,
    label: 'Pause',
    activeClass: 'bg-warning text-warning-content border-warning',
    hoverClass: 'hover:bg-warning/10 hover:text-warning hover:border-warning/40',
  },
  {
    value: 'completed' as ReadingStatus,
    label: 'Terminé',
    activeClass: 'bg-success text-success-content border-success',
    hoverClass: 'hover:bg-success/10 hover:text-success hover:border-success/40',
  },
  {
    value: 'dropped' as ReadingStatus,
    label: 'Abandonné',
    activeClass: 'bg-error text-error-content border-error',
    hoverClass: 'hover:bg-error/10 hover:text-error hover:border-error/40',
  },
] as const

// ── Batch price ──
const batchPrice = ref<number | null>(null)

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

const toggleMutation = useMutation({
  mutationFn: ({ veId, field }: { veId: string; field: 'isOwned' | 'isRead' | 'isWished' }) =>
    toggleVolume(id, veId, field),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['collection', id] })
    qc.invalidateQueries({ queryKey: ['collection'] })
    qc.invalidateQueries({ queryKey: ['wishlist'] })
    qc.invalidateQueries({ queryKey: ['stats'] })
    closeContextMenu()
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
    closeContextMenu()
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

const ratingMutation = useMutation({
  mutationFn: (rating: number) => updateCollectionRating(id, rating),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['collection', id] })
    ui.addToast(t('rating.saved'), 'success')
  },
  onError: () => ui.addToast(t('rating.error'), 'error'),
})

// ── Batch operations ──
const isBatchProcessing = ref(false)

async function batchToggle(field: 'isOwned' | 'isRead' | 'isWished') {
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
  if (ve.isAnnounced) return 'ring-base-300/20 ring-dashed'
  return 'ring-base-300/30'
}

function volumeOpacityClass(ve: VolumeEntry): string {
  if (ve.isOwned) return 'opacity-100'
  if (ve.isWished) return 'opacity-65'
  if (ve.isAnnounced) return 'opacity-40 grayscale'
  return 'opacity-25 grayscale'
}
</script>

<template>
  <div class="min-h-screen" @click="closeContextMenu(); cancelEditCover()">
    <div v-if="isPending" class="flex justify-center py-20">
      <span class="loading loading-spinner loading-lg" />
    </div>

    <template v-else-if="entry">
      <!-- Hero header with blurred cover bg -->
      <div class="relative overflow-hidden">
        <div
          v-if="entry.manga.coverUrl"
          class="absolute inset-0 bg-cover bg-center blur-3xl scale-110 opacity-20 pointer-events-none"
          :style="{ backgroundImage: `url(${coverUrl(entry.manga.coverUrl)})` }"
        />
        <div class="absolute inset-0 bg-gradient-to-b from-base-100/60 to-base-100 pointer-events-none" />

        <div class="relative max-w-5xl mx-auto px-4 sm:px-6 pt-6 sm:pt-8 pb-6">
          <RouterLink
            :to="{ name: 'collection' }"
            class="md:hidden inline-flex items-center gap-1.5 text-sm text-base-content/50 hover:text-base-content mb-4 transition-colors"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            Collection
          </RouterLink>
          <div class="flex flex-col sm:flex-row gap-5 sm:gap-6">
            <!-- Cover -->
            <div class="shrink-0 group/cover relative flex justify-center sm:block">
              <div
                class="w-40 sm:w-28 md:w-36 aspect-[2/3] rounded-2xl overflow-hidden shadow-2xl ring-2 ring-base-content/10 cursor-pointer"
                @click.stop="startEditCover"
              >
                <img v-if="entry.manga.coverUrl" :src="coverUrl(entry.manga.coverUrl)!" :alt="entry.manga.title" class="w-full h-full object-cover" />
                <div v-else class="w-full h-full flex items-center justify-center bg-base-200 text-base-content/20">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                  </svg>
                </div>
                <!-- Edit overlay -->
                <div class="absolute inset-0 bg-black/50 opacity-0 group-hover/cover:opacity-100 transition-opacity flex items-center justify-center rounded-2xl">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                  </svg>
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
                  <button
                    class="btn btn-ghost btn-xs opacity-0 group-hover/title:opacity-60 transition-opacity"
                    title="Renommer"
                    @click="startEditTitle"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </button>
                </div>

                <!-- Inline edition edit -->
                <div class="flex flex-wrap gap-1.5 mt-2">
                  <div v-if="editingEdition" class="flex items-center gap-1.5">
                    <input
                      v-model="editEditionValue"
                      class="input input-bordered input-xs font-medium w-40"
                      autofocus
                      @keydown.enter="updateMangaMutation.mutate({ edition: editEditionValue })"
                      @keydown.escape="cancelEditEdition"
                    />
                    <button class="btn btn-primary btn-xs" @click="updateMangaMutation.mutate({ edition: editEditionValue })">✓</button>
                    <button class="btn btn-ghost btn-xs" @click="cancelEditEdition">✕</button>
                  </div>
                  <div v-else class="group/edition flex items-center gap-1">
                    <span class="badge badge-primary cursor-pointer" @click="startEditEdition">{{ entry.manga.edition }}</span>
                    <button
                      class="btn btn-ghost btn-xs opacity-0 group-hover/edition:opacity-60 transition-opacity p-0 min-h-0 h-auto"
                      title="Modifier l'édition"
                      @click="startEditEdition"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                      </svg>
                    </button>
                  </div>
                  <span class="badge badge-outline">{{ entry.manga.language.toUpperCase() }}</span>
                  <span v-if="entry.manga.genre" class="badge badge-outline capitalize">{{ entry.manga.genre }}</span>

                  <!-- Rating : à droite du genre -->
                  <BaseHeartRating
                    :model-value="entry.rating"
                    class="ml-1"
                    @update:model-value="ratingMutation.mutate($event)"
                  />
                </div>
                <p v-if="entry.manga.author" class="text-sm text-base-content/60 mt-1.5 font-medium">{{ entry.manga.author }}</p>
              </div>

              <!-- Stats -->
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

              <!-- Status pill selector -->
              <div class="flex gap-1.5 overflow-x-auto pb-0.5 -mx-1 px-1 sm:flex-wrap sm:overflow-visible">
                <button
                  v-for="s in STATUS_OPTIONS"
                  :key="s.value"
                  class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold border transition-all duration-150 cursor-pointer"
                  :class="entry.readingStatus === s.value
                    ? s.activeClass
                    : ['border-base-content/10 text-base-content/35 bg-transparent', s.hoverClass]"
                  :disabled="statusMutation.isPending.value"
                  @click="entry.readingStatus !== s.value && statusMutation.mutate(s.value)"
                >
                  <span
                    v-if="statusMutation.isPending.value && entry.readingStatus === s.value"
                    class="loading loading-spinner w-2.5 h-2.5"
                  />
                  {{ s.label }}
                </button>
              </div>

              <!-- Actions row -->
              <div class="flex flex-wrap items-center gap-2">

                <button
                  v-if="missingVolumes.length > 0"
                  class="btn btn-warning btn-sm gap-1.5"
                  :class="{ loading: addToWishlistMutation.isPending.value }"
                  @click="addToWishlistMutation.mutate()"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                  </svg>
                  Souhaiter les {{ missingVolumes.length }} manquants
                </button>

                <button
                  class="btn btn-ghost btn-sm gap-1"
                  @click="showSyncPanel = !showSyncPanel"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                  </svg>
                  Ajouter tomes
                </button>

                <button
                  class="btn btn-ghost btn-sm text-error"
                  @click.stop="showDeleteConfirm = true"
                >
                  {{ t('common.remove') }}
                </button>
              </div>

              <!-- Batch price pill -->
              <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-base-200/60 border border-dashed border-base-content/15 text-xs self-start">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-base-content/35 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                </svg>
                <span class="text-base-content/40 font-medium tracking-wide">€ / tome</span>
                <input
                  v-model.number="batchPrice"
                  type="number"
                  step="0.01"
                  min="0"
                  class="w-14 bg-transparent outline-none text-center tabular-nums font-mono text-base-content/70 placeholder-base-content/20 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
                  placeholder="0.00"
                />
                <button
                  class="w-5 h-5 rounded-full flex items-center justify-center transition-all"
                  :class="batchPrice !== null && !batchPriceMutation.isPending.value
                    ? 'bg-secondary/20 text-secondary hover:bg-secondary/30 cursor-pointer'
                    : 'text-base-content/20 cursor-default'"
                  :disabled="batchPrice === null || batchPriceMutation.isPending.value"
                  @click="batchPrice !== null && batchPriceMutation.mutate(batchPrice)"
                >
                  <svg v-if="!batchPriceMutation.isPending.value" xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                  </svg>
                  <span v-else class="loading loading-spinner loading-[8px]" />
                </button>
              </div>

              <!-- Sync panel (inline) -->
              <div v-if="showSyncPanel" class="flex items-center gap-2 p-3 rounded-xl bg-base-200 text-sm">
                <span class="text-base-content/60 shrink-0">Ajouter jusqu'au tome</span>
                <input
                  v-model="syncTarget"
                  type="number"
                  min="1"
                  max="9999"
                  class="input input-xs input-bordered w-20"
                  placeholder="ex: 30"
                />
                <button
                  class="btn btn-primary btn-xs"
                  :class="{ loading: syncMutation.isPending.value }"
                  :disabled="!syncTarget"
                  @click="syncMutation.mutate()"
                >
                  Ajouter
                </button>
                <button class="btn btn-ghost btn-xs" @click="showSyncPanel = false">Annuler</button>
              </div>
            </div>
          </div>

          <p v-if="entry.manga.summary" class="mt-4 text-sm text-base-content/60 line-clamp-3 max-w-2xl">
            {{ entry.manga.summary }}
          </p>
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
            </div>
            <button
              class="btn btn-xs gap-1"
              :class="batchMode ? 'btn-primary' : 'btn-ghost'"
              @click="toggleBatchMode"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
              </svg>
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
          <button class="btn btn-xs btn-ghost text-base-content/30" @click="selectedIds = new Set()">Vider</button>
        </div>

        <div v-if="sortedVolumes.length" class="grid grid-cols-3 sm:grid-cols-5 md:grid-cols-7 lg:grid-cols-9 xl:grid-cols-11 gap-3">
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
              <img
                v-if="ve.coverUrl"
                :src="coverUrl(ve.coverUrl)!"
                :alt="`Tome ${ve.number}`"
                class="w-full h-full object-cover"
                loading="lazy"
              />
              <div
                v-else
                class="w-full h-full flex items-center justify-center"
                :class="ve.isAnnounced ? 'bg-base-300/30' : 'bg-base-200'"
              >
                <span
                  class="font-bold text-xl"
                  :class="ve.isAnnounced ? 'text-base-content/40' : ve.isOwned ? 'text-base-content/50' : ve.isWished ? 'text-warning/60' : 'text-base-content/15'"
                >
                  {{ ve.isAnnounced ? '?' : ve.number }}
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
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                </div>
              </div>
            </div>

            <!-- Wished badge (top-right) -->
            <div
              v-if="ve.isWished && !ve.isOwned"
              class="absolute top-0.5 right-0.5 w-3.5 h-3.5 rounded-full bg-warning flex items-center justify-center z-10 pointer-events-none shadow-sm"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="w-2 h-2 text-warning-content" viewBox="0 0 24 24" fill="currentColor">
                <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
              </svg>
            </div>

            <!-- Announced badge (top-left) -->
            <div
              v-if="ve.isAnnounced"
              class="absolute top-0.5 left-0.5 px-1.5 py-0.5 rounded bg-base-300/80 text-base-content/60 text-[9px] font-semibold tracking-wide z-10 pointer-events-none shadow-sm"
            >
              À paraître
            </div>

            <!-- Number label -->
            <div
              class="text-center text-[10px] sm:text-[9px] mt-0.5 tabular-nums font-semibold leading-tight"
              :class="ve.isOwned ? 'text-base-content/60' : ve.isWished ? 'text-warning/60' : 'text-base-content/20'"
            >
              T{{ ve.number }}
            </div>
          </div>
        </div>

        <!-- Legend -->
        <div v-if="sortedVolumes.length" class="mt-6 pt-4 border-t border-base-300">
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs text-base-content/60">
            <div class="flex items-center gap-2">
              <div class="w-6 h-8 rounded ring-2 ring-info/80 bg-base-100" />
              <span>Lu</span>
            </div>
            <div class="flex items-center gap-2">
              <div class="w-6 h-8 rounded ring-2 ring-success/70 bg-base-100" />
              <span>Possédé</span>
            </div>
            <div class="flex items-center gap-2">
              <div class="w-6 h-8 rounded ring-2 ring-warning/60 bg-base-100 opacity-65" />
              <span>Souhaité</span>
            </div>
            <div class="flex items-center gap-2">
              <div class="w-6 h-8 rounded ring-2 ring-dashed ring-base-300/20 bg-base-300/30 opacity-40" />
              <span>À paraître</span>
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
    </template>
  </div>

  <!-- ── Context Menu ── -->
  <Teleport to="body">
    <div v-if="contextMenu" class="fixed inset-0 z-[90]" @click="closeContextMenu">
      <div
        class="absolute bg-base-100 rounded-xl shadow-2xl border border-base-300 overflow-hidden w-52 py-1"
        :style="{ top: `${contextMenu.y}px`, left: `${contextMenu.x}px` }"
        @click.stop
      >
        <div class="px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-base-content/40 border-b border-base-200">
          Tome {{ contextMenu.ve.number }}
        </div>
        <ul class="menu menu-xs p-1 gap-0.5">
          <li v-if="contextMenu.ve.isOwned">
            <a
              class="gap-2 text-sm"
              :class="{ 'pointer-events-none opacity-50': toggleMutation.isPending.value }"
              @click="toggleMutation.mutate({ veId: contextMenu.ve.id, field: 'isRead' })"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
              </svg>
              <span :class="contextMenu.ve.isRead ? 'text-base-content/60' : 'font-medium'">
                {{ contextMenu.ve.isRead ? 'Marquer non lu' : 'Marquer lu' }}
              </span>
              <span v-if="contextMenu.ve.isRead" class="ml-auto badge badge-info badge-xs">Lu</span>
            </a>
          </li>
          <li v-if="!contextMenu.ve.isOwned">
            <a
              class="gap-2 text-sm"
              :class="{ 'pointer-events-none opacity-50': toggleMutation.isPending.value }"
              @click="toggleMutation.mutate({ veId: contextMenu.ve.id, field: 'isOwned' })"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-success" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
              </svg>
              <span class="font-medium">Marquer possédé</span>
            </a>
          </li>
          <li v-if="!contextMenu.ve.isOwned && !contextMenu.ve.isWished">
            <a
              class="gap-2 text-sm"
              :class="{ 'pointer-events-none opacity-50': toggleMutation.isPending.value }"
              @click="toggleMutation.mutate({ veId: contextMenu.ve.id, field: 'isWished' })"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
              </svg>
              <span>Ajouter à la wishlist</span>
            </a>
          </li>
          <li v-if="contextMenu.ve.isWished && !contextMenu.ve.isOwned">
            <a
              class="gap-2 text-sm"
              :class="{ 'pointer-events-none opacity-50': purchaseMutation.isPending.value }"
              @click="purchaseMutation.mutate(contextMenu.ve.id)"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
              </svg>
              <span>Marquer comme possédé</span>
            </a>
          </li>
          <div class="h-px bg-base-200 my-0.5 mx-2" />
          <li>
            <a class="gap-2 text-sm" @click="openModalFromContext">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              <span>Détails / Couverture</span>
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
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
              </svg>
              Marquer lus
            </button>
            <button
              v-if="selectedVolumes.some((v) => v.isOwned && v.isRead)"
              class="btn btn-info btn-sm btn-outline gap-1.5"
              :disabled="isBatchProcessing"
              @click="batchToggle('isRead')"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
              </svg>
              Marquer non lus
            </button>
            <button
              v-if="selectedVolumes.some((v) => !v.isOwned)"
              class="btn btn-success btn-sm gap-1.5"
              :disabled="isBatchProcessing"
              @click="batchToggle('isOwned')"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
              </svg>
              Marquer possédés
            </button>
            <button
              v-if="selectedVolumes.some((v) => !v.isOwned && !v.isWished)"
              class="btn btn-warning btn-sm btn-outline gap-1.5"
              :disabled="isBatchProcessing"
              @click="batchToggle('isWished')"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
              </svg>
              Wishlist
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
              <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
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
</style>
