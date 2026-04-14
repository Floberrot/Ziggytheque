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
  const q = search.value.toLowerCase()
  return collection.value.filter(
    (e) =>
      e.manga.title.toLowerCase().includes(q) || e.manga.edition.toLowerCase().includes(q),
  )
})

const paginated = computed(() => {
  const start = (page.value - 1) * perPage
  return filtered.value.slice(start, start + perPage)
})

const totalPages = computed(() => Math.ceil(filtered.value.length / perPage))
const totalOwned = computed(() => collection.value?.reduce((s, e) => s + e.ownedCount, 0) ?? 0)
const totalVolumes = computed(() => collection.value?.reduce((s, e) => s + e.totalVolumes, 0) ?? 0)
const totalWishlist = computed(() => collection.value?.reduce((s, e) => s + e.wishlistCount, 0) ?? 0)
</script>

<template>
  <div class="p-4 md:p-6 space-y-6 max-w-screen-2xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h1 class="text-3xl font-bold tracking-tight">{{ t('collection.title') }}</h1>
        <p v-if="!isPending && collection?.length" class="text-sm text-base-content/50 mt-1">
          {{ collection.length }} {{ t('collection.oeuvres') }} &bull;
          <span class="text-success font-medium">{{ totalOwned }}</span> {{ t('collection.owned') }}
          <template v-if="totalWishlist > 0">
            &bull; <span class="text-warning font-medium">{{ totalWishlist }}</span> {{ t('collection.wishlisted') }}
          </template>
          <template v-if="totalVolumes > 0">
            / {{ totalVolumes }} {{ t('collection.totalVolumes') }}
          </template>
        </p>
      </div>
      <RouterLink to="/add" class="btn btn-primary gap-2 shrink-0">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        {{ t('collection.add') }}
      </RouterLink>
    </div>

    <!-- Search bar -->
    <label class="input input-bordered flex items-center gap-2 w-full max-w-sm">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-40 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
      </svg>
      <input
        v-model="search"
        type="search"
        :placeholder="t('common.search')"
        class="grow bg-transparent outline-none"
        @input="page = 1"
      />
    </label>

    <!-- Loading -->
    <div v-if="isPending" class="flex justify-center py-20">
      <span class="loading loading-spinner loading-lg text-primary" />
    </div>

    <!-- Empty state -->
    <div
      v-else-if="paginated.length === 0"
      class="flex flex-col items-center justify-center py-24 gap-4 text-base-content/40"
    >
      <svg xmlns="http://www.w3.org/2000/svg" class="w-16 h-16 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
      </svg>
      <p class="text-lg font-medium">{{ t('collection.empty') }}</p>
      <RouterLink to="/add" class="btn btn-primary btn-sm">
        {{ t('collection.add') }}
      </RouterLink>
    </div>

    <!-- Grid of cards -->
    <div
      v-else
      class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4"
    >
      <MangaCard
        v-for="entry in paginated"
        :key="entry.id"
        :entry="entry"
        mode="collection"
      />
    </div>

    <!-- Pagination -->
    <div v-if="totalPages > 1" class="flex justify-center gap-2 flex-wrap">
      <button
        class="btn btn-sm btn-ghost"
        :disabled="page === 1"
        @click="page--"
      >
        ←
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
        →
      </button>
    </div>
  </div>
</template>
