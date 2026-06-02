import { ref, watch } from 'vue'
import client from '@/api/client'
import { useUiStore } from '@/stores/useUiStore'

export interface ExternalMangaResult {
  externalId: string
  title: string
  edition: string | null
  language: string
  author: string | null
  summary: string | null
  coverUrl: string | null
  genre: string | null
  totalVolumes: number | null
}

export type SearchProvider = 'jikan' | 'googlebooks'

export interface SearchProviderOption {
  /** Sent to the backend as the `provider` query param. */
  key: SearchProvider
  /** Brand name shown in the tooltip and the picker. */
  label: string
  /** Value pre-filled into the search field — visible and editable. */
  defaultQuery: string
}

/**
 * Selectable search providers. `defaultQuery` is the keyword each provider
 * needs by default: Google Books has no manga `type` filter so it gets a
 * "manga" keyword, while Jikan already filters server-side and needs none.
 */
export const SEARCH_PROVIDERS: SearchProviderOption[] = [
  { key: 'jikan', label: 'MyAnimeList', defaultQuery: '' },
  { key: 'googlebooks', label: 'Google Books', defaultQuery: 'manga' },
]

const DEFAULT_PROVIDER: SearchProvider = 'jikan'
const STORAGE_KEY = 'ziggytheque.searchProvider'
const EXTERNAL_API_URL =
  (import.meta.env.VITE_EXTERNAL_API_URL as string | undefined) ?? '/manga/external'
const PAGE_SIZE = 20

function defaultQueryFor(provider: SearchProvider): string {
  return SEARCH_PROVIDERS.find((option) => option.key === provider)?.defaultQuery ?? ''
}

function loadProvider(): SearchProvider {
  try {
    const stored = localStorage.getItem(STORAGE_KEY)
    if (SEARCH_PROVIDERS.some((option) => option.key === stored)) {
      return stored as SearchProvider
    }
  } catch {
    // localStorage may be unavailable (private mode) — fall back to the default.
  }
  return DEFAULT_PROVIDER
}

function persistProvider(provider: SearchProvider): void {
  try {
    localStorage.setItem(STORAGE_KEY, provider)
  } catch {
    // Ignore persistence failures — the choice still applies for this session.
  }
}

export function useExternalSearch() {
  const ui = useUiStore()
  const provider = ref<SearchProvider>(loadProvider())
  // Seed the field with the provider's default keyword so it stays visible and editable.
  const query = ref(defaultQueryFor(provider.value))
  const results = ref<ExternalMangaResult[]>([])
  const isLoading = ref(false)
  const isLoadingMore = ref(false)
  const hasMore = ref(false)
  const error = ref<string | null>(null)

  let currentPage = 1
  let lastQuery = ''
  let debounceTimer: ReturnType<typeof setTimeout> | null = null

  async function search(searchTerm: string): Promise<void> {
    const trimmed = searchTerm.trim()
    if (trimmed.length < 2) {
      results.value = []
      hasMore.value = false
      return
    }

    lastQuery = trimmed
    currentPage = 1
    isLoading.value = true
    error.value = null
    results.value = []

    try {
      const response = await client.get<ExternalMangaResult[]>(EXTERNAL_API_URL, {
        params: { q: trimmed, type: 'manga', page: 1, provider: provider.value },
      })
      results.value = response.data
      hasMore.value = response.data.length >= PAGE_SIZE
    } catch {
      error.value = 'Recherche indisponible'
      hasMore.value = false
      ui.addToast('Erreur lors de la recherche — réessayez', 'error')
    } finally {
      isLoading.value = false
    }
  }

  async function loadMore(): Promise<void> {
    if (!hasMore.value || isLoadingMore.value || !lastQuery) return
    const nextPage = currentPage + 1
    isLoadingMore.value = true
    try {
      const response = await client.get<ExternalMangaResult[]>(EXTERNAL_API_URL, {
        params: { q: lastQuery, type: 'manga', page: nextPage, provider: provider.value },
      })
      if (response.data.length > 0) {
        currentPage = nextPage
        results.value = [...results.value, ...response.data]
        hasMore.value = response.data.length >= PAGE_SIZE
      } else {
        hasMore.value = false
      }
    } catch {
      ui.addToast('Erreur lors du chargement — réessayez', 'error')
    } finally {
      isLoadingMore.value = false
    }
  }

  watch(query, (newValue) => {
    if (debounceTimer) clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => search(newValue), 400)
  })

  // Switching provider persists the choice and either re-seeds the untouched
  // default or re-runs the current search against the newly selected provider.
  watch(provider, (next, previous) => {
    persistProvider(next)
    const current = query.value.trim()
    if (current === '' || current === defaultQueryFor(previous)) {
      query.value = defaultQueryFor(next)
    } else {
      search(query.value)
    }
  })

  function clear(): void {
    query.value = ''
    results.value = []
    error.value = null
    isLoading.value = false
    isLoadingMore.value = false
    hasMore.value = false
    currentPage = 1
    lastQuery = ''
    if (debounceTimer) clearTimeout(debounceTimer)
  }

  return {
    provider,
    providers: SEARCH_PROVIDERS,
    query,
    results,
    isLoading,
    isLoadingMore,
    hasMore,
    loadMore,
    error,
    search,
    clear,
  }
}
