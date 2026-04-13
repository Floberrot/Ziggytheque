<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { importManga } from '@/api/manga'
import { addToCollection } from '@/api/collection'
import { addToWishlist } from '@/api/wishlist'
import { useUiStore } from '@/stores/useUiStore'
import { useI18n } from 'vue-i18n'
import { useExternalSearch } from '@/composables/useExternalSearch'
import type { ExternalMangaResult } from '@/composables/useExternalSearch'

const router = useRouter()
const qc = useQueryClient()
const ui = useUiStore()
const { t } = useI18n()

const step = ref<1 | 2 | 3>(1)
const mangaId = ref('')

const { query, results, isLoading: searchLoading, error: searchError, clear: clearSearch } = useExternalSearch()

const form = ref({
  title: '',
  edition: '',
  language: 'fr',
  author: '',
  summary: '',
  coverUrl: '',
  genre: '',
})

const coverPreview = computed(() => form.value.coverUrl || null)

function applyResult(result: ExternalMangaResult): void {
  form.value = {
    title: result.title,
    edition: result.edition ?? '',
    language: result.language,
    author: result.author ?? '',
    summary: result.summary ?? '',
    coverUrl: result.coverUrl ?? '',
    genre: result.genre ?? '',
  }
  clearSearch()
  step.value = 2
}

function goToForm(): void {
  clearSearch()
  step.value = 2
}

const importMutation = useMutation({
  mutationFn: () =>
    importManga({
      title: form.value.title,
      edition: form.value.edition,
      language: form.value.language,
      author: form.value.author || undefined,
      summary: form.value.summary || undefined,
      coverUrl: form.value.coverUrl || undefined,
      genre: form.value.genre || undefined,
    }),
  onSuccess: (data) => {
    mangaId.value = data.id
    step.value = 3
  },
})

const addCollectionMutation = useMutation({
  mutationFn: () => addToCollection(mangaId.value),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['collection'] })
    qc.invalidateQueries({ queryKey: ['stats'] })
    ui.addToast(t('collection.added'), 'success')
    router.push({ name: 'collection' })
  },
})

const addWishlistMutation = useMutation({
  mutationFn: () => addToWishlist(mangaId.value),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['wishlist'] })
    qc.invalidateQueries({ queryKey: ['stats'] })
    ui.addToast(t('wishlist.added'), 'success')
    router.push({ name: 'wishlist' })
  },
})

const genres = ['shonen', 'shojo', 'seinen', 'josei', 'isekai', 'fantasy', 'action', 'romance', 'horror', 'sci_fi', 'slice_of_life', 'sports', 'other']
</script>

