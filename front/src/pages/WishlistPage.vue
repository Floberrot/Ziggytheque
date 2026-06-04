<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useInfiniteQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { CheckSquare, X, Star, Plus, ArrowRight, Check, ShoppingCart, Book, Search } from 'lucide-vue-next'
import { getWishlist, clearWishlist, purchaseVolume } from '@/api/wishlist'
import { useUiStore } from '@/stores/useUiStore'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import type { WishlistEntry, VolumeEntry } from '@/types'
import { coverUrl } from '@/utils/coverUrl'
import BaseLoader from '@/components/atoms/BaseLoader.vue'

const qc = useQueryClient()
const ui = useUiStore()
const { t } = useI18n()
const router = useRouter()

// ── Search (debounced) ──
const searchInput = ref('')
const search = ref<string | undefined>(undefined)

let debounceTimer: ReturnType<typeof setTimeout> | null = null
watch(searchInput, (val) => {
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    search.value = val.trim() || undefined
  }, 300)
})

// ── Infinite query ──
const queryKey = computed(() => ['wishlist', { search: search.value }])

const { data, fetchNextPage, hasNextPage, isFetchingNextPage, isLoading } = useInfiniteQuery({
  queryKey,
  queryFn: ({ pageParam }) =>
    getWishlist({ search: search.value, page: pageParam as number }),
  getNextPageParam: (lastPage) => {
    const fetched = lastPage.page * lastPage.limit
    return fetched < lastPage.total ? lastPage.page + 1 : undefined
  },
  initialPageParam: 1,
})

const entries = computed<WishlistEntry[]>(() => data.value?.pages.flatMap((p) => p.items) ?? [])
const totalSeries = computed(() => data.value?.pages[0]?.total ?? 0)
const isPending = isLoading

const totalWished = computed(() => entries.value.reduce((sum, entry) => sum + wishedVolumes(entry).length, 0))

function wishedVolumes(entry: WishlistEntry): VolumeEntry[] {
  return entry.volumes.filter((v) => v.isWished && !v.isOwned)
}

// ── Batch selection ──
const batchMode = ref(false)
const selectedVeIds = ref<Set<string>>(new Set())
const isBatchProcessing = ref(false)

// Map ve.id → collection entry id for batch purchase calls
const veToEntry = computed(() => {
  const map = new Map<string, string>()
  entries.value?.forEach((entry) => {
    entry.volumes.forEach((ve) => map.set(ve.id, entry.id))
  })
  return map
})

function toggleBatchMode() {
  batchMode.value = !batchMode.value
  if (!batchMode.value) selectedVeIds.value = new Set()
}

function toggleVolumeSelect(veId: string) {
  const next = new Set(selectedVeIds.value)
  if (next.has(veId)) next.delete(veId)
  else next.add(veId)
  selectedVeIds.value = next
}

function selectAllWished() {
  const next = new Set<string>()
  entries.value?.forEach((entry) => {
    wishedVolumes(entry).forEach((v) => next.add(v.id))
  })
  selectedVeIds.value = next
}

function selectEntryWished(entry: WishlistEntry) {
  const next = new Set(selectedVeIds.value)
  wishedVolumes(entry).forEach((v) => next.add(v.id))
  selectedVeIds.value = next
}

async function batchPurchase() {
  if (selectedVeIds.value.size === 0) return
  const count = selectedVeIds.value.size
  const items = [...selectedVeIds.value]
    .map((veId) => ({ veId, entryId: veToEntry.value.get(veId) }))
    .filter((item): item is { veId: string; entryId: string } => !!item.entryId)

  isBatchProcessing.value = true
  try {
    await Promise.all(items.map(({ entryId, veId }) => purchaseVolume(entryId, veId)))
    await qc.invalidateQueries({ queryKey: ['wishlist'] })
    await qc.invalidateQueries({ queryKey: ['collection'] })
    await qc.invalidateQueries({ queryKey: ['stats'] })
    selectedVeIds.value = new Set()
    ui.addToast(`${count} tome${count > 1 ? 's' : ''} marqué${count > 1 ? 's' : ''} comme acheté${count > 1 ? 's' : ''}`, 'success')
  } finally {
    isBatchProcessing.value = false
  }
}

// ── Single mutations ──
const clearMutation = useMutation({
  mutationFn: (id: string) => clearWishlist(id),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['wishlist'] })
    qc.invalidateQueries({ queryKey: ['stats'] })
    ui.addToast('Retiré de la liste de souhaits', 'success')
  },
})

