import { describe, it, expect, vi, beforeEach } from 'vitest'

vi.mock('@/api/manga', () => ({
  discoverEditions: vi.fn(),
  mangaEditions: vi.fn(),
}))

import { discoverEditions, mangaEditions } from '@/api/manga'
import type { ExternalEdition } from '@/api/manga'
import { useEditions } from '../useEditions'

const mockDiscover = vi.mocked(discoverEditions)
const mockMangaEditions = vi.mocked(mangaEditions)

const EDITION_FR: ExternalEdition = {
  workTitle: 'Berserk',
  editionLabel: 'Glénat — Berserk',
  publisher: 'Glénat',
  language: 'fr',
  country: 'FR',
  format: 'broche',
  volumeCount: 40,
  isbnSample: '9782723425483',
  coverUrl: null,
  source: 'bnf',
  externalId: null,
  editionLine: null,
}

const EDITION_US: ExternalEdition = {
  workTitle: 'Berserk',
  editionLabel: 'Dark Horse — Berserk',
  publisher: 'Dark Horse',
  language: 'en',
  country: 'US',
  format: 'broche',
  volumeCount: 40,
  isbnSample: null,
  coverUrl: null,
  source: 'open_library',
  externalId: null,
  editionLine: null,
}

describe('useEditions', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('starts with empty editions and no loading', () => {
    const { editions, isLoading, error } = useEditions()
    expect(editions.value).toEqual([])
    expect(isLoading.value).toBe(false)
    expect(error.value).toBeNull()
  })

  it('discover() calls discoverEditions and fills editions', async () => {
    mockDiscover.mockResolvedValueOnce([EDITION_FR, EDITION_US])
    const { editions, discover } = useEditions()

    await discover('Berserk')

    expect(mockDiscover).toHaveBeenCalledWith({ q: 'Berserk', author: undefined, language: undefined })
    expect(editions.value).toHaveLength(2)
  })

  it('discover() does nothing for empty query', async () => {
    const { discover } = useEditions()

    await discover('')

    expect(mockDiscover).not.toHaveBeenCalled()
  })

  it('discover() sets error and clears editions on failure', async () => {
    mockDiscover.mockRejectedValueOnce(new Error('Network error'))
    const { editions, error, discover } = useEditions()

    await discover('Berserk')

    expect(editions.value).toEqual([])
    expect(error.value).toBeTruthy()
  })

  it('loadForManga() calls mangaEditions', async () => {
    mockMangaEditions.mockResolvedValueOnce([EDITION_FR])
    const { editions, loadForManga } = useEditions()

    await loadForManga('manga-123')

    expect(mockMangaEditions).toHaveBeenCalledWith('manga-123')
    expect(editions.value).toHaveLength(1)
  })

  it('groupedByCountry groups editions by country', async () => {
    mockDiscover.mockResolvedValueOnce([EDITION_FR, EDITION_US])
    const { groupedByCountry, discover } = useEditions()

    await discover('Berserk')

    expect(groupedByCountry.value).toHaveLength(2)
    expect(groupedByCountry.value[0].country).toBe('FR')
    expect(groupedByCountry.value[1].country).toBe('US')
  })
})
