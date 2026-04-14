<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { importManga } from '@/api/manga'
import { addToCollection, addRemainingToWishlist } from '@/api/collection'
import { useUiStore } from '@/stores/useUiStore'
import { useI18n } from 'vue-i18n'
import { useExternalSearch } from '@/composables/useExternalSearch'
import type { ExternalMangaResult } from '@/composables/useExternalSearch'

const router = useRouter()
const qc = useQueryClient()
const ui = useUiStore()
const { t } = useI18n()

const step = ref<1 | 2 | 3>(1)
const collectionEntryId = ref('')

const { query, results, isLoading: searchLoading, error: searchError, clear: clearSearch } = useExternalSearch()

const form = ref({
  title: '',
  edition: '',
  language: 'fr',
  author: '',
  summary: '',
  coverUrl: '',
  genre: '',
  totalVolumes: '' as string | number,
  externalId: '',
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
    totalVolumes: result.totalVolumes ?? '',
    externalId: result.externalId ?? '',
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
      externalId: form.value.externalId || undefined,
      totalVolumes: form.value.totalVolumes !== '' ? Number(form.value.totalVolumes) : undefined,
    }),
  onSuccess: async (data) => {
    // Always add to collection first (creates the oeuvre tracker with all volumes)
    const res = await addToCollection(data.id)
    collectionEntryId.value = res.id
    qc.invalidateQueries({ queryKey: ['collection'] })
    step.value = 3
  },
})

const goCollectionMutation = useMutation({
  mutationFn: () => Promise.resolve(),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['stats'] })
    ui.addToast(t('collection.added'), 'success')
    router.push({ name: 'collection-detail', params: { id: collectionEntryId.value } })
  },
})

const goWishlistMutation = useMutation({
  mutationFn: () => addRemainingToWishlist(collectionEntryId.value),
  onSuccess: () => {
    qc.invalidateQueries({ queryKey: ['wishlist'] })
    qc.invalidateQueries({ queryKey: ['stats'] })
    ui.addToast(t('wishlist.allAdded'), 'success')
    router.push({ name: 'wishlist' })
  },
})

const genres = ['shonen', 'shojo', 'seinen', 'josei', 'isekai', 'fantasy', 'action', 'romance', 'horror', 'sci_fi', 'slice_of_life', 'sports', 'other']
</script>

<template>
  <div class="p-4 md:p-6 max-w-3xl mx-auto space-y-6">
    <div class="flex items-center gap-3">
      <button v-if="step > 1 && step < 3" class="btn btn-ghost btn-sm btn-circle" @click="step = step === 2 ? 1 : 2">
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

      <div v-if="searchError" class="alert alert-warning text-sm py-2">{{ searchError }}</div>

      <div v-if="results.length" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-3">
        <button
          v-for="result in results"
          :key="result.externalId"
          class="group flex flex-col items-center gap-1.5 text-left"
          @click="applyResult(result)"
        >
          <div class="w-full aspect-[2/3] relative rounded-xl overflow-hidden bg-base-200 shadow group-hover:shadow-lg group-hover:scale-105 transition-all duration-150 ring-2 ring-transparent group-hover:ring-primary">
            <img
              v-if="result.coverUrl"
              :src="result.coverUrl"
              :alt="result.title"
              class="w-full h-full object-cover"
            />
            <div v-else class="w-full h-full flex items-center justify-center text-3xl opacity-30">📚</div>
            <!-- Volume count badge -->
            <div v-if="result.totalVolumes" class="absolute bottom-1 right-1 bg-black/70 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full leading-none">
              {{ result.totalVolumes }}T
            </div>
          </div>
          <div class="w-full px-0.5">
            <p class="text-xs font-medium leading-tight line-clamp-2">{{ result.title }}</p>
            <p v-if="result.author" class="text-[10px] text-base-content/40 truncate">{{ result.author }}</p>
          </div>
        </button>
      </div>

      <p v-else-if="!searchLoading && query.length >= 2" class="text-sm text-center text-base-content/40 py-4">
        {{ t('add.noResults') }}
      </p>

      <div class="divider text-xs text-base-content/40">ou</div>
      <button class="btn btn-outline btn-sm w-full" @click="goToForm">
        {{ t('add.fillManually') }}
      </button>
    </div>

    <!-- ── Step 2 : Formulaire ── -->
    <div v-if="step === 2" class="flex gap-5">
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

      <form class="flex-1 space-y-3" @submit.prevent="importMutation.mutate()">
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

        <!-- Total volumes + summary row -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div class="form-control sm:col-span-2">
            <label class="label py-1"><span class="label-text text-xs font-medium">{{ t('manga.summary') }}</span></label>
            <textarea v-model="form.summary" class="textarea textarea-bordered textarea-sm resize-none" rows="3" />
          </div>
          <div class="form-control">
            <label class="label py-1">
              <span class="label-text text-xs font-medium">{{ t('manga.totalVolumes') }}</span>
              <span class="label-text-alt text-base-content/30">optionnel</span>
            </label>
            <input
              v-model="form.totalVolumes"
              type="number"
              min="0"
              max="9999"
              class="input input-bordered input-sm"
              placeholder="ex: 25"
            />
            <label class="label py-0.5">
              <span class="label-text-alt text-base-content/30">Pré-remplit les {{ form.totalVolumes || '?' }} tomes</span>
            </label>
          </div>
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
    <div v-if="step === 3" class="space-y-4">
      <p class="text-sm text-base-content/60 text-center">
        La série a été ajoutée à votre bibliothèque. Que voulez-vous faire ?
      </p>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <!-- Go to collection detail -->
        <button
          class="card bg-primary text-primary-content shadow hover:shadow-xl hover:scale-[1.02] transition-all duration-150 cursor-pointer"
          :class="{ loading: goCollectionMutation.isPending.value }"
          @click="goCollectionMutation.mutate()"
        >
          <div class="card-body items-center text-center gap-3 py-8">
            <span class="text-4xl">📚</span>
            <h3 class="card-title">{{ t('collection.addToCollection') }}</h3>
            <p class="text-sm opacity-80">Gérer les tomes possédés</p>
          </div>
        </button>

        <!-- Mark all as wished + go to wishlist -->
        <button
          class="card bg-warning/20 text-warning-content shadow hover:shadow-xl hover:scale-[1.02] transition-all duration-150 cursor-pointer border border-warning/30"
          :class="{ loading: goWishlistMutation.isPending.value }"
          @click="goWishlistMutation.mutate()"
        >
          <div class="card-body items-center text-center gap-3 py-8">
            <span class="text-4xl">⭐</span>
            <h3 class="card-title text-warning">{{ t('wishlist.addToWishlist') }}</h3>
            <p class="text-sm text-base-content/60">Tous les tomes → liste de souhaits</p>
          </div>
        </button>
      </div>
    </div>
  </div>
</template>
