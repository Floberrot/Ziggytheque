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
const perPage = 12

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
</script>

<template>
  <div class="p-6 space-y-6">
    <div class="flex items-center justify-between gap-4">
      <h1 class="text-2xl font-bold">{{ t('collection.title') }}</h1>
      <RouterLink to="/add" class="btn btn-primary btn-sm">
        + {{ t('collection.add') }}
      </RouterLink>
    </div>

    <input
      v-model="search"
      type="search"
      :placeholder="t('common.search')"
      class="input input-bordered w-full max-w-sm"
      @input="page = 1"
    />

    <div v-if="isPending" class="flex justify-center py-16">
      <span class="loading loading-spinner loading-lg" />
    </div>

    <div v-else-if="paginated.length === 0" class="text-center py-16 text-base-content/50">
      {{ t('collection.empty') }}
    </div>

    <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
      <MangaCard
        v-for="entry in paginated"
        :key="entry.id"
        :entry="entry"
      />
    </div>

    <div v-if="totalPages > 1" class="flex justify-center gap-2">
      <button
        v-for="p in totalPages"
        :key="p"
        class="btn btn-sm"
        :class="p === page ? 'btn-primary' : 'btn-ghost'"
        @click="page = p"
      >
        {{ p }}
      </button>
    </div>
  </div>
</template>
