import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import * as api from '@/api/wishlist'

export function useWishlistList() {
  return useQuery({
    queryKey: ['wishlist'],
    queryFn: () => api.getWishlist(),
  })
}

export function useClearWishlist() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (collectionEntryId: string) => api.clearWishlist(collectionEntryId),
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
      Promise.all(purchases.map((p) => api.purchaseVolume(p.collectionEntryId, p.volumeEntryId))),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['wishlist'] })
      queryClient.invalidateQueries({ queryKey: ['collection'] })
      queryClient.invalidateQueries({ queryKey: ['stats'] })
    },
  })
}
