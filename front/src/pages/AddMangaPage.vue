<script setup lang="ts">
import { ref, computed, watch } from 'vue'
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

const { query, results, isLoading: searchLoading, isLoadingMore, hasMore, loadMore, error: searchError, search: runSearch, clear: clearSearch } = useExternalSearch()

function onResultsScroll(event: Event) {
  const el = event.target as HTMLElement
  if (el.scrollTop + el.clientHeight >= el.scrollHeight - 100) {
    loadMore()
  }
}

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

const frenchEditions = [
  'Pika Édition',
  'Glénat',
  'Kana',
  'Ki-oon',
  'Kazé Manga',
  'Kurokawa',
  'Delcourt / Tonkam',
  'Akata',
  'Nobi Nobi!',
  'Doki-Doki',
  'Soleil Manga',
  'Michel Lafon',
  'J\'ai Lu',
  'Panini Comics',
  'Bamboo Édition',
  'Kami',
  'Vega-Dupuis',
]

const editionInput = ref('')
watch(() => form.value.edition, (v) => { if (v !== editionInput.value) editionInput.value = v })
const editionFiltered = computed(() => {
  const q = editionInput.value.toLowerCase().trim()
  if (!q) return frenchEditions
  return frenchEditions.filter((e) => e.toLowerCase().includes(q))
})
const showEditionDropdown = ref(false)

function selectEdition(edition: string) {
  form.value.edition = edition
  editionInput.value = edition
  showEditionDropdown.value = false
}

// Sync editionInput when form.edition changes (e.g. from applyResult)
function onEditionInput() {
  form.value.edition = editionInput.value
  showEditionDropdown.value = true
}
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
    <div v-if="step === 1" class="space-y-3">
      <div class="flex gap-2 items-center">
        <label class="input input-bordered flex items-center gap-2 flex-1">
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
        <button
          v-if="query.trim().length >= 2"
          class="btn btn-square btn-outline btn-sm"
          :class="{ loading: searchLoading }"
          :disabled="searchLoading"
          title="Relancer la recherche"
          @click="runSearch(query)"
        >
          <svg v-if="!searchLoading" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
        </button>
      </div>

      <div v-if="searchError" class="alert alert-warning text-sm py-2">{{ searchError }}</div>

      <!-- Scrollable results container — fires loadMore when scrolled near bottom -->
      <div
        v-if="results.length"
        class="overflow-y-auto max-h-[55vh] rounded-xl border border-base-200"
        @scroll="onResultsScroll"
      >
        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-3 p-3">
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
              <div v-else class="w-full h-full flex items-center justify-center opacity-30 text-base-content">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
              </div>
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
        <!-- Load more indicator -->
        <div v-if="isLoadingMore || hasMore" class="py-3 flex items-center justify-center gap-2 text-xs text-base-content/40 border-t border-base-200">
          <span v-if="isLoadingMore" class="loading loading-spinner loading-xs" />
          <span v-else>Faites défiler pour en voir plus</span>
        </div>
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
          <!-- Edition combobox -->
          <div class="form-control relative">
            <label class="label py-1"><span class="label-text text-xs font-medium">{{ t('manga.edition') }} *</span></label>
            <input
              v-model="editionInput"
              type="text"
              class="input input-bordered input-sm"
              placeholder="Pika, Glénat, Kana…"
              required
              autocomplete="off"
              @input="onEditionInput"
              @focus="showEditionDropdown = true"
              @blur="() => setTimeout(() => (showEditionDropdown = false), 150)"
            />
            <!-- Dropdown suggestions -->
            <ul
              v-if="showEditionDropdown && editionFiltered.length"
              class="absolute top-full left-0 right-0 z-30 mt-0.5 bg-base-100 border border-base-300 rounded-lg shadow-lg max-h-40 overflow-y-auto text-sm"
            >
              <li
                v-for="ed in editionFiltered"
                :key="ed"
                class="px-3 py-1.5 cursor-pointer hover:bg-primary hover:text-primary-content transition-colors"
                @mousedown.prevent="selectEdition(ed)"
              >
                {{ ed }}
              </li>
            </ul>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="form-control">
            <label class="label py-1">
              <span class="label-text text-xs font-medium">{{ t('manga.author') }}</span>
              <span v-if="form.externalId && !form.author" class="label-text-alt text-warning/80 text-[10px]">Non trouvé — à saisir</span>
            </label>
            <input v-model="form.author" type="text" class="input input-bordered input-sm" placeholder="ex: Kentaro Miura" />
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
            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
            </svg>
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
            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-warning" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
            </svg>
            <h3 class="card-title text-warning">{{ t('wishlist.addToWishlist') }}</h3>
            <p class="text-sm text-base-content/60">Tous les tomes → liste de souhaits</p>
          </div>
        </button>
      </div>
    </div>
  </div>
</template>
