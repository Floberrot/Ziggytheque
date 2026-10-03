import { computed, onScopeDispose, ref, toValue, watch, type MaybeRefOrGetter } from 'vue'
import { searchVolumeExternal, type VolumeSearchResult } from '@/api/manga'
import { useCoverProvider } from '@/composables/useCoverProvider'

/** The API pages its suggestions by 20: a full page means there may be more. */
const PAGE_SIZE = 20
const DEBOUNCE_MS = 500

/**
 * Cover suggestions for a tome, searched by title (a pure lookup — nothing is saved).
 * The query is debounced, re-run when the cover source changes, and paged on demand
 * (`loadMore`, called by the scrolling list). The volume number and the edition are
 * sent as their own parameters: the query only carries the series title.
 */
export function useCoverTitleSearch(options: {
  volumeNumber: MaybeRefOrGetter<number | null>
  edition: MaybeRefOrGetter<string | null>
  /** A failed search (not a "no result" one): the caller tells the user. */
  onError?: () => void
}) {
  const query = ref('')
  const results = ref<VolumeSearchResult[]>([])
  const isSearching = ref(false)
  const isLoadingMore = ref(false)
  const hasMore = ref(false)
  let currentPage = 1
  let lastQuery = ''
  let debounceTimer: ReturnType<typeof setTimeout> | null = null

  const { provider, providers } = useCoverProvider()
  const providerLabel = computed(
    () => providers.find((option) => option.key === provider.value)?.label ?? 'Auto',
  )

  async function runSearch(rawQuery: string): Promise<void> {
    if (rawQuery.trim().length < 2) {
      results.value = []
      hasMore.value = false
      return
    }
    lastQuery = rawQuery.trim()
    currentPage = 1
    isSearching.value = true
    try {
      const page = await searchVolumeExternal(
        lastQuery,
        1,
        toValue(options.volumeNumber),
        toValue(options.edition),
        provider.value,
      )
      results.value = page
      hasMore.value = page.length >= PAGE_SIZE
    } catch {
      results.value = []
      hasMore.value = false
      options.onError?.()
    } finally {
      isSearching.value = false
    }
  }

  async function loadMore(): Promise<void> {
    if (!hasMore.value || isLoadingMore.value || !lastQuery) return
    isLoadingMore.value = true
    try {
      const page = await searchVolumeExternal(
        lastQuery,
        currentPage + 1,
        toValue(options.volumeNumber),
        toValue(options.edition),
        provider.value,
      )
      if (page.length > 0) {
        currentPage++
        results.value = [...results.value, ...page]
        hasMore.value = page.length >= PAGE_SIZE
      } else {
        hasMore.value = false
      }
    } catch {
      // A page that fails to load only ends the list; the suggestions shown stay.
    } finally {
      isLoadingMore.value = false
    }
  }

  /** Forgets the query and its results, so another tome never shows this one's. */
  function reset(): void {
    if (debounceTimer) clearTimeout(debounceTimer)
    debounceTimer = null
    results.value = []
    query.value = ''
    hasMore.value = false
    currentPage = 1
    lastQuery = ''
  }

  watch(query, (value) => {
    if (debounceTimer) clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => runSearch(value), DEBOUNCE_MS)
  })

  // Another cover source: run the current search again against it.
  watch(provider, () => {
    if (query.value.trim().length >= 2) runSearch(query.value)
  })

  onScopeDispose(() => {
    if (debounceTimer) clearTimeout(debounceTimer)
  })

  return {
    query,
    results,
    isSearching,
    isLoadingMore,
    hasMore,
    provider,
    providers,
    providerLabel,
    runSearch,
    loadMore,
    reset,
  }
}
