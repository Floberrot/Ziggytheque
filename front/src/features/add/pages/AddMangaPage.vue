<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useImportManga } from '@/composables/queries/useMangaQueries'

const router = useRouter()
const step = ref<'search' | 'details'>('search')
const searchQuery = ref('')
const { mutate: importManga, isPending } = useImportManga()

const form = ref({
  title: '',
  edition: '',
  author: '',
  summary: '',
  language: 'fr',
})

function handleImport() {
  importManga(form.value, {
    onSuccess: () => {
      router.push('/collection')
    },
  })
}
</script>

<template>
  <div class="p-4 lg:p-6">
    <h1 class="heading-xl mb-6">Add Manga</h1>

    <div class="max-w-3xl mx-auto">
      <div class="steps w-full mb-8">
        <button
          :class="{ 'step-primary': step === 'search' }"
          class="step"
          @click="step = 'search'"
        >
          Search
        </button>
        <button
          :class="{ 'step-primary': step === 'details' }"
          class="step"
          @click="step = 'details'"
        >
          Details
        </button>
      </div>

      <form class="space-y-4" @submit.prevent="handleImport">
        <div v-if="step === 'search'" class="space-y-4">
          <AInput
            v-model="searchQuery"
            type="search"
            label="Search Manga"
            placeholder="e.g., Attack on Titan"
          />
          <p class="text-sm text-base-content/70">
            Search external databases to populate manga information
          </p>
        </div>

        <div v-if="step === 'details'" class="space-y-4">
          <AInput v-model="form.title" label="Title" placeholder="Manga title" />
          <AInput v-model="form.edition" label="Edition" placeholder="Edition/Publisher" />
          <AInput v-model="form.author" label="Author" placeholder="Author name" />
          <ATextarea v-model="form.summary" label="Summary" placeholder="Brief description" />
        </div>

        <div class="flex gap-2">
          <AButton
            v-if="step === 'details'"
            variant="ghost"
            @click="step = 'search'"
          >
            Back
          </AButton>
          <AButton
            v-if="step === 'search'"
            @click="step = 'details'"
          >
            Continue
          </AButton>
          <AButton
            v-if="step === 'details'"
            :loading="isPending"
            class="flex-1"
          >
            Add to Collection
          </AButton>
        </div>
      </form>
    </div>
  </div>
</template>
