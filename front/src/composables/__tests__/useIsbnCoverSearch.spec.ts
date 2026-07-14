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

  it('normalizes the ISBN before calling the API (hyphens, ISBN-10, EAN add-on)', async () => {
    mockCoverByIsbn.mockResolvedValue(mockCovers)

    const isbn = ref('978-2-8116-4563-2')
    const { search } = useIsbnCoverSearch(isbn)
    await search()
    expect(mockCoverByIsbn).toHaveBeenLastCalledWith('9782811645632')

    // ISBN-10 converted to ISBN-13
    isbn.value = '2-8116-4563-2'
    await search()
    expect(mockCoverByIsbn).toHaveBeenLastCalledWith('9782811645632')

    // EAN-5 barcode add-on stripped
    isbn.value = '978281164563212345'
    await search()
    expect(mockCoverByIsbn).toHaveBeenLastCalledWith('9782811645632')
  })

  it('flags an invalid ISBN without calling the API', async () => {
    const isbn = ref('not-an-isbn')
    const { covers, error, search } = useIsbnCoverSearch(isbn)

    await search()

    expect(mockCoverByIsbn).not.toHaveBeenCalled()
    expect(covers.value).toEqual([])
    expect(error.value).toBe('enrich.isbnInvalid')
  })

  it('does not call coverByIsbn when isbn is empty', async () => {
    const isbn = ref('')
    const { search } = useIsbnCoverSearch(isbn)

    await search()

    expect(mockCoverByIsbn).not.toHaveBeenCalled()
  })

  it('fills a backend-error key and resets isLoading on API rejection', async () => {
    mockCoverByIsbn.mockRejectedValueOnce(new Error('Network error'))

    const isbn = ref('9782811645632')
    const { covers, isLoading, error, search } = useIsbnCoverSearch(isbn)

    await search()

    expect(covers.value).toEqual([])
    expect(isLoading.value).toBe(false)
    expect(error.value).toBe('enrich.isbnSearchError')
  })

  it('distinguishes "0 result" (empty covers, no error) from a backend error', async () => {
    mockCoverByIsbn.mockResolvedValueOnce([])

    const isbn = ref('9782723492607')
    const { covers, error, search } = useIsbnCoverSearch(isbn)

    await search()

    expect(covers.value).toEqual([])
    expect(error.value).toBeNull()
  })

  it('clears a previous error on the next search', async () => {
    const isbn = ref('invalid')
    const { error, search } = useIsbnCoverSearch(isbn)

    await search()
    expect(error.value).toBe('enrich.isbnInvalid')

    mockCoverByIsbn.mockResolvedValueOnce(mockCovers)
    isbn.value = '9782811645632'
    await search()
    expect(error.value).toBeNull()
  })
})
