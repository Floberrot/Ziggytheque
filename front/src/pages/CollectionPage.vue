<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted, onUnmounted } from 'vue'
import { useInfiniteQuery } from '@tanstack/vue-query'
import { Search, Plus, Book, X, RotateCcw } from 'lucide-vue-next'
import { getCollection, type CollectionFilters } from '@/api/collection'
import { useI18n } from 'vue-i18n'
import MangaCard from '@/components/organisms/MangaCard.vue'

const { t } = useI18n()

const GENRES = [
  'shonen', 'shojo', 'seinen', 'josei', 'kodomomuke',
  'isekai', 'fantasy', 'action', 'romance', 'horror',
  'sci_fi', 'slice_of_life', 'sports', 'other',
] as const

const READING_STATUSES = ['not_started', 'in_progress', 'completed', 'on_hold', 'dropped'] as const

// ── Filter state ──────────────────────────────────────────────────────────────

const searchInput = ref('')

const filters = reactive<CollectionFilters>({
  search:        undefined,
  genre:         undefined,
  edition:       undefined,
  readingStatus: undefined,
  sort:          undefined,
  followed:      false,
})

// Debounce search input (300 ms)
let debounceTimer: ReturnType<typeof setTimeout> | null = null
watch(searchInput, (val) => {
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    filters.search = val.trim() || undefined
  }, 300)
})

const hasActiveFilters = computed(
  () => !!(filters.search || filters.genre || filters.edition || filters.readingStatus || filters.sort || filters.followed),
)

function resetFilters() {
  if (debounceTimer) clearTimeout(debounceTimer)
  searchInput.value    = ''
  filters.search        = undefined
  filters.genre         = undefined
  filters.edition       = undefined
  filters.readingStatus = undefined
  filters.sort          = undefined
  filters.followed      = false
}

// ── Infinite query ────────────────────────────────────────────────────────────

const queryKey = computed(() => [
  'collection',
  {
    search:        filters.search,
    genre:         filters.genre,
    edition:       filters.edition,
    readingStatus: filters.readingStatus,
    sort:          filters.sort,
    followed:      filters.followed,
  },
])

const { data, fetchNextPage, hasNextPage, isFetchingNextPage, isLoading } = useInfiniteQuery({
  queryKey,
  queryFn: ({ pageParam }) =>
    getCollection({ ...filters, page: pageParam as number }),
  getNextPageParam: (lastPage) => {
    const fetched = lastPage.page * lastPage.limit
    return fetched < lastPage.total ? lastPage.page + 1 : undefined
  },
  initialPageParam: 1,
})

const entries = computed(() => data.value?.pages.flatMap((p) => p.items) ?? [])
const total   = computed(() => data.value?.pages[0]?.total ?? 0)

