import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { effectScope, nextTick, ref } from 'vue'
import type { VolumeSearchResult } from '@/api/manga'

vi.mock('@/api/manga', () => ({
  searchVolumeExternal: vi.fn(),
}))

import { searchVolumeExternal } from '@/api/manga'
import { useCoverTitleSearch } from '../useCoverTitleSearch'

const mockSearch = vi.mocked(searchVolumeExternal)

/** Lets the awaited (mocked) API calls resolve — flushPromises would wait on faked timers. */
async function settle(): Promise<void> {
  for (let round = 0; round < 5; round++) await Promise.resolve()
}

function results(count: number, prefix = 'r'): VolumeSearchResult[] {
  return Array.from({ length: count }, (_, index) => ({
    externalId: `${prefix}-${index}`,
    title: 'Berserk',
    edition: null,
    coverUrl: `https://covers.test/${prefix}-${index}.jpg`,
    isbn: null,
    language: 'fr',
    totalVolumes: null,
    source: 'mangadex',
  }))
}

describe('useCoverTitleSearch', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.useFakeTimers()
    localStorage.clear()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  function setup(onError = vi.fn()) {
    const scope = effectScope()
    const volumeNumber = ref<number | null>(3)
    const search = scope.run(() => useCoverTitleSearch({ volumeNumber, edition: () => 'Glénat', onError }))!
    return { scope, search, onError }
  }

  it('waits for the typing to pause, then searches with the tome and edition as their own params', async () => {
    mockSearch.mockResolvedValueOnce(results(2))
    const { scope, search } = setup()

    search.query.value = 'Berserk'
    await nextTick()
    expect(mockSearch).not.toHaveBeenCalled()

    await vi.advanceTimersByTimeAsync(500)
    await settle()

    expect(mockSearch).toHaveBeenCalledWith('Berserk', 1, 3, 'Glénat', 'composite')
    expect(search.results.value).toHaveLength(2)
    expect(search.hasMore.value).toBe(false)
    scope.stop()
  })

  it('does not search under two characters', async () => {
    const { scope, search } = setup()

    await search.runSearch('B')

    expect(mockSearch).not.toHaveBeenCalled()
    expect(search.results.value).toEqual([])
    scope.stop()
  })

  it('pages on demand while a page comes back full', async () => {
    mockSearch.mockResolvedValueOnce(results(20, 'first')).mockResolvedValueOnce(results(3, 'second'))
    const { scope, search } = setup()

    await search.runSearch('Berserk')
    expect(search.hasMore.value).toBe(true)

    await search.loadMore()

    expect(mockSearch).toHaveBeenLastCalledWith('Berserk', 2, 3, 'Glénat', 'composite')
    expect(search.results.value).toHaveLength(23)
    expect(search.hasMore.value).toBe(false)
    scope.stop()
  })

  it('searches again when the cover source changes', async () => {
    mockSearch.mockResolvedValue(results(1))
    const { scope, search } = setup()
    await search.runSearch('Berserk')
    search.query.value = 'Berserk'
    await nextTick()
    await vi.advanceTimersByTimeAsync(500)
    await settle()
    mockSearch.mockClear()

    search.provider.value = 'mangadex'
    await nextTick()

    expect(mockSearch).toHaveBeenCalledWith('Berserk', 1, 3, 'Glénat', 'mangadex')
    expect(search.providerLabel.value).toBe('MangaDex')
    scope.stop()
  })

  it('reports a failed search and empties the results', async () => {
    mockSearch.mockRejectedValueOnce(new Error('503'))
    const { scope, search, onError } = setup()

    await search.runSearch('Berserk')

    expect(onError).toHaveBeenCalledOnce()
    expect(search.results.value).toEqual([])
    scope.stop()
  })

  it('forgets the query and its results on reset', async () => {
    mockSearch.mockResolvedValueOnce(results(20))
    const { scope, search } = setup()
    await search.runSearch('Berserk')

    search.reset()

    expect(search.results.value).toEqual([])
    expect(search.query.value).toBe('')
    expect(search.hasMore.value).toBe(false)
    await search.loadMore()
    expect(mockSearch).toHaveBeenCalledOnce()
    scope.stop()
  })
})
