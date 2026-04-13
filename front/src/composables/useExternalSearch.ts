import { ref, watch } from 'vue'
import client from '@/api/client'

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

/**
 * Debounced external manga search — calls GET /api/manga/external?q=...
 * The Symfony backend proxies to Google Books and normalises the response.
 *
 * Override the endpoint via VITE_EXTERNAL_API_URL if needed.
 */
const EXTERNAL_API_URL = (import.meta.env.VITE_EXTERNAL_API_URL as string | undefined) ?? '/manga/external'

export function useExternalSearch() {
  const query = ref('')
  const results = ref<ExternalMangaResult[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  let debounceTimer: ReturnType<typeof setTimeout> | null = null

  async function search(q: string): Promise<void> {
    if (q.trim().length < 2) {
      results.value = []
      return
    }

    isLoading.value = true
    error.value = null

    try {
      const res = await client.get<ExternalMangaResult[]>(EXTERNAL_API_URL, {
        params: { q: q.trim() },
      })
      results.value = res.data
    } catch {
      error.value = 'Search unavailable'
      results.value = []
    } finally {
      isLoading.value = false
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
    if (debounceTimer) clearTimeout(debounceTimer)
  }

  return { query, results, isLoading, error, clear }
}
