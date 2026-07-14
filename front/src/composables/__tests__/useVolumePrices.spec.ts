import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref } from 'vue'

vi.mock('@/api/manga', () => ({
  getVolumePrices: vi.fn(),
}))

import { getVolumePrices } from '@/api/manga'
import type { PriceOffer, RetailerOffer } from '@/api/manga'
import { useVolumePrices } from '../useVolumePrices'

const mockGetVolumePrices = vi.mocked(getVolumePrices)

const OFFER: PriceOffer = {
  kind: 'merchant_live',
  merchant: 'eBay',
  merchantLogo: 'ebay',
  currency: 'EUR',
  amount: 6.99,
  url: 'https://rover.ebay.com/test',
  source: 'ebay',
}

const RETAILERS: RetailerOffer[] = [
  { retailer: 'amazon', label: 'Amazon', status: 'not_found', bestOffer: null },
  { retailer: 'fnac', label: 'Fnac', status: 'not_found', bestOffer: null },
  { retailer: 'ebay', label: 'eBay', status: 'found', bestOffer: OFFER },
]

describe('useVolumePrices', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('starts with empty offers and not loaded', () => {
    const { offers, retailers, hasIsbn, loaded, isLoading } = useVolumePrices('m1', 'v1')
    expect(offers.value).toEqual([])
    expect(retailers.value).toEqual([])
    expect(hasIsbn.value).toBe(false)
    expect(loaded.value).toBe(false)
    expect(isLoading.value).toBe(false)
  })

  it('load() fetches prices and sets offers and retailers', async () => {
    mockGetVolumePrices.mockResolvedValueOnce({
      offers: [OFFER],
      retailers: RETAILERS,
      hasIsbn: true,
      marketplace: 'EBAY_FR',
    })

    const { offers, retailers, hasIsbn, loaded, load } = useVolumePrices('m1', 'v1')
    await load()

    expect(mockGetVolumePrices).toHaveBeenCalledWith('m1', 'v1', undefined)
    expect(offers.value).toHaveLength(1)
    expect(retailers.value).toHaveLength(3)
    expect(retailers.value[2].status).toBe('found')
    expect(retailers.value[2].bestOffer).toEqual(OFFER)
    expect(hasIsbn.value).toBe(true)
    expect(loaded.value).toBe(true)
  })

  it('load() accepts a marketplace param', async () => {
    mockGetVolumePrices.mockResolvedValueOnce({
      offers: [],
      retailers: RETAILERS,
      hasIsbn: true,
      marketplace: 'EBAY_US',
    })

    const { load } = useVolumePrices('m1', 'v1')
    await load('EBAY_US')

    expect(mockGetVolumePrices).toHaveBeenCalledWith('m1', 'v1', 'EBAY_US')
  })

  it('load() accepts reactive refs as inputs', async () => {
    mockGetVolumePrices.mockResolvedValueOnce({
      offers: [OFFER],
      retailers: RETAILERS,
      hasIsbn: true,
      marketplace: 'EBAY_FR',
    })

    const mangaId = ref('manga-abc')
    const volumeId = ref('vol-xyz')
    const { offers, load } = useVolumePrices(mangaId, volumeId)
    await load()

    expect(mockGetVolumePrices).toHaveBeenCalledWith('manga-abc', 'vol-xyz', undefined)
    expect(offers.value).toHaveLength(1)
  })

  it('load() sets error and clears offers and retailers on failure', async () => {
    mockGetVolumePrices.mockRejectedValueOnce(new Error('Network error'))

    const { offers, retailers, error, loaded, load } = useVolumePrices('m1', 'v1')
    await load()

    expect(offers.value).toEqual([])
    expect(retailers.value).toEqual([])
    expect(error.value).toBeTruthy()
    expect(loaded.value).toBe(false)
  })

  it('load() does nothing when ids are empty', async () => {
    const { load } = useVolumePrices('', '')
    await load()

    expect(mockGetVolumePrices).not.toHaveBeenCalled()
  })
})
