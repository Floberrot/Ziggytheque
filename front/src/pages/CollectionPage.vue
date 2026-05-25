<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted, onUnmounted, type Component } from 'vue'
import { useInfiniteQuery } from '@tanstack/vue-query'
import {
  Search, Plus, Book, X, RotateCcw,
  BookOpen, CheckCircle2, PauseCircle, BookmarkPlus, Ban, Heart, Bell,
} from 'lucide-vue-next'
import { getCollection, type CollectionFilters } from '@/api/collection'
import { useI18n } from 'vue-i18n'
import MangaCard from '@/components/organisms/MangaCard.vue'

const { t } = useI18n()

const GENRES = [
  'shonen', 'shojo', 'seinen', 'josei', 'kodomomuke',
  'isekai', 'fantasy', 'action', 'romance', 'horror',
  'sci_fi', 'slice_of_life', 'sports', 'other',
] as const

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

// ── Preset filters (quick-access chips) ───────────────────────────────────────

interface FilterPreset {
  id: string
  labelKey: string
  icon: Component
  group: 'status' | 'special'
  match: (filters: CollectionFilters) => boolean
  apply: (filters: CollectionFilters) => void
  clear: (filters: CollectionFilters) => void
}

const PRESETS: FilterPreset[] = [
  {
    id: 'in_progress',
    labelKey: 'status.in_progress',
    icon: BookOpen,
    group: 'status',
    match: f => f.readingStatus === 'in_progress',
    apply: f => { f.readingStatus = 'in_progress' },
    clear: f => { f.readingStatus = undefined },
  },
  {
    id: 'completed',
    labelKey: 'status.completed',
    icon: CheckCircle2,
    group: 'status',
    match: f => f.readingStatus === 'completed',
    apply: f => { f.readingStatus = 'completed' },
    clear: f => { f.readingStatus = undefined },
  },
  {
    id: 'on_hold',
    labelKey: 'status.on_hold',
    icon: PauseCircle,
    group: 'status',
    match: f => f.readingStatus === 'on_hold',
    apply: f => { f.readingStatus = 'on_hold' },
    clear: f => { f.readingStatus = undefined },
  },
  {
    id: 'not_started',
    labelKey: 'status.not_started',
    icon: BookmarkPlus,
    group: 'status',
    match: f => f.readingStatus === 'not_started',
    apply: f => { f.readingStatus = 'not_started' },
    clear: f => { f.readingStatus = undefined },
  },
  {
    id: 'dropped',
    labelKey: 'status.dropped',
    icon: Ban,
    group: 'status',
    match: f => f.readingStatus === 'dropped',
    apply: f => { f.readingStatus = 'dropped' },
    clear: f => { f.readingStatus = undefined },
  },
  {
    id: 'favorites',
    labelKey: 'filter.favorites',
    icon: Heart,
    group: 'special',
    match: f => f.sort === 'rating_desc',
    apply: f => { f.sort = 'rating_desc' },
    clear: f => { f.sort = undefined },
  },
  {
    id: 'followed',
    labelKey: 'filter.followedOnly',
    icon: Bell,
    group: 'special',
    match: f => !!f.followed,
    apply: f => { f.followed = true },
    clear: f => { f.followed = false },
  },
]

function togglePreset(preset: FilterPreset) {
  if (preset.match(filters)) {
    preset.clear(filters)
  } else {
    preset.apply(filters)
  }
}

