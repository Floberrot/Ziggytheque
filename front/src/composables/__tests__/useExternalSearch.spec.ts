import { describe, it, expect, vi, beforeEach } from 'vitest'
import { nextTick } from 'vue'

vi.mock('@/api/client', () => ({
  default: { get: vi.fn() },
}))

vi.mock('@/stores/useUiStore', () => ({
  useUiStore: () => ({ addToast: vi.fn() }),
}))

import client from '@/api/client'
import { useExternalSearch } from '../useExternalSearch'

const mockGet = vi.mocked(client.get)

describe('useExternalSearch', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.clear()
    mockGet.mockResolvedValue({ data: [] } as never)
  })

  it('defaults to jikan and seeds the field with its (empty) default query', () => {
    const { provider, query } = useExternalSearch()

    expect(provider.value).toBe('jikan')
    expect(query.value).toBe('')
  })

  it('restores the persisted provider and seeds its default keyword', () => {
    localStorage.setItem('ziggytheque.searchProvider', 'googlebooks')

    const { provider, query } = useExternalSearch()

    expect(provider.value).toBe('googlebooks')
    expect(query.value).toBe('manga')
  })

  it('re-seeds the untouched field when switching provider and persists the choice', async () => {
    const { provider, query } = useExternalSearch()

    provider.value = 'googlebooks'
    await nextTick()

    expect(query.value).toBe('manga')
    expect(localStorage.getItem('ziggytheque.searchProvider')).toBe('googlebooks')
  })

  it('sends the selected provider to the API on search()', async () => {
    const { search } = useExternalSearch()

    await search('one piece')

    expect(mockGet).toHaveBeenCalledWith('/manga/external', {
      params: { q: 'one piece', type: 'manga', page: 1, provider: 'jikan' },
    })
  })

  it('keeps a custom query and re-runs the search against the new provider', async () => {
    const { provider, query } = useExternalSearch()

    query.value = 'naruto'
    provider.value = 'googlebooks'
    await nextTick()

    expect(query.value).toBe('naruto')
    expect(mockGet).toHaveBeenCalledWith('/manga/external', {
      params: { q: 'naruto', type: 'manga', page: 1, provider: 'googlebooks' },
    })
  })

  it('does not query the API for terms shorter than two characters', async () => {
    const { search } = useExternalSearch()

    await search('a')

    expect(mockGet).not.toHaveBeenCalled()
  })
})
