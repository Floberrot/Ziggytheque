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

const EXTERNAL_API_URL = (import.meta.env.VITE_EXTERNAL_API_URL as string | undefined) ?? '/manga/external'
const PAGE_SIZE = 20

export function useExternalSearch() {
  const ui = useUiStore()
  const query = ref('')
  const results = ref<ExternalMangaResult[]>([])
  const isLoading = ref(false)
  const isLoadingMore = ref(false)
  const hasMore = ref(false)
  const error = ref<string | null>(null)

  let currentPage = 1
  let lastQuery = ''
  let debounceTimer: ReturnType<typeof setTimeout> | null = null

  async function search(q: string): Promise<void> {
    const trimmed = q.trim()
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
      const res = await client.get<ExternalMangaResult[]>(EXTERNAL_API_URL, {
        params: { q: trimmed, type: 'manga', page: 1 },
      })
      results.value = res.data
      hasMore.value = res.data.length >= PAGE_SIZE
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
      const res = await client.get<ExternalMangaResult[]>(EXTERNAL_API_URL, {
        params: { q: lastQuery, type: 'manga', page: nextPage },
      })
      if (res.data.length > 0) {
        currentPage = nextPage
        results.value = [...results.value, ...res.data]
        hasMore.value = res.data.length >= PAGE_SIZE
      } else {
        hasMore.value = false
      }
    } catch {
      ui.addToast('Erreur lors du chargement — réessayez', 'error')
    } finally {
      isLoadingMore.value = false
    }
  }

  watch(query, (newVal) => {
    if (debounceTimer) clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => search(newVal), 400)
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

  return { query, results, isLoading, isLoadingMore, hasMore, loadMore, error, search, clear }
}