const activePresetCount = computed(() => PRESETS.filter(p => p.match(filters)).length)

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
    <div class="sticky top-0 z-10 bg-base-100/90 backdrop-blur-md border-b border-base-200/70 shadow-[0_1px_0_0_rgba(0,0,0,0.02)]">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3 space-y-3">

        <!-- Row 1: Search + reset -->
        <div class="flex items-center gap-2">
          <div class="relative flex-1 max-w-xl">
            <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-base-content/40 pointer-events-none" />
            <input
              v-model="searchInput"
              type="search"
              class="input input-bordered w-full h-10 pl-10 pr-10 text-sm rounded-xl bg-base-100 focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all"
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

          <div class="flex-1" />

          <button
            v-if="hasActiveFilters"
            class="btn btn-ghost btn-sm gap-1.5 text-base-content/60 hover:text-error shrink-0"
            @click="resetFilters"
          >
            <RotateCcw class="h-3.5 w-3.5" />
            <span class="hidden sm:inline">{{ t('filter.reset') }}</span>
          </button>
        </div>

        <!-- Row 2: Preset chips -->
        <div class="flex flex-wrap items-center gap-1.5">
          <span class="text-[11px] uppercase tracking-wider text-base-content/40 font-semibold mr-1 hidden sm:inline">
            {{ t('filter.quick') }}
          </span>

          <template v-for="(preset, idx) in PRESETS" :key="preset.id">
            <!-- Visual separator between status group and special group -->
            <span
              v-if="idx > 0 && PRESETS[idx - 1].group !== preset.group"
              class="h-5 w-px bg-base-300 mx-1"
              aria-hidden="true"
            />
            <button
              type="button"
              class="group flex items-center gap-1.5 pl-2.5 pr-3 py-1.5 rounded-full text-xs font-medium border transition-all duration-150 active:scale-95"
              :class="preset.match(filters)
                ? 'bg-primary text-primary-content border-primary shadow-sm shadow-primary/20'
                : 'bg-base-100 border-base-300/80 text-base-content/70 hover:border-primary/40 hover:bg-primary/5 hover:text-base-content'"
              @click="togglePreset(preset)"
            >
              <component
                :is="preset.icon"
                class="h-3.5 w-3.5 transition-transform"
                :class="preset.match(filters) ? '' : 'opacity-70 group-hover:opacity-100'"
                stroke-width="2.25"
              />
              {{ t(preset.labelKey) }}
            </button>
          </template>
        </div>

        <!-- Row 3: Advanced filters -->
        <div class="flex flex-wrap items-center gap-2 pt-1">
          <span class="text-[11px] uppercase tracking-wider text-base-content/40 font-semibold mr-1 hidden sm:inline">
            {{ t('filter.advanced') }}
          </span>

          <select
            v-model="filters.genre"
            class="select select-bordered select-sm rounded-lg h-9 min-h-9 text-sm"
            :class="{ 'select-primary text-primary font-medium': filters.genre }"
          >
            <option :value="undefined">{{ t('filter.allGenres') }}</option>
            <option v-for="genre in GENRES" :key="genre" :value="genre">
              {{ t(`genre.${genre}`) }}
            </option>
          </select>

          <select
            v-model="filters.sort"
            class="select select-bordered select-sm rounded-lg h-9 min-h-9 text-sm"
            :class="{ 'select-primary text-primary font-medium': filters.sort }"
          >
            <option :value="undefined">{{ t('filter.allSorts') }}</option>
            <option value="rating_desc">{{ t('filter.sortRatingDesc') }}</option>
            <option value="rating_asc">{{ t('filter.sortRatingAsc') }}</option>
          </select>

          <input
            v-model="filters.edition"
            type="text"
            class="input input-bordered input-sm rounded-lg h-9 w-36 text-sm"
            :class="{ 'input-primary text-primary font-medium': filters.edition }"
            :placeholder="t('filter.editionPlaceholder')"
          />

          <div v-if="activePresetCount > 0 || hasActiveFilters" class="ml-auto text-xs text-base-content/50">
            <span class="inline-flex items-center gap-1">
              <span class="h-1.5 w-1.5 rounded-full bg-primary animate-pulse" />
              {{ total }} {{ total !== 1 ? t('filter.resultsPlural') : t('filter.resultsSingular') }}
            </span>
          </div>
        </div>

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

/* Hide native search clear button — we render our own */
input[type='search']::-webkit-search-cancel-button,
input[type='search']::-webkit-search-decoration {
  -webkit-appearance: none;
  appearance: none;
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
