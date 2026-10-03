import { defineStore } from 'pinia'
import { reactive, ref, watch } from 'vue'
import type { CollectionFilters } from '@/api/collection'

const STORAGE_KEY = 'collection-filters'

/** List pages that put the reader back at the scroll offset they left them at. */
export const REMEMBERED_LISTS = ['collection', 'wishlist'] as const
export type RememberedList = (typeof REMEMBERED_LISTS)[number]

type CollectionSort = NonNullable<CollectionFilters['sort']>
const SORTS: readonly CollectionSort[] = ['added_desc', 'rating_asc', 'rating_desc']

/** What is kept in sessionStorage — undefined values are simply left out of the JSON. */
interface ListState {
  /** Collection: the raw search box (the query uses its debounced copy, filters.search). */
  searchInput: string
  filters: CollectionFilters
  /** Collection: the "refine" panel (genre, sort, edition) is open. */
  advancedOpen: boolean
  wishlistSearchInput: string
  /** News: the followed series the articles are filtered on, and the page shown. */
  notificationsSeriesId: string | undefined
  notificationsPage: number
  scroll: Partial<Record<RememberedList, number>>
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

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function readText(value: unknown): string {
  return typeof value === 'string' ? value : ''
}

/** An empty text is no filter: kept as undefined so the query and the JSON stay clean. */
function optionalText(value: unknown): string | undefined {
  return typeof value === 'string' && value !== '' ? value : undefined
}

function readOffset(value: unknown): number | undefined {
  if (typeof value !== 'number' || !Number.isFinite(value) || value <= 0) return undefined
  return Math.round(value)
}

function readFilters(value: unknown): CollectionFilters {
  if (!isRecord(value)) return createDefaultFilters()
  return {
    search: optionalText(value.search),
    genre: optionalText(value.genre),
    edition: optionalText(value.edition),
    readingStatus: optionalText(value.readingStatus),
    sort: SORTS.find((sort) => sort === value.sort),
    followed: value.followed === true,
    hasOwned: value.hasOwned === true,
    hasRead: value.hasRead === true,
    hasWished: value.hasWished === true,
  }
}

function readScroll(value: unknown): Partial<Record<RememberedList, number>> {
  const scroll: Partial<Record<RememberedList, number>> = {}
  if (!isRecord(value)) return scroll
  for (const list of REMEMBERED_LISTS) {
    const offset = readOffset(value[list])
    if (offset !== undefined) scroll[list] = offset
  }
  return scroll
}

/** Whatever sessionStorage holds (an older shape, a corrupt payload) is read field by field. */
function loadPersisted(): Record<string, unknown> {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY)
    const parsed: unknown = raw ? JSON.parse(raw) : null
    return isRecord(parsed) ? parsed : {}
  } catch {
    return {}
  }
}

function serialize(state: ListState): string {
  return JSON.stringify({
    searchInput: state.searchInput,
    filters: readFilters(state.filters),
    advancedOpen: state.advancedOpen,
    wishlistSearchInput: state.wishlistSearchInput,
    notificationsSeriesId: optionalText(state.notificationsSeriesId),
    notificationsPage: state.notificationsPage,
    scroll: readScroll(state.scroll),
  })
}

const DEFAULT_STATE_JSON = serialize({
  searchInput: '',
  filters: createDefaultFilters(),
  advancedOpen: false,
  wishlistSearchInput: '',
  notificationsSeriesId: undefined,
  notificationsPage: 1,
  scroll: {},
})

/**
 * Remembers what the reader set on the list pages, so leaving one (opening a series,
 * another tab of the app) and coming back finds it as it was:
 * - collection: search, every filter and sort, the refine panel, the scroll offset;
 * - wishlist: search and scroll offset;
 * - news: the followed series filtered on and the page.
 *
 * Backed by sessionStorage — it survives a reload but not the tab, like the auth token —
 * and cleared on logout (useAuthStore), so the next account starts afresh.
 */
export const useCollectionFiltersStore = defineStore('collectionFilters', () => {
  const persisted = loadPersisted()

  const searchInput = ref(readText(persisted.searchInput))
  const filters = reactive<CollectionFilters>(readFilters(persisted.filters))
  const advancedOpen = ref(persisted.advancedOpen === true)

  const wishlistSearchInput = ref(readText(persisted.wishlistSearchInput))

  const notificationsSeriesId = ref<string | undefined>(
    optionalText(persisted.notificationsSeriesId),
  )
  const restoredPage = persisted.notificationsPage
  const notificationsPage = ref(
    typeof restoredPage === 'number' && Number.isInteger(restoredPage) && restoredPage > 1
      ? restoredPage
      : 1,
  )

  const scrollPositions = reactive<Partial<Record<RememberedList, number>>>(
    readScroll(persisted.scroll),
  )

  /** The search box is the source of truth for the search term: the query follows it. */
  function commitSearch(): void {
    filters.search = searchInput.value.trim() || undefined
  }

  // Restored mid-typing (reload during the debounce): the list matches what the box shows.
  commitSearch()

  function persist(json: string): void {
    try {
      if (json === DEFAULT_STATE_JSON) sessionStorage.removeItem(STORAGE_KEY)
      else sessionStorage.setItem(STORAGE_KEY, json)
    } catch {
      // Storage unavailable (private mode, quota): the state still lives for this visit.
    }
  }

  watch(
    () =>
      serialize({
        searchInput: searchInput.value,
        filters,
        advancedOpen: advancedOpen.value,
        wishlistSearchInput: wishlistSearchInput.value,
        notificationsSeriesId: notificationsSeriesId.value,
        notificationsPage: notificationsPage.value,
        scroll: scrollPositions,
      }),
    persist,
  )

  /** Collection "reset" button: search and every filter back to none. */
  function reset(): void {
    searchInput.value = ''
    Object.assign(filters, createDefaultFilters())
  }

  function saveScroll(list: RememberedList, offset: number): void {
    const top = readOffset(offset)
    if (top === undefined) delete scrollPositions[list]
    else scrollPositions[list] = top
  }

  /** Logout: forget everything, storage included, at once (a reload may follow right away). */
  function clear(): void {
    reset()
    advancedOpen.value = false
    wishlistSearchInput.value = ''
    notificationsSeriesId.value = undefined
    notificationsPage.value = 1
    for (const list of REMEMBERED_LISTS) delete scrollPositions[list]
    persist(DEFAULT_STATE_JSON)
  }

  return {
    searchInput,
    filters,
    advancedOpen,
    wishlistSearchInput,
    notificationsSeriesId,
    notificationsPage,
    scrollPositions,
    commitSearch,
    reset,
    saveScroll,
    clear,
  }
})
