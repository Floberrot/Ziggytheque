import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref } from 'vue'
import { useIsbnCoverSearch } from '../useIsbnCoverSearch'

vi.mock('@/api/manga', () => ({
  coverByIsbn: vi.fn(),
}))

import { coverByIsbn } from '@/api/manga'

const mockCoverByIsbn = vi.mocked(coverByIsbn)

const mockCovers = [
  { coverUrl: 'https://img.example.com/bnf.jpg', spineUrl: null, isbn: '9782811645632', source: 'bnf' },
  { coverUrl: 'https://img.example.com/google.jpg', spineUrl: null, isbn: '9782811645632', source: 'google_books' },
]

describe('useIsbnCoverSearch', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('calls coverByIsbn and fills covers on search()', async () => {
    mockCoverByIsbn.mockResolvedValueOnce(mockCovers)

    const isbn = ref('9782811645632')
    const { covers, isLoading, search } = useIsbnCoverSearch(isbn)

    expect(covers.value).toEqual([])

    const searchPromise = search()
    expect(isLoading.value).toBe(true)

    await searchPromise

    expect(mockCoverByIsbn).toHaveBeenCalledWith('9782811645632')
    expect(covers.value).toEqual(mockCovers)
    expect(isLoading.value).toBe(false)
  })

  it('does not call coverByIsbn when isbn is empty', async () => {
    const isbn = ref('')
    const { search } = useIsbnCoverSearch(isbn)

    await search()

    expect(mockCoverByIsbn).not.toHaveBeenCalled()
  })

  it('fills error and resets isLoading on API rejection', async () => {
    mockCoverByIsbn.mockRejectedValueOnce(new Error('Network error'))

    const isbn = ref('9782811645632')
    const { covers, isLoading, error, search } = useIsbnCoverSearch(isbn)

    await search()

    expect(covers.value).toEqual([])
    expect(isLoading.value).toBe(false)
    expect(error.value).toBeTruthy()
  })

  it('returns empty covers when no source has a cover', async () => {
    mockCoverByIsbn.mockResolvedValueOnce([])

    const isbn = ref('9782723492607')
    const { covers, search } = useIsbnCoverSearch(isbn)

    await search()

    expect(covers.value).toEqual([])
  })
})
