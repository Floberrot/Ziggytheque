import { useMutation, useInfiniteQuery, useQueryClient } from '@tanstack/vue-query'
import * as api from '@/api/manga'

export function useImportManga() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: api.importManga,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['collection'] })
    },
  })
}

export function useUpdateManga() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      payload,
    }: {
      id: string
      payload: { title?: string; edition?: string; coverUrl?: string }
    }) => api.updateManga(id, payload),
    onSuccess: (_, { id }) => {
      queryClient.invalidateQueries({ queryKey: ['collection', id] })
    },
  })
}

export function useUpdateVolume() {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({
      mangaId,
      volumeId,
      payload,
    }: {
      mangaId: string
      volumeId: string
      payload: { coverUrl?: string; releaseDate?: string; price?: number | null }
    }) => api.updateVolume(mangaId, volumeId, payload),
    onSuccess: (_, { mangaId }) => {
      queryClient.invalidateQueries({ queryKey: ['collection', mangaId] })
    },
  })
}

export function useInfiniteExternalSearch() {
  return useInfiniteQuery({
    queryKey: ['manga', 'external-search'],
    queryFn: ({ pageParam }) => api.searchVolumeExternal('', pageParam as number),
    getNextPageParam: (lastPage: any) => {
      if (lastPage && lastPage.length === 20) return undefined
      return undefined
    },
    initialPageParam: 1,
  })
}
