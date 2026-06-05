import { ref } from 'vue'
import type { MaybeRefOrGetter } from 'vue'
import { toValue } from 'vue'
import { getVolumePrices } from '@/api/manga'
import type { PriceOffer } from '@/api/manga'

export type { PriceOffer }

export function useVolumePrices(
  mangaId: MaybeRefOrGetter<string>,
  volumeId: MaybeRefOrGetter<string>,
) {
  const offers = ref<PriceOffer[]>([])
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
      hasIsbn.value = result.hasIsbn
      marketplace.value = result.marketplace
      loaded.value = true
    } catch {
      error.value = 'Prix indisponibles'
      offers.value = []
    } finally {
      isLoading.value = false
    }
  }

  return { offers, hasIsbn, marketplace, isLoading, error, loaded, load }
}
