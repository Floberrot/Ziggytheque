import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import * as wishlistApi from '@/api/wishlist'
import * as collectionApi from '@/api/collection'

export function useWishlistList() {
  return useQuery({
    queryKey: ['wishlist'],
    queryFn: () => wishlistApi.getWishlist(),
  })
}

export function useClearWishlist() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (collectionEntryId: string) => wishlistApi.clearWishlist(collectionEntryId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['wishlist'] })
      queryClient.invalidateQueries({ queryKey: ['stats'] })
    },
  })
}

export function useBatchPurchase() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (purchases: Array<{ collectionEntryId: string; volumeEntryId: string }>) =>
      Promise.all(purchases.map((p) => collectionApi.purchaseVolume(p.collectionEntryId, p.volumeEntryId))),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['wishlist'] })
      queryClient.invalidateQueries({ queryKey: ['collection'] })
      queryClient.invalidateQueries({ queryKey: ['stats'] })
    },
  })
}
