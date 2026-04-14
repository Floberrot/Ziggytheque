<script setup lang="ts">
import { ref, computed } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { getCollection } from '@/api/collection'
import { useI18n } from 'vue-i18n'
import MangaCard from '@/components/organisms/MangaCard.vue'

const { t } = useI18n()
const { data: collection, isPending } = useQuery({ queryKey: ['collection'], queryFn: getCollection })

const search = ref('')
const page = ref(1)
const perPage = 18

const filtered = computed(() => {
  if (!collection.value) return []
  const q = search.value.toLowerCase().trim()
  if (!q) return collection.value
  return collection.value.filter(
    (e) =>
      e.manga.title.toLowerCase().includes(q) ||
      e.manga.edition.toLowerCase().includes(q) ||
      (e.manga.author?.toLowerCase().includes(q) ?? false),
  )
})

const paginated = computed(() => {
  const start = (page.value - 1) * perPage
  return filtered.value.slice(start, start + perPage)
})

const totalPages = computed(() => Math.ceil(filtered.value.length / perPage))

const totalOwned = computed(() => collection.value?.reduce((s, e) => s + e.ownedCount, 0) ?? 0)
const totalWished = computed(() => collection.value?.reduce((s, e) => s + e.wishedCount, 0) ?? 0)
const totalVolumes = computed(() => collection.value?.reduce((s, e) => s + e.totalVolumes, 0) ?? 0)
</script>

<template>
  <div class="min-h-screen">
    <!-- Hero header -->
    <div class="bg-gradient-to-br from-primary/10 via-base-100 to-base-100 border-b border-base-200 px-6 py-8">
      <div class="max-w-7xl mx-auto">
        <div class="flex items-start justify-between gap-4 flex-wrap">
          <div>
            <h1 class="text-3xl font-extrabold tracking-tight">{{ t('collection.title') }}</h1>
            <p class="text-base-content/50 text-sm mt-1">
              {{ collection?.length ?? 0 }} oeuvre{{ (collection?.length ?? 0) !== 1 ? 's' : '' }} suivie{{ (collection?.length ?? 0) !== 1 ? 's' : '' }}
            </p>
          </div>

          <!-- Stats pills -->
          <div class="flex gap-3 flex-wrap items-center">
            <div class="stat-pill bg-success/10 text-success">
              <span class="font-bold text-lg">{{ totalOwned }}</span>
              <span class="text-xs opacity-70">possédés</span>
            </div>
            <div v-if="totalWished > 0" class="stat-pill bg-warning/10 text-warning">
              <span class="font-bold text-lg">{{ totalWished }}</span>
              <span class="text-xs opacity-70">souhaités</span>
            </div>
            <div class="stat-pill bg-base-200 text-base-content/70">
              <span class="font-bold text-lg">{{ totalVolumes }}</span>
              <span class="text-xs opacity-70">au total</span>
            </div>
            <RouterLink to="/add" class="btn btn-primary btn-sm gap-1.5 shadow">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
              </svg>
              {{ t('collection.add') }}
            </RouterLink>
          </div>
        </div>

        <!-- Search bar -->
        <div class="mt-5 max-w-md">
          <label class="input input-bordered flex items-center gap-2 bg-base-100">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-40 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
              v-model="search"
              type="search"
              class="grow text-sm"
              :placeholder="t('common.search')"
              @input="page = 1"
            />
          </label>
        </div>
      </div>
    </div>

    <div class="max-w-7xl mx-auto px-6 py-8">
      <!-- Loading -->
      <div v-if="isPending" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-7 gap-4">
        <div
          v-for="i in 12"
          :key="i"
          class="aspect-[2/3] rounded-2xl bg-base-200 animate-pulse"
        />
      </div>

      <!-- Empty state -->
      <div v-else-if="filtered.length === 0" class="flex flex-col items-center justify-center py-24 gap-4">
        <div class="text-6xl opacity-20">📚</div>
        <p class="text-base-content/40 text-lg font-medium">
          {{ search ? 'Aucun résultat pour cette recherche' : t('collection.empty') }}
        </p>
        <RouterLink v-if="!search" to="/add" class="btn btn-primary btn-sm">
          {{ t('collection.add') }}
        </RouterLink>
      </div>

      <!-- Grid -->
      <div
        v-else
        class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-7 gap-4"
      >
        <MangaCard
          v-for="(entry, i) in paginated"
          :key="entry.id"
          :entry="entry"
          :style="{ animationDelay: `${i * 30}ms` }"
          class="card-appear"
        />
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="flex justify-center gap-2 mt-10">
        <button
          class="btn btn-sm btn-ghost"
          :disabled="page === 1"
          @click="page--"
        >
          ‹
        </button>
        <button
          v-for="p in totalPages"
          :key="p"
          class="btn btn-sm"
          :class="p === page ? 'btn-primary' : 'btn-ghost'"
          @click="page = p"
        >
          {{ p }}
        </button>
        <button
          class="btn btn-sm btn-ghost"
          :disabled="page === totalPages"
          @click="page++"
        >
          ›
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.stat-pill {
  @apply flex flex-col items-center px-4 py-2 rounded-xl gap-0;
}

.card-appear {
  animation: fadeSlideUp 0.4s ease-out both;
}

@keyframes fadeSlideUp {
  from {
    opacity: 0;
    transform: translateY(12px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
