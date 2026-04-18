<script setup lang="ts">
import { ref, computed } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { getWishlist, clearWishlist, purchaseVolume } from '@/api/wishlist'
import { useUiStore } from '@/stores/useUiStore'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import type { WishlistEntry, VolumeEntry } from '@/types'
import { coverUrl } from '@/utils/coverUrl'

const qc = useQueryClient()
const ui = useUiStore()
const { t } = useI18n()
const router = useRouter()

const { data: entries, isPending } = useQuery({ queryKey: ['wishlist'], queryFn: getWishlist })

const totalWished = computed(() => entries.value?.reduce((s, e) => s + wishedVolumes(e).length, 0) ?? 0)

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
</script>

<template>
  <div class="min-h-screen">
    <!-- Header -->
    <div class="bg-gradient-to-br from-warning/10 via-base-100 to-base-100 border-b border-base-200 px-6 py-8">
      <div class="max-w-5xl mx-auto flex items-center justify-between gap-4 flex-wrap">
        <div>
          <h1 class="text-3xl font-extrabold tracking-tight">{{ t('wishlist.title') }}</h1>
          <p class="text-base-content/50 text-sm mt-1">
            <template v-if="!isPending">
              {{ entries?.length ?? 0 }} série{{ (entries?.length ?? 0) !== 1 ? 's' : '' }} ·
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
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
            {{ batchMode ? 'Terminer' : 'Sélectionner' }}
          </button>
          <RouterLink to="/add" class="btn btn-warning btn-sm gap-1.5 shadow">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
            </svg>
            Ajouter
          </RouterLink>
        </div>
      </div>

      <!-- Batch quick-select row -->
      <div v-if="batchMode" class="max-w-5xl mx-auto mt-3 flex flex-wrap gap-2 text-sm">
        <span class="text-xs text-base-content/40 self-center">Sélectionner :</span>
        <button class="btn btn-xs btn-ghost" @click="selectAllWished">Tous les tomes</button>
        <button class="btn btn-xs btn-ghost text-base-content/30" @click="selectedVeIds = new Set()">Vider</button>
      </div>
    </div>

    <div class="max-w-5xl mx-auto px-6 py-8 space-y-5">
      <!-- Loading -->
      <div v-if="isPending" class="space-y-4">
        <div v-for="i in 3" :key="i" class="h-44 rounded-2xl bg-base-200 animate-pulse" />
      </div>

      <!-- Empty -->
      <div v-else-if="!entries?.length" class="flex flex-col items-center justify-center py-24 gap-4">
        <div class="opacity-20">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
          </svg>
        </div>
        <p class="text-base-content/40 text-lg font-medium">{{ t('wishlist.empty') }}</p>
        <p class="text-base-content/30 text-sm text-center max-w-xs">
          Depuis la vue collection, marquez des tomes comme souhaités ou utilisez le bouton "Ajouter à la liste"
        </p>
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
              <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
              </svg>
            </div>
            <!-- Wished count badge -->
            <div class="absolute top-2 left-2 z-10">
              <span class="badge badge-warning badge-sm font-bold shadow gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                </svg>
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
                  :class="{ loading: clearMutation.isPending.value }"
                  title="Retirer de la liste de souhaits"
                  @click="clearMutation.mutate(entry.id)"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
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
                      <svg v-if="selectedVeIds.has(ve.id)" class="w-6 h-6 text-white drop-shadow" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                      </svg>
                    </div>

                    <!-- Purchase overlay (normal mode hover) -->
                    <div
                      v-else
                      class="absolute inset-0 bg-success/90 opacity-0 hover:opacity-100 transition-opacity flex items-center justify-center text-success-content"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                      </svg>
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
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                  </div>
                  <div class="text-center text-[10px] mt-1 text-base-content/25 leading-none">voir tout</div>
                </div>
              </div>
            </div>

            <!-- Hint text -->
            <p class="text-[10px] text-base-content/25 italic leading-tight">
              {{ batchMode ? 'Cliquez sur les tomes pour les sélectionner' : 'Survolez un tome pour le marquer comme acheté' }}
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Batch Purchase Action Bar ── -->
  <Teleport to="body">
    <Transition name="slide-up">
      <div
        v-if="batchMode && selectedVeIds.size > 0"
        class="fixed bottom-0 left-0 right-0 z-50 bg-base-100/95 backdrop-blur-sm border-t-2 border-warning/40 shadow-2xl"
      >
        <div class="max-w-5xl mx-auto px-4 py-3 flex items-center gap-3 flex-wrap">
          <span class="badge badge-warning badge-lg shrink-0">
            {{ selectedVeIds.size }} tome{{ selectedVeIds.size > 1 ? 's' : '' }}
          </span>
          <div class="flex-1" />
          <button
            class="btn btn-success btn-sm gap-1.5"
            :disabled="isBatchProcessing"
            :class="{ loading: isBatchProcessing }"
            @click="batchPurchase"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
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
