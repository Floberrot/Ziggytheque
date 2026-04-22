import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import * as api from '@/api/collection'
import type { ReadingStatus, VolumeToggleField } from '@/types'

export function useCollectionList() {
  return useQuery({
    queryKey: ['collection'],
    queryFn: () => api.getCollection(),
  })
}

export function useCollectionEntry(id: string) {
  return useQuery({
    queryKey: ['collection', id],
    queryFn: () => api.getCollectionEntry(id),
  })
}

export function useAddToCollection() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (mangaId: string) => api.addToCollection(mangaId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['collection'] })
    },
  })
}

export function useRemoveFromCollection() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (id: string) => api.removeFromCollection(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['collection'] })
    },
  })
}

export function useUpdateReadingStatus() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, status }: { id: string; status: ReadingStatus }) =>
      api.updateReadingStatus(id, status),
    onSuccess: (_, { id }) => {
      queryClient.invalidateQueries({ queryKey: ['collection', id] })
      queryClient.invalidateQueries({ queryKey: ['stats'] })
    },
  })
}

export function useToggleVolume() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      collectionId,
      volumeEntryId,
      field,
    }: {
      collectionId: string
      volumeEntryId: string
      field: VolumeToggleField
    }) => api.toggleVolume(collectionId, volumeEntryId, field),
    onSuccess: (_, { collectionId }) => {
      queryClient.invalidateQueries({ queryKey: ['collection', collectionId] })
      queryClient.invalidateQueries({ queryKey: ['wishlist'] })
      queryClient.invalidateQueries({ queryKey: ['stats'] })
    },
  })
}

export function useAddRemainingToWishlist() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: (collectionId: string) => api.addRemainingToWishlist(collectionId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['wishlist'] })
    },
  })
}

export function usePurchaseVolume() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ collectionId, volumeEntryId }: { collectionId: string; volumeEntryId: string }) =>
      api.purchaseVolume(collectionId, volumeEntryId),
    onSuccess: (_, { collectionId }) => {
      queryClient.invalidateQueries({ queryKey: ['collection', collectionId] })
      queryClient.invalidateQueries({ queryKey: ['wishlist'] })
      queryClient.invalidateQueries({ queryKey: ['stats'] })
    },
  })
}

export function useSyncVolumes() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ collectionId, upToVolume }: { collectionId: string; upToVolume?: number }) =>
      api.syncVolumes(collectionId, upToVolume),
    onSuccess: (_, { collectionId }) => {
      queryClient.invalidateQueries({ queryKey: ['collection', collectionId] })
    },
  })
}

export function useBatchSetPrice() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ collectionId, price }: { collectionId: string; price: number }) =>
      api.batchSetVolumePrice(collectionId, price),
    onSuccess: (_, { collectionId }) => {
      queryClient.invalidateQueries({ queryKey: ['collection', collectionId] })
      queryClient.invalidateQueries({ queryKey: ['stats'] })
    },
  })
}

export function useUpdateRating() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ id, rating }: { id: string; rating: number }) =>
      api.updateCollectionRating(id, rating),
    onSuccess: (_, { id }) => {
      queryClient.invalidateQueries({ queryKey: ['collection', id] })
    },
  })
}