// ── Infinite scroll sentinel ──────────────────────────────────────────────────

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
    <!-- Hero header -->
    <div class="bg-gradient-to-br from-primary/10 via-base-100 to-base-100 border-b border-base-200 px-4 sm:px-6 py-6 sm:py-8">
      <div class="max-w-7xl mx-auto">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
          <div>
            <h1 class="text-3xl font-extrabold tracking-tight">{{ t('collection.title') }}</h1>
            <p class="text-base-content/50 text-sm mt-1">
              {{ total }} oeuvre{{ total !== 1 ? 's' : '' }} suivie{{ total !== 1 ? 's' : '' }}
            </p>
          </div>

          <div class="flex gap-3 flex-wrap items-center">
            <RouterLink to="/add" class="btn btn-primary btn-sm gap-1.5 shadow">
              <Plus class="h-4 w-4" stroke-width="2.5" />
              {{ t('collection.add') }}
            </RouterLink>
          </div>
        </div>
      </div>
    </div>

    <!-- Sticky filter bar -->
    <div class="sticky top-0 z-10 bg-base-100/95 backdrop-blur border-b border-base-200 px-4 sm:px-6 py-3">
      <div class="max-w-7xl mx-auto flex flex-wrap gap-2 items-center">
        <!-- Search -->
        <label class="input input-bordered input-sm flex items-center gap-2 bg-base-100 w-52">
          <Search class="h-4 w-4 opacity-40 shrink-0" />
          <input
            v-model="searchInput"
            type="search"
            class="grow text-sm min-w-0"
            :placeholder="t('filter.searchPlaceholder')"
          />
          <button
            v-if="searchInput"
            class="opacity-40 hover:opacity-100"
            @click="searchInput = ''"
          >
            <X class="h-3 w-3" />
          </button>
        </label>

        <!-- Genre -->
        <select
          v-model="filters.genre"
          class="select select-bordered select-sm"
          :class="{ 'select-primary': filters.genre }"
        >
          <option :value="undefined">{{ t('filter.allGenres') }}</option>
          <option v-for="g in GENRES" :key="g" :value="g">
            {{ t(`genre.${g}`) }}
          </option>
        </select>

        <!-- Reading status -->
        <select
          v-model="filters.readingStatus"
          class="select select-bordered select-sm"
          :class="{ 'select-primary': filters.readingStatus }"
        >
          <option :value="undefined">{{ t('filter.allStatus') }}</option>
          <option v-for="s in READING_STATUSES" :key="s" :value="s">
            {{ t(`status.${s}`) }}
          </option>
        </select>

        <!-- Sort -->
        <select
          v-model="filters.sort"
          class="select select-bordered select-sm"
          :class="{ 'select-primary': filters.sort }"
        >
          <option :value="undefined">{{ t('filter.allSorts') }}</option>
          <option value="rating_desc">{{ t('filter.sortRatingDesc') }}</option>
          <option value="rating_asc">{{ t('filter.sortRatingAsc') }}</option>
        </select>

        <!-- Edition -->
        <input
          v-model="filters.edition"
          type="text"
          class="input input-bordered input-sm w-32"
          :class="{ 'input-primary': filters.edition }"
          :placeholder="t('filter.editionPlaceholder')"
        />

        <!-- Followed toggle -->
        <label class="flex items-center gap-2 cursor-pointer select-none">
          <input
            v-model="filters.followed"
            type="checkbox"
            class="toggle toggle-primary toggle-sm"
          />
          <span class="text-sm" :class="{ 'text-primary font-medium': filters.followed }">
            {{ t('filter.followedOnly') }}
          </span>
        </label>

        <!-- Reset button -->
        <button
          v-if="hasActiveFilters"
          class="btn btn-ghost btn-sm gap-1"
          @click="resetFilters"
        >
          <RotateCcw class="h-3.5 w-3.5" />
          {{ t('filter.reset') }}
        </button>
      </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
      <!-- Loading skeleton -->
      <div v-if="isLoading" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
        <div
          v-for="i in 12"
          :key="i"
          class="aspect-[2/3] rounded-2xl bg-base-200 animate-pulse"
        />
      </div>

      <!-- Empty state -->
      <div v-else-if="!isLoading && entries.length === 0" class="flex flex-col items-center justify-center py-24 gap-4">
        <div class="opacity-20">
          <Book class="h-16 w-16" stroke-width="1" />
        </div>
        <p class="text-base-content/40 text-lg font-medium">
          {{ hasActiveFilters ? 'Aucun résultat pour ces filtres' : t('collection.empty') }}
        </p>
        <button v-if="hasActiveFilters" class="btn btn-ghost btn-sm" @click="resetFilters">
          {{ t('filter.reset') }}
        </button>
        <RouterLink v-else to="/add" class="btn btn-primary btn-sm">
          {{ t('collection.add') }}
        </RouterLink>
      </div>

      <!-- Grid -->
      <div
        v-else
        class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4"
      >
        <MangaCard
          v-for="(entry, i) in entries"
          :key="entry.id"
          :entry="entry"
          :style="{ animationDelay: `${(i % 20) * 30}ms` }"
          class="card-appear"
        />
      </div>

      <!-- Infinite scroll sentinel + loading indicator -->
      <div ref="sentinel" class="h-4 mt-4" />
      <div v-if="isFetchingNextPage" class="flex justify-center py-6">
        <span class="loading loading-spinner loading-md text-primary" />
      </div>
    </div>
  </div>
</template>

<style scoped>
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
