import { describe, it, expect, beforeEach } from 'vitest'
import { nextTick } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import { useCollectionFiltersStore } from '../useCollectionFiltersStore'
import { useAuthStore } from '../useAuthStore'

const STORAGE_KEY = 'collection-filters'

function persisted(): Record<string, unknown> | null {
  const raw = sessionStorage.getItem(STORAGE_KEY)
  return raw === null ? null : (JSON.parse(raw) as Record<string, unknown>)
}

/** A new page load: a fresh Pinia reads sessionStorage again. */
function reloadStore() {
  setActivePinia(createPinia())
  return useCollectionFiltersStore()
}

describe('useCollectionFiltersStore', () => {
  beforeEach(() => {
    sessionStorage.clear()
    setActivePinia(createPinia())
  })

  it('starts with empty defaults when nothing is persisted', () => {
    const store = useCollectionFiltersStore()

    expect(store.searchInput).toBe('')
    expect(store.filters.genre).toBeUndefined()
    expect(store.filters.search).toBeUndefined()
    expect(store.filters.followed).toBe(false)
    expect(store.filters.hasOwned).toBe(false)
    expect(store.filters.hasRead).toBe(false)
    expect(store.filters.hasWished).toBe(false)
    expect(store.advancedOpen).toBe(false)
    expect(store.wishlistSearchInput).toBe('')
    expect(store.notificationsSeriesId).toBeUndefined()
    expect(store.notificationsPage).toBe(1)
    expect(store.scrollPositions).toEqual({})
  })

  it('persists search and filter changes to sessionStorage', async () => {
    const store = useCollectionFiltersStore()

    store.searchInput = 'naruto'
    store.filters.genre = 'shonen'
    store.filters.hasOwned = true
    await nextTick()

    const saved = persisted()
    expect(saved).not.toBeNull()
    expect(saved!.searchInput).toBe('naruto')
    expect((saved!.filters as Record<string, unknown>).genre).toBe('shonen')
    expect((saved!.filters as Record<string, unknown>).hasOwned).toBe(true)
  })

  it('restores persisted filters on a fresh store instance', () => {
    sessionStorage.setItem(
      STORAGE_KEY,
      JSON.stringify({ searchInput: 'one piece', filters: { genre: 'seinen', hasRead: true } }),
    )

    const store = reloadStore()

    expect(store.searchInput).toBe('one piece')
    expect(store.filters.genre).toBe('seinen')
    expect(store.filters.hasRead).toBe(true)
    // search is reconciled from the raw input so the list matches what is typed
    expect(store.filters.search).toBe('one piece')
    // unspecified booleans fall back to their defaults
    expect(store.filters.hasOwned).toBe(false)
  })

  it('remembers the refine panel, the wishlist search, the news filter and the scroll offsets', async () => {
    const store = useCollectionFiltersStore()
    store.advancedOpen = true
    store.filters.sort = 'rating_desc'
    store.wishlistSearchInput = 'berserk'
    store.notificationsSeriesId = 'entry-42'
    store.notificationsPage = 3
    store.saveScroll('collection', 1840.6)
    store.saveScroll('wishlist', 320)
    await nextTick()

    const restored = reloadStore()

    expect(restored.advancedOpen).toBe(true)
    expect(restored.filters.sort).toBe('rating_desc')
    expect(restored.wishlistSearchInput).toBe('berserk')
    expect(restored.notificationsSeriesId).toBe('entry-42')
    expect(restored.notificationsPage).toBe(3)
    expect(restored.scrollPositions).toEqual({ collection: 1841, wishlist: 320 })
  })

  it('commitSearch() aligns the queried term with the search box', () => {
    const store = useCollectionFiltersStore()

    store.searchInput = '  vagabond '
    store.commitSearch()
    expect(store.filters.search).toBe('vagabond')

    store.searchInput = '   '
    store.commitSearch()
    expect(store.filters.search).toBeUndefined()
  })

  it('forgets a scroll offset back at the top', () => {
    const store = useCollectionFiltersStore()
    store.saveScroll('collection', 900)

    store.saveScroll('collection', 0)

    expect(store.scrollPositions.collection).toBeUndefined()
  })

  it('reset() clears search and every filter back to defaults', () => {
    const store = useCollectionFiltersStore()
    store.searchInput = 'bleach'
    store.filters.genre = 'shojo'
    store.filters.followed = true
    store.filters.hasWished = true

    store.reset()

    expect(store.searchInput).toBe('')
    expect(store.filters.genre).toBeUndefined()
    expect(store.filters.followed).toBe(false)
    expect(store.filters.hasWished).toBe(false)
  })

  it('leaves nothing in sessionStorage once everything is back to its default', async () => {
    const store = useCollectionFiltersStore()
    store.filters.genre = 'seinen'
    await nextTick()
    expect(persisted()).not.toBeNull()

    store.filters.genre = undefined
    await nextTick()

    expect(persisted()).toBeNull()
  })

  it('clear() forgets everything, sessionStorage included, at once', () => {
    sessionStorage.setItem(
      STORAGE_KEY,
      JSON.stringify({
        searchInput: 'one piece',
        filters: { genre: 'seinen' },
        advancedOpen: true,
        wishlistSearchInput: 'berserk',
        notificationsSeriesId: 'entry-42',
        notificationsPage: 2,
        scroll: { collection: 1200, wishlist: 80 },
      }),
    )
    const store = reloadStore()

    store.clear()

    expect(persisted()).toBeNull()
    expect(store.searchInput).toBe('')
    expect(store.filters.genre).toBeUndefined()
    expect(store.filters.search).toBeUndefined()
    expect(store.advancedOpen).toBe(false)
    expect(store.wishlistSearchInput).toBe('')
    expect(store.notificationsSeriesId).toBeUndefined()
    expect(store.notificationsPage).toBe(1)
    expect(store.scrollPositions).toEqual({})
  })

  it('is cleared when the user logs out', async () => {
    const store = useCollectionFiltersStore()
    store.searchInput = 'naruto'
    store.saveScroll('collection', 600)
    await nextTick()

    useAuthStore().logout()

    expect(persisted()).toBeNull()
    expect(store.searchInput).toBe('')
    expect(store.scrollPositions).toEqual({})
  })

  it('falls back to defaults when the persisted payload is corrupt', () => {
    sessionStorage.setItem(STORAGE_KEY, 'not valid json {{{')

    const store = reloadStore()

    expect(store.searchInput).toBe('')
    expect(store.filters.genre).toBeUndefined()
  })

  it('reads a malformed payload field by field, keeping only valid values', () => {
    sessionStorage.setItem(
      STORAGE_KEY,
      JSON.stringify({
        searchInput: 42,
        filters: { genre: 'josei', sort: 'by_price', followed: 'yes' },
        advancedOpen: 'true',
        notificationsPage: -3,
        scroll: { collection: 'far', wishlist: 250, dashboard: 90 },
      }),
    )

    const store = reloadStore()

    expect(store.searchInput).toBe('')
    expect(store.filters.genre).toBe('josei')
    expect(store.filters.sort).toBeUndefined()
    expect(store.filters.followed).toBe(false)
    expect(store.advancedOpen).toBe(false)
    expect(store.notificationsPage).toBe(1)
    expect(store.scrollPositions).toEqual({ wishlist: 250 })
  })
})
