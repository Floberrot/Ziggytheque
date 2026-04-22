<script setup lang="ts">
import { ref } from 'vue'
import { useImportManga } from '@/composables/queries/useMangaQueries'

const title = ref('')
const edition = ref('')
const { mutate, isPending } = useImportManga()

function handleSubmit() {
  mutate({
    title: title.value,
    edition: edition.value,
    language: 'fr',
  })
}
</script>

<template>
  <div class="p-4 lg:p-6">
    <h1 class="heading-xl mb-6">Add Manga</h1>
    <div class="max-w-2xl">
      <form class="space-y-4" @submit.prevent="handleSubmit">
        <AInput v-model="title" label="Title" placeholder="Enter manga title" />
        <AInput v-model="edition" label="Edition" placeholder="Enter edition" />
        <AButton :loading="isPending" class="w-full">
          Add to collection
        </AButton>
      </form>
    </div>
  </div>
</template>
