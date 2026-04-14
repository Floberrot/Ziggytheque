<script setup lang="ts">
import { computed } from 'vue'
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { getWishlist, clearWishlist, purchaseVolume } from '@/api/wishlist'
import { useUiStore } from '@/stores/useUiStore'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import type { WishlistEntry, VolumeEntry } from '@/types'

const qc = useQueryClient()
const ui = useUiStore()
const { t } = useI18n()
const router = useRouter()

const { data: entries, isPending } = useQuery({ queryKey: ['wishlist'], queryFn: getWishlist })

const totalWished = computed(() => entries.value?.reduce((s, e) => s + wishedVolumes(e).length, 0) ?? 0)

function wishedVolumes(entry: WishlistEntry): VolumeEntry[] {
  return entry.volumes.filter((v) => v.isWished && !v.isOwned)
}

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
        <RouterLink to="/add" class="btn btn-warning btn-sm gap-1.5 shadow">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
          </svg>
          Ajouter une série
        </RouterLink>
      </div>
    </div>

    <div class="max-w-5xl mx-auto px-6 py-8 space-y-6">
      <!-- Loading -->
      <div v-if="isPending" class="space-y-4">
        <div v-for="i in 3" :key="i" class="h-48 rounded-2xl bg-base-200 animate-pulse" />
      </div>

      <!-- Empty -->
      <div v-else-if="!entries?.length" class="flex flex-col items-center justify-center py-24 gap-4">
        <div class="text-6xl opacity-20">⭐</div>
        <p class="text-base-content/40 text-lg font-medium">{{ t('wishlist.empty') }}</p>
        <p class="text-base-content/30 text-sm text-center max-w-xs">
          Depuis la vue collection, marquez des tomes comme souhaités ou utilisez le bouton "Ajouter à la liste"
        </p>
      </div>

      <!-- Oeuvre cards -->
      <div
        v-else
        class="space-y-5"
      >
        <div
          v-for="(entry, idx) in entries"
          :key="entry.id"
          class="wishlist-card rounded-2xl bg-base-100 shadow-md ring-1 ring-warning/30 overflow-hidden transition-all duration-300 hover:shadow-lg hover:ring-warning/60"
          :style="{ animationDelay: `${idx * 60}ms` }"
        >
          <div class="flex gap-0">
            <!-- Cover sidebar -->
            <div
              class="shrink-0 w-24 sm:w-32 cursor-pointer relative overflow-hidden"
              @click="goToDetail(entry.id)"
            >
              <img
                v-if="entry.manga.coverUrl"
                :src="entry.manga.coverUrl"
                :alt="entry.manga.title"
                class="w-full h-full object-cover transition-transform duration-500 hover:scale-105"
              />
              <div v-else class="w-full h-full min-h-36 flex items-center justify-center bg-base-200 text-3xl text-base-content/20">
                📚
              </div>
              <!-- Wished badge -->
              <div class="absolute top-2 left-2">
                <span class="badge badge-warning badge-sm font-bold shadow">⭐ {{ wishedVolumes(entry).length }}</span>
              </div>
            </div>

            <!-- Content -->
            <div class="flex-1 min-w-0 p-4 space-y-3">
              <!-- Title row -->
              <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                  <button
                    class="text-left font-bold text-lg leading-tight hover:text-primary transition-colors line-clamp-2"
                    @click="goToDetail(entry.id)"
                  >
                    {{ entry.manga.title }}
                  </button>
                  <p class="text-sm text-base-content/50 mt-0.5">
                    {{ entry.manga.edition }}
                    <span v-if="entry.manga.author" class="ml-1">· {{ entry.manga.author }}</span>
                  </p>
                </div>
                <!-- Actions -->
                <div class="flex gap-1.5 shrink-0">
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

              <!-- Owned vs wished summary -->
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

              <!-- Wished volumes scroll -->
              <div class="flex gap-2 overflow-x-auto pb-1 scrollbar-thin">
                <div
                  v-for="ve in wishedVolumes(entry).sort((a, b) => a.number - b.number)"
                  :key="ve.id"
                  class="volume-chip group relative shrink-0 cursor-pointer"
                  :title="`Tome ${ve.number} — Marquer comme acheté`"
                >
                  <!-- Cover thumbnail or number -->
                  <div class="w-10 h-14 rounded-lg overflow-hidden ring-2 ring-warning/60 bg-base-200 relative transition-all duration-150 group-hover:ring-success group-hover:scale-105">
                    <img
                      v-if="ve.coverUrl"
                      :src="ve.coverUrl"
                      :alt="`Tome ${ve.number}`"
                      class="w-full h-full object-cover"
                    />
                    <div v-else class="w-full h-full flex items-center justify-center text-xs font-bold text-base-content/60">
                      {{ ve.number }}
                    </div>
                    <!-- Purchase overlay on hover -->
                    <button
                      class="absolute inset-0 bg-success/90 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-success-content"
                      :class="{ 'opacity-100': purchaseMutation.isPending.value }"
                      @click.stop="purchaseMutation.mutate({ entryId: entry.id, veId: ve.id })"
                    >
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                      </svg>
                    </button>
                  </div>
                  <div class="text-center text-[10px] mt-0.5 text-warning/80 tabular-nums font-medium">
                    T{{ ve.number }}
                  </div>
                </div>

                <!-- View all CTA -->
                <button
                  class="shrink-0 w-10 flex flex-col items-center justify-center gap-1 text-base-content/30 hover:text-primary transition-colors"
                  @click="goToDetail(entry.id)"
                >
                  <div class="w-10 h-14 rounded-lg border-2 border-dashed border-base-300 hover:border-primary flex items-center justify-center transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                  </div>
                  <span class="text-[9px]">tout voir</span>
                </button>
              </div>

              <!-- Tip: click volume to mark as purchased -->
              <p class="text-[10px] text-base-content/25 italic">
                Survolez un tome pour le marquer comme acheté
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.wishlist-card {
  animation: fadeSlideUp 0.4s ease-out both;
}

.scrollbar-thin {
  scrollbar-width: thin;
  scrollbar-color: oklch(var(--wa) / 0.3) transparent;
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
</style>
