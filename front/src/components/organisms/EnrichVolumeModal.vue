<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { searchVolumeExternal, updateVolume } from '@/api/manga'
import { toggleVolume, purchaseVolume } from '@/api/collection'
import { useUiStore } from '@/stores/useUiStore'
import type { VolumeEntry } from '@/types'

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

// ── Search state ──
const searchQuery = ref('')
const searchResults = ref<{ externalId: string; title: string; edition: string | null; coverUrl: string | null }[]>([])
const isSearching = ref(false)
let searchTimer: ReturnType<typeof setTimeout> | null = null
let skipNextSearch = false

watch(() => [props.open, props.volume] as const, ([open, vol], prev) => {
  const wasOpen = prev?.[0] ?? false
  const justOpened = open && !wasOpen

  if (justOpened && vol) {
    if (vol.coverUrl) skipNextSearch = true
    searchQuery.value = `${props.mangaTitle} tome ${vol.number} ${props.mangaEdition}`.trim()
    if (!vol.coverUrl) {
      runSearch(searchQuery.value)
    }
  }
  if (!open) {
    searchResults.value = []
    skipNextSearch = false
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
  if (q.trim().length < 2) { searchResults.value = []; return }
  isSearching.value = true
  try {
    searchResults.value = await searchVolumeExternal(q.trim())
  } catch {
    searchResults.value = []
  } finally {
    isSearching.value = false
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
        <div class="relative z-10 w-full sm:max-w-2xl bg-base-100 rounded-t-3xl sm:rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90dvh]">
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

          <!-- Actions (bottom on mobile) + Search (top on mobile) -->
          <div class="flex flex-col-reverse sm:flex-row gap-0 overflow-hidden flex-1 min-h-0">
            <!-- Left: current state + quick actions — sticky at bottom on mobile -->
            <div class="shrink-0 sm:w-48 p-4 flex flex-col gap-3 border-t sm:border-t-0 sm:border-r border-base-200">
              <!-- Current cover -->
              <div class="mx-auto w-28 aspect-[2/3] rounded-xl overflow-hidden ring-2 bg-base-200"
                :class="volumeStatus === 'owned' ? 'ring-success/60' : volumeStatus === 'wished' ? 'ring-warning/60' : 'ring-base-300'">
                <img v-if="volume.coverUrl" :src="volume.coverUrl" :alt="`Tome ${volume.number}`" class="w-full h-full object-cover" />
                <div v-else class="w-full h-full flex items-center justify-center text-base-content/20">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                  </svg>
                </div>
              </div>

              <!-- Status badge -->
              <div class="text-center">
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
                  Marquer possédé
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
                  Ajouter wishlist
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
                  <!-- Toggle pill -->
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

              <!-- Price info -->
              <div v-if="volume.priceCode" class="text-center text-sm text-base-content/50">
                {{ volume.priceCode.value.toFixed(2) }}€
              </div>
            </div>

            <!-- Right: cover enrichment via Google Books -->
            <div class="flex-1 min-w-0 flex flex-col overflow-hidden">
              <div class="p-4 border-b border-base-200">
                <p class="text-xs text-base-content/50 mb-2 font-medium uppercase tracking-wide">
                  Enrichir la couverture — Google Books
                </p>
                <label class="input input-bordered input-sm flex items-center gap-2 w-full">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-40 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                  </svg>
                  <input
                    v-model="searchQuery"
                    type="text"
                    class="grow text-sm"
                    placeholder="ex: GTO tome 1 pika"
                  />
                  <span v-if="isSearching" class="loading loading-spinner loading-xs opacity-40" />
                </label>
              </div>

              <!-- Results -->
              <div class="flex-1 overflow-y-auto p-4">
                <p v-if="!searchResults.length && !isSearching" class="text-sm text-base-content/30 text-center py-6">
                  Saisissez le titre + numéro de tome pour trouver la couverture
                </p>

                <div class="grid grid-cols-3 sm:grid-cols-4 gap-3">
                  <button
                    v-for="result in searchResults"
                    :key="result.externalId"
                    class="group flex flex-col gap-1.5 text-left"
                    :disabled="!result.coverUrl"
                    @click="result.coverUrl && enrichMutation.mutate({ coverUrl: result.coverUrl })"
                  >
                    <div class="w-full aspect-[2/3] rounded-lg overflow-hidden bg-base-200 ring-2 ring-transparent transition-all duration-150"
                      :class="result.coverUrl
                        ? 'group-hover:ring-primary group-hover:scale-105 group-hover:shadow-lg cursor-pointer'
                        : 'opacity-40'">
                      <img
                        v-if="result.coverUrl"
                        :src="result.coverUrl"
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
              </div>
            </div>
          </div>
        </div>
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
</style>
