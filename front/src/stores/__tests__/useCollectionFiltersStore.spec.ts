import { describe, it, expect, beforeEach } from 'vitest'
import { nextTick } from 'vue'
import { createPinia, setActivePinia } from 'pinia'
import { useCollectionFiltersStore } from '../useCollectionFiltersStore'

const STORAGE_KEY = 'collection-filters'

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
  })

  it('persists search and filter changes to sessionStorage', async () => {
    const store = useCollectionFiltersStore()

    store.searchInput = 'naruto'
    store.filters.genre = 'shonen'
    store.filters.hasOwned = true
    await nextTick()

    const raw = sessionStorage.getItem(STORAGE_KEY)
    expect(raw).not.toBeNull()
    const parsed = JSON.parse(raw!)
    expect(parsed.searchInput).toBe('naruto')
    expect(parsed.filters.genre).toBe('shonen')
    expect(parsed.filters.hasOwned).toBe(true)
  })

  it('restores persisted filters on a fresh store instance', () => {
    sessionStorage.setItem(
      STORAGE_KEY,
      JSON.stringify({ searchInput: 'one piece', filters: { genre: 'seinen', hasRead: true } }),
    )
    setActivePinia(createPinia())

    const store = useCollectionFiltersStore()
    expect(store.searchInput).toBe('one piece')
    expect(store.filters.genre).toBe('seinen')
    expect(store.filters.hasRead).toBe(true)
    // search is reconciled from the raw input so the list matches what is typed
    expect(store.filters.search).toBe('one piece')
    // unspecified booleans fall back to their defaults
    expect(store.filters.hasOwned).toBe(false)
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

  it('falls back to defaults when the persisted payload is corrupt', () => {
    sessionStorage.setItem(STORAGE_KEY, 'not valid json {{{')
    setActivePinia(createPinia())

    const store = useCollectionFiltersStore()
    expect(store.searchInput).toBe('')
    expect(store.filters.genre).toBeUndefined()
  })
})