<template>
  <div class="p-4 md:p-6 max-w-3xl mx-auto space-y-6">
    <div class="flex items-center gap-3">
      <button v-if="step > 1" class="btn btn-ghost btn-sm btn-circle" @click="step = step === 3 ? 2 : 1">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
      </button>
      <h1 class="text-2xl font-bold">{{ t('add.title') }}</h1>
    </div>

    <!-- Step indicator -->
    <ul class="steps w-full text-xs">
      <li class="step" :class="step >= 1 ? 'step-primary' : ''">{{ t('add.search') }}</li>
      <li class="step" :class="step >= 2 ? 'step-primary' : ''">{{ t('add.info') }}</li>
      <li class="step" :class="step >= 3 ? 'step-primary' : ''">{{ t('add.destination') }}</li>
    </ul>

    <!-- ── Step 1 : Recherche ── -->
    <div v-if="step === 1" class="space-y-4">
      <!-- Search input -->
      <label class="input input-bordered flex items-center gap-2 w-full">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 opacity-50 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <input
          v-model="query"
          type="text"
          class="grow"
          :placeholder="t('add.searchPlaceholder')"
          autocomplete="off"
        />
        <span v-if="searchLoading" class="loading loading-spinner loading-xs opacity-50" />
      </label>

      <!-- Error -->
      <div v-if="searchError" class="alert alert-warning text-sm py-2">{{ searchError }}</div>

      <!-- Results grid -->
      <div v-if="results.length" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-3">
        <button
          v-for="result in results"
          :key="result.externalId"
          class="group flex flex-col items-center gap-1.5 text-left"
          @click="applyResult(result)"
        >
          <div class="w-full aspect-[2/3] rounded-xl overflow-hidden bg-base-200 shadow group-hover:shadow-lg group-hover:scale-105 transition-all duration-150 ring-2 ring-transparent group-hover:ring-primary">
            <img
              v-if="result.coverUrl"
              :src="result.coverUrl"
              :alt="result.title"
              class="w-full h-full object-cover"
            />
            <div v-else class="w-full h-full flex items-center justify-center text-3xl opacity-30">📚</div>
          </div>
          <div class="w-full px-0.5">
            <p class="text-xs font-medium leading-tight line-clamp-2">{{ result.title }}</p>
            <p v-if="result.edition" class="text-xs text-base-content/40 truncate">{{ result.edition }}</p>
          </div>
        </button>
      </div>

      <!-- No results -->
      <p v-else-if="!searchLoading && query.length >= 2" class="text-sm text-center text-base-content/40 py-4">
        {{ t('add.noResults') }}
      </p>

      <!-- Manual fallback -->
      <div class="divider text-xs text-base-content/40">ou</div>
      <button class="btn btn-outline btn-sm w-full" @click="goToForm">
        {{ t('add.fillManually') }}
      </button>
    </div>

    <!-- ── Step 2 : Formulaire ── -->
    <div v-if="step === 2" class="flex gap-5">

      <!-- Cover preview (desktop) -->
      <div class="hidden md:flex flex-col items-center gap-2 shrink-0">
        <div class="w-32 aspect-[2/3] rounded-xl overflow-hidden bg-base-200 shadow-md ring-1 ring-base-300">
          <img
            v-if="coverPreview"
            :src="coverPreview"
            alt="Aperçu couverture"
            class="w-full h-full object-cover"
          />
          <div v-else class="w-full h-full flex flex-col items-center justify-center gap-1 text-base-content/20">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span class="text-xs">Couverture</span>
          </div>
        </div>
        <p class="text-xs text-base-content/30 text-center leading-tight">Aperçu<br/>automatique</p>
      </div>

      <!-- Form -->
      <form class="flex-1 space-y-3" @submit.prevent="importMutation.mutate()">
        <!-- Title + Edition row -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="form-control">
            <label class="label py-1"><span class="label-text text-xs font-medium">{{ t('manga.title') }} *</span></label>
            <input v-model="form.title" type="text" class="input input-bordered input-sm" required />
          </div>
          <div class="form-control">
            <label class="label py-1"><span class="label-text text-xs font-medium">{{ t('manga.edition') }} *</span></label>
            <input v-model="form.edition" type="text" class="input input-bordered input-sm" placeholder="Kana, Glénat…" required />
          </div>
        </div>

        <!-- Author + Language row -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="form-control">
            <label class="label py-1"><span class="label-text text-xs font-medium">{{ t('manga.author') }}</span></label>
            <input v-model="form.author" type="text" class="input input-bordered input-sm" />
          </div>
          <div class="form-control">
            <label class="label py-1"><span class="label-text text-xs font-medium">{{ t('manga.language') }}</span></label>
            <select v-model="form.language" class="select select-bordered select-sm">
              <option value="fr">Français</option>
              <option value="en">English</option>
              <option value="jp">日本語</option>
            </select>
          </div>
        </div>

        <!-- Genre + Cover URL row -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="form-control">
            <label class="label py-1"><span class="label-text text-xs font-medium">{{ t('manga.genre') }}</span></label>
            <select v-model="form.genre" class="select select-bordered select-sm">
              <option value="">—</option>
              <option v-for="g in genres" :key="g" :value="g" class="capitalize">{{ g }}</option>
            </select>
          </div>
          <div class="form-control">
            <label class="label py-1"><span class="label-text text-xs font-medium">{{ t('manga.coverUrl') }}</span></label>
            <input v-model="form.coverUrl" type="url" class="input input-bordered input-sm" placeholder="https://…" />
          </div>
        </div>

        <!-- Summary -->
        <div class="form-control">
          <label class="label py-1"><span class="label-text text-xs font-medium">{{ t('manga.summary') }}</span></label>
          <textarea v-model="form.summary" class="textarea textarea-bordered textarea-sm resize-none" rows="3" />
        </div>

        <button
          type="submit"
          class="btn btn-primary w-full"
          :class="{ loading: importMutation.isPending.value }"
        >
          {{ t('common.next') }}
        </button>
      </form>
    </div>

    <!-- ── Step 3 : Destination ── -->
    <div v-if="step === 3" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <button
        class="card bg-primary text-primary-content shadow hover:shadow-xl hover:scale-[1.02] transition-all duration-150 cursor-pointer"
        :class="{ loading: addCollectionMutation.isPending.value }"
        @click="addCollectionMutation.mutate()"
      >
        <div class="card-body items-center text-center gap-3 py-8">
          <span class="text-4xl">📚</span>
          <h3 class="card-title">{{ t('collection.addToCollection') }}</h3>
          <p class="text-sm opacity-80">Je l'ai ou je veux le suivre</p>
        </div>
      </button>

      <button
        class="card bg-base-100 shadow hover:shadow-xl hover:scale-[1.02] transition-all duration-150 cursor-pointer border border-base-300"
        :class="{ loading: addWishlistMutation.isPending.value }"
        @click="addWishlistMutation.mutate()"
      >
        <div class="card-body items-center text-center gap-3 py-8">
          <span class="text-4xl">⭐</span>
          <h3 class="card-title">{{ t('wishlist.addToWishlist') }}</h3>
          <p class="text-sm text-base-content/60">Je veux l'acheter plus tard</p>
        </div>
      </button>
    </div>
  </div>
</template>
