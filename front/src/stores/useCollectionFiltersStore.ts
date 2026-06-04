import { defineStore } from 'pinia'
import { reactive, ref, watch } from 'vue'
import type { CollectionFilters } from '@/api/collection'

const STORAGE_KEY = 'collection-filters'

interface PersistedCollectionFilters {
  searchInput: string
  filters: CollectionFilters
}

function createDefaultFilters(): CollectionFilters {
  return {
    search: undefined,
    genre: undefined,
    edition: undefined,
    readingStatus: undefined,
    sort: undefined,
    followed: false,
    hasOwned: false,
    hasRead: false,
    hasWished: false,
  }
}

function loadPersisted(): PersistedCollectionFilters | null {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY)
    return raw ? (JSON.parse(raw) as PersistedCollectionFilters) : null
  } catch {
    return null
  }
}

/**
 * Holds the collection page's filter state outside the component so it survives
 * navigating away and back (e.g. opening a series, then returning to the list).
 * Backed by sessionStorage: filters also survive a reload but reset when the tab
 * is closed — matching the auth token's session scope.
 */
export const useCollectionFiltersStore = defineStore('collectionFilters', () => {
  const persisted = loadPersisted()

  const searchInput = ref(persisted?.searchInput ?? '')
  const filters = reactive<CollectionFilters>({ ...createDefaultFilters(), ...persisted?.filters })

  // The raw search box is the source of truth for the search term; keep the
  // (debounced) query value aligned with it on restore so the list and the
  // input never disagree after a reload mid-typing.
  filters.search = searchInput.value.trim() || undefined

  watch(
    () => ({ searchInput: searchInput.value, filters: { ...filters } }),
    (snapshot) => {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(snapshot))
    },
  )

  function reset(): void {
    searchInput.value = ''
    Object.assign(filters, createDefaultFilters())
  }

  return { searchInput, filters, reset }
})
