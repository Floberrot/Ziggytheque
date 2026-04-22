<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useImportManga, useInfiniteExternalSearch } from '@/composables/queries/useMangaQueries'

const router = useRouter()
const step = ref<'search' | 'details'>('search')
const searchQuery = ref('')
const { mutate: importManga, isPending } = useImportManga()

const { data: searchData, hasNextPage, fetchNextPage } = useInfiniteExternalSearch(searchQuery.value)

const searchResults = computed(() => {
  return searchData.value?.pages.flatMap((p: any) => p) ?? []
})

const form = ref({
  title: '',
  edition: '',
  author: '',
  summary: '',
  language: 'fr',
  genre: '',
  totalVolumes: 0,
  externalId: '',
  coverUrl: '',
})

function selectResult(result: any) {
  form.value = {
    title: result.title,
    edition: result.edition ?? '',
    language: result.language ?? 'fr',
    author: result.author ?? '',
    summary: result.summary ?? '',
    genre: result.genre ?? '',
    totalVolumes: result.totalVolumes ?? 0,
    externalId: result.externalId ?? '',
    coverUrl: result.coverUrl ?? '',
  }
  step.value = 'details'
}

function handleImport() {
  importManga(form.value, {
    onSuccess: () => {
      router.push('/collection')
    },
  })
}

function onSearchScroll(event: Event) {
  const el = event.target as HTMLElement
  if (el.scrollTop + el.clientHeight >= el.scrollHeight - 100 && hasNextPage.value) {
    fetchNextPage()
  }
}
</script>

<template>
  <div class="p-4 lg:p-6">
    <h1 class="heading-xl mb-6">Add Manga</h1>

    <div class="max-w-3xl mx-auto">
      <div v-if="step === 'search'" class="space-y-4">
        <AInput
          v-model="searchQuery"
          type="search"
          label="Search Manga"
          placeholder="e.g., Attack on Titan"
        />

        <div
          v-if="searchQuery && searchResults.length"
          class="border border-base-300 rounded-lg p-4 max-h-96 overflow-y-auto space-y-2"
          @scroll="onSearchScroll"
        >
          <div
            v-for="result in searchResults"
            :key="result.externalId"
            class="p-3 bg-base-200 rounded cursor-pointer hover:bg-base-300 transition-colors"
            @click="selectResult(result)"
          >
            <div class="font-semibold">{{ result.title }}</div>
            <div class="text-sm text-base-content/70">{{ result.edition }}</div>
          </div>
        </div>

        <div v-else-if="searchQuery && !searchResults.length" class="text-center py-8">
          <p class="text-base-content/70">No results found</p>
        </div>

        <div v-else class="text-center py-8 text-base-content/50">
          <p>Enter a manga title to search</p>
        </div>

        <AButton @click="step = 'details'" variant="ghost">
          Skip search & enter manually
        </AButton>
      </div>

      <form v-if="step === 'details'" class="space-y-4" @submit.prevent="handleImport">
        <AInput v-model="form.title" label="Title" placeholder="Manga title" required />
        <AInput v-model="form.edition" label="Edition" placeholder="Edition/Publisher" />
        <AInput v-model="form.author" label="Author" placeholder="Author name" />
        <AInput v-model="form.genre" label="Genre" placeholder="e.g., Action, Fantasy" />
        <ATextarea v-model="form.summary" label="Summary" placeholder="Brief description" />
        <AInput v-model.number="form.totalVolumes" type="number" label="Total Volumes" />

        <div class="flex gap-2">
          <AButton variant="ghost" @click="step = 'search'">
            Back
          </AButton>
          <AButton :loading="isPending" type="submit" class="flex-1">
            Add to Collection
          </AButton>
        </div>
      </form>
    </div>
  </div>
</template>
