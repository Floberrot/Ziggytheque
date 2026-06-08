<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { getArticles, getFollowedEntries } from '@/api/notification'
import { useI18n } from 'vue-i18n'
import { RouterLink } from 'vue-router'
import { ChevronDown, Check, Search } from 'lucide-vue-next'
import ArticleCard from '@/components/molecules/ArticleCard.vue'
import type { ArticleCollectionEntry } from '@/types'

const { t } = useI18n()

const page = ref(1)
const limit = 12
const selectedCollectionId = ref<string | undefined>(undefined)
const followedSearch = ref('')

// Source of truth for the filter: ALL followed works, never truncated by
// collection pagination — so the selector holds up whatever the count.
const { data: followedEntries } = useQuery({
  queryKey: ['followed-entries'],
  queryFn: () => getFollowedEntries(),
  initialData: [] as ArticleCollectionEntry[],
})

const selectedEntry = computed<ArticleCollectionEntry | undefined>(
  () => followedEntries.value.find((entry) => entry.id === selectedCollectionId.value),
)

const filteredFollowed = computed<ArticleCollectionEntry[]>(() => {
  const term = followedSearch.value.trim().toLowerCase()
  if (!term) return followedEntries.value
  return followedEntries.value.filter((entry) => entry.manga.title.toLowerCase().includes(term))
})

const { data: articlePage, isPending } = useQuery({
  queryKey: computed(() => ['articles', page.value, selectedCollectionId.value]),
  queryFn: () => getArticles({ page: page.value, limit, collectionEntryId: selectedCollectionId.value }),
})

watch(selectedCollectionId, () => { page.value = 1 })

// Reset to "All" if the active selection is no longer followed (unfollowed elsewhere).
watch(followedEntries, (entries) => {
  if (selectedCollectionId.value && !entries.some((entry) => entry.id === selectedCollectionId.value)) {
    selectedCollectionId.value = undefined
  }
})

function selectEntry(id: string | undefined): void {
  selectedCollectionId.value = id
  followedSearch.value = ''
  // Close the DaisyUI focus-based dropdown after picking.
  ;(document.activeElement as HTMLElement | null)?.blur()
}
</script>

<template>
  <div class="p-4 sm:p-6 space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold">{{ t('notifications.title') }}</h1>
      <RouterLink
        :to="{ name: 'notification-preferences' }"
        class="btn btn-ghost btn-sm gap-2 text-base-content/50 hover:text-base-content"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
          <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/>
          <circle cx="12" cy="12" r="3"/>
        </svg>
        {{ t('notifications.settings.title') }}
      </RouterLink>
    </div>

    <div class="max-w-4xl mx-auto px-6 py-8 space-y-8">
      <!-- Filter bar — a single dropdown that scales to any number of followed works -->
      <div class="flex items-center gap-3 flex-wrap">
        <span class="text-sm text-base-content/60 shrink-0">{{ t('notifications.filterBy') }}</span>

        <div v-if="followedEntries.length" class="dropdown">
          <button tabindex="0" class="btn btn-sm gap-2 normal-case max-w-full">
            <template v-if="selectedEntry">
              <img
                v-if="selectedEntry.manga.coverUrl"
                :src="selectedEntry.manga.coverUrl"
                :alt="selectedEntry.manga.title"
                class="w-4 h-5 object-cover rounded-sm shrink-0"
              />
              <span class="truncate max-w-40">{{ selectedEntry.manga.title }}</span>
            </template>
            <span v-else>{{ t('notifications.allMangas') }}</span>
            <ChevronDown class="w-4 h-4 shrink-0 opacity-60" />
          </button>

          <div
            tabindex="0"
            class="dropdown-content bg-base-200 rounded-box shadow-lg z-50 mt-1 w-72 max-w-[calc(100vw-3rem)] p-2"
          >
            <!-- Search appears only once the list is long enough to need it -->
            <label
              v-if="followedEntries.length > 8"
              class="input input-sm input-bordered flex items-center gap-2 mb-2"
            >
              <Search class="w-4 h-4 opacity-50" />
              <input
                v-model="followedSearch"
                type="text"
                class="grow"
                :placeholder="t('notifications.searchFollowed')"
              />
            </label>

            <ul class="max-h-72 overflow-y-auto space-y-0.5">
              <li>
                <button
                  class="btn btn-ghost btn-sm w-full justify-between font-normal"
                  :class="{ 'btn-active text-primary': selectedCollectionId === undefined }"
                  @click="selectEntry(undefined)"
                >
                  <span>{{ t('notifications.allMangas') }}</span>
                  <Check v-if="selectedCollectionId === undefined" class="w-4 h-4 shrink-0" />
                </button>
              </li>
              <li v-for="entry in filteredFollowed" :key="entry.id">
                <button
                  class="btn btn-ghost btn-sm w-full justify-start gap-2 font-normal"
                  :class="{ 'btn-active text-primary': selectedCollectionId === entry.id }"
                  @click="selectEntry(entry.id)"
                >
                  <img
                    v-if="entry.manga.coverUrl"
                    :src="entry.manga.coverUrl"
                    :alt="entry.manga.title"
                    class="w-4 h-5 object-cover rounded-sm shrink-0"
                  />
                  <span class="truncate flex-1 text-left">{{ entry.manga.title }}</span>
                  <Check v-if="selectedCollectionId === entry.id" class="w-4 h-4 shrink-0" />
                </button>
              </li>
              <li
                v-if="filteredFollowed.length === 0"
                class="px-2 py-3 text-center text-sm text-base-content/40"
              >
                {{ t('notifications.noFollowedMatch') }}
              </li>
            </ul>
          </div>
        </div>

        <p v-else class="text-sm text-base-content/40 italic">
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