const purchaseMutation = useMutation({
  mutationFn: ({ entryId, veId }: { entryId: string; veId: string }) =>
    purchaseVolume(entryId, veId),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['wishlist'] })
    qc.invalidateQueries({ queryKey: ['collection'] })
    qc.invalidateQueries({ queryKey: ['stats'] })
    ui.addToast(t('wishlist.purchased'), 'success')
  },
})

function goToDetail(id: string) {
  router.push({ name: 'collection-detail', params: { id } })
}

// ── Infinite scroll sentinel ──
const sentinel = ref<HTMLElement | null>(null)
let observer: IntersectionObserver | null = null

onMounted(() => {
  observer = new IntersectionObserver(([entry]) => {
    if (entry.isIntersecting && hasNextPage.value && !isFetchingNextPage.value) {
      fetchNextPage()
    }
  })
  if (sentinel.value) observer.observe(sentinel.value)
})

onUnmounted(() => {
  if (debounceTimer) clearTimeout(debounceTimer)
  observer?.disconnect()
})
</script>

<template>
  <div class="min-h-screen">
    <!-- Header -->
    <div class="bg-gradient-to-br from-warning/10 via-base-100 to-base-100 border-b border-base-200 px-4 sm:px-6 py-6 sm:py-8">
      <div class="max-w-5xl mx-auto flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="text-3xl font-extrabold tracking-tight">{{ t('wishlist.title') }}</h1>
          <p class="text-base-content/50 text-sm mt-1">
            <template v-if="!isPending">
              {{ totalSeries }} série{{ totalSeries !== 1 ? 's' : '' }} ·
              <span class="text-warning font-semibold">{{ totalWished }} tome{{ totalWished !== 1 ? 's' : '' }} souhaité{{ totalWished !== 1 ? 's' : '' }}</span>
            </template>
          </p>
        </div>
        <div class="flex gap-2">
          <!-- Batch mode toggle -->
          <button
            v-if="entries?.length"
            class="btn btn-sm gap-1.5"
            :class="batchMode ? 'btn-primary' : 'btn-ghost'"
            @click="toggleBatchMode"
          >
            <CheckSquare class="h-4 w-4" />
            {{ batchMode ? 'Terminer' : 'Sélectionner' }}
          </button>
          <RouterLink to="/add" class="btn btn-warning btn-sm gap-1.5 shadow">
            <Plus class="h-4 w-4" stroke-width="2.5" />
            Ajouter
          </RouterLink>
        </div>
      </div>

      <!-- Search bar -->
      <div class="max-w-5xl mx-auto mt-4">
        <div class="relative">
          <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-base-content/40 pointer-events-none" />
          <input
            v-model="searchInput"
            type="search"
            class="input input-bordered w-full h-10 pl-10 pr-10 text-sm rounded-xl bg-base-100 focus:outline-none focus:ring-2 focus:ring-warning/30 focus:border-warning transition-all"
            :placeholder="t('filter.searchPlaceholder')"
          />
          <button
            v-if="searchInput"
            class="absolute right-3 top-1/2 -translate-y-1/2 h-5 w-5 flex items-center justify-center rounded-full hover:bg-base-200 text-base-content/40 hover:text-base-content/80 transition"
            :aria-label="t('filter.reset')"
            @click="searchInput = ''"
          >
            <X class="h-3.5 w-3.5" />
          </button>
        </div>
      </div>

      <!-- Batch quick-select row -->
      <div v-if="batchMode" class="max-w-5xl mx-auto mt-3 flex flex-wrap gap-2 text-sm">
        <span class="text-xs text-base-content/40 self-center">Sélectionner :</span>
        <button class="btn btn-xs btn-ghost" @click="selectAllWished">Tous les tomes</button>
        <button class="btn btn-xs btn-ghost text-base-content/30" @click="selectedVeIds = new Set()">Vider</button>
      </div>
    </div>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-6 sm:py-8 space-y-5">
      <!-- Loading -->
      <div v-if="isPending" class="space-y-4">
        <div v-for="i in 3" :key="i" class="h-44 rounded-2xl bg-base-200 animate-pulse" />
      </div>

      <!-- Empty -->
      <div v-else-if="!entries.length" class="flex flex-col items-center justify-center py-24 gap-4">
        <div class="opacity-20">
          <Star class="h-16 w-16" stroke-width="1" />
        </div>
        <template v-if="search">
          <p class="text-base-content/40 text-lg font-medium">Aucun résultat pour « {{ search }} »</p>
          <button class="btn btn-ghost btn-sm" @click="searchInput = ''">Effacer la recherche</button>
        </template>
        <template v-else>
          <p class="text-base-content/40 text-lg font-medium">{{ t('wishlist.empty') }}</p>
          <p class="text-base-content/30 text-sm text-center max-w-xs">
            Depuis la vue collection, marquez des tomes comme souhaités ou utilisez le bouton "Ajouter à la liste"
          </p>
        </template>
      </div>

      <!-- Wishlist cards -->
      <div
        v-for="(entry, idx) in entries"
        v-else
        :key="entry.id"
        class="wishlist-card rounded-2xl bg-base-100 shadow-md ring-1 ring-warning/30 transition-all duration-300 hover:shadow-lg hover:ring-warning/50"
        :style="{ animationDelay: `${idx * 60}ms` }"
      >
        <div class="flex min-h-[180px]">
          <!-- Cover sidebar — fixed width, image fills height via flex stretch -->
          <div
            class="shrink-0 w-28 sm:w-36 relative cursor-pointer overflow-hidden rounded-l-2xl bg-base-200"
            @click="goToDetail(entry.id)"
          >
            <img
              v-if="entry.manga.coverUrl"
              :src="coverUrl(entry.manga.coverUrl)!"
              :alt="entry.manga.title"
              class="absolute inset-0 w-full h-full object-cover transition-transform duration-500 hover:scale-105"
            />
            <div v-else class="absolute inset-0 flex items-center justify-center text-base-content/20">
              <Book class="h-10 w-10" stroke-width="1.5" />
            </div>
            <!-- Wished count badge -->
            <div class="absolute top-2 left-2 z-10">
              <span class="badge badge-warning badge-sm font-bold shadow gap-1">
                <Star class="h-3 w-3" />
                {{ wishedVolumes(entry).length }}
              </span>
            </div>
          </div>

          <!-- Content -->
          <div class="flex-1 min-w-0 p-5 flex flex-col gap-3">
            <!-- Title row -->
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <button
                  class="text-left font-bold text-lg leading-tight hover:text-primary transition-colors line-clamp-2"
                  @click="goToDetail(entry.id)"
                >
                  {{ entry.manga.title }}
                </button>
                <p class="text-sm text-base-content/50 mt-0.5 truncate">
                  {{ entry.manga.edition }}<span v-if="entry.manga.author"> · {{ entry.manga.author }}</span>
                </p>
              </div>
              <!-- Actions -->
              <div class="flex gap-1.5 shrink-0 items-center">
                <!-- Select all for this card (batch mode) -->
                <button
                  v-if="batchMode"
                  class="btn btn-ghost btn-xs gap-1 text-xs"
                  @click="selectEntryWished(entry)"
                >
                  Tout
                </button>
                <button
                  class="btn btn-ghost btn-xs text-error"
                  :disabled="clearMutation.isPending.value"
                  title="Retirer de la liste de souhaits"
                  @click="clearMutation.mutate(entry.id)"
                >
                  <BaseLoader v-if="clearMutation.isPending.value" size="xs" />
                  <X v-else class="h-4 w-4" />
                </button>
              </div>
            </div>

            <!-- Stats pills -->
            <div class="flex items-center gap-3 text-sm">
              <span class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-success inline-block" />
                <span class="text-base-content/70"><span class="font-semibold text-success">{{ entry.ownedCount }}</span> possédé{{ entry.ownedCount !== 1 ? 's' : '' }}</span>
              </span>
              <span class="flex items-center gap-1">
                <span class="w-2 h-2 rounded-full bg-warning inline-block" />
                <span class="text-base-content/70"><span class="font-semibold text-warning">{{ wishedVolumes(entry).length }}</span> souhaité{{ wishedVolumes(entry).length !== 1 ? 's' : '' }}</span>
              </span>
              <span class="text-base-content/30">/ {{ entry.totalVolumes }} tomes</span>
            </div>

            <!-- Volume chips row — horizontal scroll -->
            <!-- py-3 gives room for scale transforms (overflow-x:auto clips y otherwise) -->
            <div class="overflow-x-auto py-3 -mx-1">
              <div class="flex gap-2.5 flex-nowrap px-1">
                <div
                  v-for="ve in wishedVolumes(entry).sort((a, b) => a.number - b.number)"
                  :key="ve.id"
                  class="shrink-0 cursor-pointer"
                  :title="batchMode ? `Tome ${ve.number} — Sélectionner` : `Tome ${ve.number} — Marquer acheté`"
                  @click="batchMode ? toggleVolumeSelect(ve.id) : purchaseMutation.mutate({ entryId: entry.id, veId: ve.id })"
                >
                  <div
                    class="w-14 h-20 rounded-xl overflow-hidden ring-2 bg-base-200 relative transition-all duration-150"
                    :class="batchMode && selectedVeIds.has(ve.id)
                      ? 'ring-primary ring-offset-2 ring-offset-base-100 scale-105 shadow-lg'
                      : batchMode
                        ? 'ring-base-300/50 hover:ring-primary/50 hover:scale-105'
                        : 'ring-warning/60 hover:ring-success hover:scale-110 hover:shadow-md hover:z-10'"
                  >
                    <img
                      v-if="ve.coverUrl"
                      :src="coverUrl(ve.coverUrl)!"
                      :alt="`Tome ${ve.number}`"
                      class="w-full h-full object-cover"
                    />
                    <div v-else class="w-full h-full flex items-center justify-center text-sm font-bold text-base-content/50">
                      {{ ve.number }}
                    </div>

                    <!-- Selected overlay (batch mode) -->
                    <div
                      v-if="batchMode"
                      class="absolute inset-0 flex items-center justify-center transition-all duration-150"
                      :class="selectedVeIds.has(ve.id) ? 'bg-primary/80' : 'bg-transparent hover:bg-base-content/10'"
                    >
                      <Check v-if="selectedVeIds.has(ve.id)" class="w-6 h-6 text-white drop-shadow" stroke-width="3" />
                    </div>

                    <!-- Purchase overlay (normal mode hover) -->
                    <div
                      v-else
                      class="absolute inset-0 bg-success/90 opacity-0 hover:opacity-100 transition-opacity flex items-center justify-center text-success-content"
                    >
                      <Check class="h-5 w-5" stroke-width="2.5" />
                    </div>
                  </div>
                  <div
                    class="text-center text-[11px] mt-1 tabular-nums font-medium leading-none"
                    :class="batchMode && selectedVeIds.has(ve.id) ? 'text-primary font-bold' : 'text-warning/70'"
                  >
                    T{{ ve.number }}
                  </div>
                </div>

                <!-- View all CTA -->
                <div class="shrink-0 cursor-pointer" @click="goToDetail(entry.id)">
                  <div class="w-14 h-20 rounded-xl border-2 border-dashed border-base-300 hover:border-primary flex items-center justify-center transition-colors text-base-content/30 hover:text-primary">
                    <ArrowRight class="h-5 w-5" />
                  </div>
                  <div class="text-center text-[10px] mt-1 text-base-content/25 leading-none">voir tout</div>
                </div>
              </div>
            </div>

            <!-- Hint text -->
            <p class="text-[10px] text-base-content/25 italic leading-tight">
              {{ batchMode ? 'Appuyez sur les tomes pour les sélectionner' : 'Appuyez sur un tome pour le marquer comme acheté' }}
            </p>
          </div>
        </div>
      </div>

      <!-- Infinite scroll sentinel + loading indicator -->
      <div ref="sentinel" class="h-4" />
      <div v-if="isFetchingNextPage" class="flex justify-center py-6">
        <BaseLoader size="md" class="text-warning" />
      </div>
    </div>
  </div>

  <!-- ── Batch Purchase Action Bar ── -->
  <Teleport to="body">
    <Transition name="slide-up">
      <div
        v-if="batchMode && selectedVeIds.size > 0"
        class="fixed bottom-16 lg:bottom-0 left-0 right-0 z-50 bg-base-100/95 backdrop-blur-sm border-t-2 border-warning/40 shadow-2xl"
      >
        <div class="max-w-5xl mx-auto px-4 py-3 flex items-center gap-3 flex-wrap">
          <span class="badge badge-warning badge-lg shrink-0">
            {{ selectedVeIds.size }} tome{{ selectedVeIds.size > 1 ? 's' : '' }}
          </span>
          <div class="flex-1" />
          <button
            class="btn btn-success btn-sm gap-1.5"
            :disabled="isBatchProcessing"
            @click="batchPurchase"
          >
            <BaseLoader v-if="isBatchProcessing" size="xs" />
            <ShoppingCart v-else class="h-4 w-4" />
            Marquer acheté{{ selectedVeIds.size > 1 ? 's' : '' }}
          </button>
          <button class="btn btn-ghost btn-sm shrink-0" @click="selectedVeIds = new Set()">
            Vider
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.wishlist-card {
  animation: fadeSlideUp 0.4s ease-out both;
}

@keyframes fadeSlideUp {
  from {
    opacity: 0;
    transform: translateY(16px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.slide-up-enter-active,
.slide-up-leave-active {
  transition: transform 0.25s ease, opacity 0.2s ease;
}
.slide-up-enter-from,
.slide-up-leave-to {
  transform: translateY(100%);
  opacity: 0;
}
</style>
