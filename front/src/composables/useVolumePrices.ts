import { ref } from 'vue'
import type { MaybeRefOrGetter } from 'vue'
import { toValue } from 'vue'
import { getVolumePrices } from '@/api/manga'
import type { PriceOffer, RetailerOffer } from '@/api/manga'

export type { PriceOffer, RetailerOffer }

export function useVolumePrices(
  mangaId: MaybeRefOrGetter<string>,
  volumeId: MaybeRefOrGetter<string>,
) {
  const offers = ref<PriceOffer[]>([])
  const retailers = ref<RetailerOffer[]>([])
  const hasIsbn = ref(false)
  const marketplace = ref<string | null>(null)
  const isLoading = ref(false)
  const error = ref<string | null>(null)
  const loaded = ref(false)

  async function load(marketplaceParam?: string | null): Promise<void> {
    const mid = toValue(mangaId)
    const vid = toValue(volumeId)
    if (!mid || !vid) return
    isLoading.value = true
    error.value = null
    try {
      const result = await getVolumePrices(mid, vid, marketplaceParam)
      offers.value = result.offers
      retailers.value = result.retailers ?? []
      hasIsbn.value = result.hasIsbn
      marketplace.value = result.marketplace
      loaded.value = true
    } catch {
      error.value = 'Prix indisponibles'
      offers.value = []
      retailers.value = []
    } finally {
      isLoading.value = false
    }
  }

  return { offers, retailers, hasIsbn, marketplace, isLoading, error, loaded, load }
}
