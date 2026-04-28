<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { getArticles } from '@/api/notification'
import { getCollection } from '@/api/collection'
import { useI18n } from 'vue-i18n'
import ArticleCard from '@/components/molecules/ArticleCard.vue'
import type { CollectionEntry } from '@/types'

const { t } = useI18n()

const page = ref(1)
const limit = 12
const selectedCollectionId = ref<string | undefined>(undefined)

const { data: collection } = useQuery({
  queryKey: ['collection'],
  queryFn: () => getCollection(),
})

const followedEntries = computed<CollectionEntry[]>(
  () => collection.value?.items.filter((e) => e.notificationsEnabled) ?? [],
)

const { data: articlePage, isPending } = useQuery({
  queryKey: computed(() => ['articles', page.value, selectedCollectionId.value]),
  queryFn: () => getArticles({ page: page.value, limit, collectionEntryId: selectedCollectionId.value }),
})

watch(selectedCollectionId, () => { page.value = 1 })

</script>

<template>
  <div class="p-4 sm:p-6 space-y-6">
    <h1 class="text-2xl font-bold">{{ t('notifications.title') }}</h1>

    <div class="max-w-4xl mx-auto px-6 py-8 space-y-6">
      <!-- Filter bar -->
      <div class="flex items-center gap-3 flex-wrap">
        <span class="text-sm text-base-content/60 shrink-0">{{ t('notifications.filterBy') }}</span>
        <button
          class="btn btn-sm"
          :class="selectedCollectionId === undefined ? 'btn-primary' : 'btn-ghost'"
          @click="selectedCollectionId = undefined"
        >
          {{ t('notifications.allMangas') }}
        </button>
        <button
          v-for="entry in followedEntries"
          :key="entry.id"
          class="btn btn-sm gap-1.5"
          :class="selectedCollectionId === entry.id ? 'btn-primary' : 'btn-ghost'"
          @click="selectedCollectionId = entry.id"
        >
          <img
            v-if="entry.manga.coverUrl"
            :src="entry.manga.coverUrl"
            :alt="entry.manga.title"
            class="w-4 h-5 object-cover rounded-sm"
          />
          <span class="truncate max-w-28">{{ entry.manga.title }}</span>
        </button>

        <!-- No followed entries hint -->
        <p v-if="followedEntries.length === 0" class="text-sm text-base-content/40 italic">
          {{ t('notifications.noFollowed') }}
        </p>
      </div>

      <!-- Loading -->
      <div v-if="isPending" class="space-y-3">
        <div v-for="i in 6" :key="i" class="h-28 rounded-xl bg-base-200 animate-pulse" />
      </div>

      <!-- Empty state -->
      <div
        v-else-if="!articlePage?.items?.length"
        class="flex flex-col items-center justify-center py-24 gap-4 text-base-content/40"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-14 w-14 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 12h6m-6-4h4" />
        </svg>
        <p class="text-lg font-medium">{{ t('notifications.empty') }}</p>
      </div>

      <!-- Article list -->
      <div v-else class="space-y-3">
        <ArticleCard
          v-for="article in articlePage.items"
          :key="article.id"
          :article="article"
        />
      </div>

      <!-- Pagination -->
      <div v-if="(articlePage?.totalPages ?? 0) > 1" class="flex justify-center gap-2 mt-8">
        <button class="btn btn-sm btn-ghost" :disabled="page === 1" @click="page--">‹</button>
        <button
          v-for="p in articlePage!.totalPages"
          :key="p"
          class="btn btn-sm"
          :class="p === page ? 'btn-primary' : 'btn-ghost'"
          @click="page = p"
        >
          {{ p }}
        </button>
        <button class="btn btn-sm btn-ghost" :disabled="page === articlePage!.totalPages" @click="page++">›</button>
      </div>
    </div>
  </div>
</template>
