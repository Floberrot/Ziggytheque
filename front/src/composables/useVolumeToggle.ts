import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { useI18n } from 'vue-i18n'
import { toggleVolume } from '@/api/collection'
import { useUiStore } from '@/stores/useUiStore'
import type { CollectionEntryDetail, VolumeToggleField } from '@/types'
import { applyVolumeToggle, volumeCounts } from '@/utils/volumeToggle'

export interface VolumeToggleVariables {
  volumeEntryId: string
  field: VolumeToggleField
}

/**
 * Flips one flag (owned / read / wished / announced) of a tome of a series detail.
 * Optimistic: the tile reacts at once, even on a slow request, and goes back on failure.
 *
 * Called by the series page — the mutation stays page-level; the tome modal and the
 * tome menus only emit which flag to flip. `onMutate` runs once the cache is patched
 * (the page closes its context menu there).
 */
export function useVolumeToggle(collectionEntryId: string, options: { onMutate?: () => void } = {}) {
  const queryClient = useQueryClient()
  const ui = useUiStore()
  const { t } = useI18n()

  const detailKey = ['collection', collectionEntryId]
  const mutationKey = ['toggle-volume', collectionEntryId]

  return useMutation({
    mutationKey,
    mutationFn: ({ volumeEntryId, field }: VolumeToggleVariables) =>
      toggleVolume(collectionEntryId, volumeEntryId, field),
    onMutate: async ({ volumeEntryId, field }: VolumeToggleVariables) => {
      await queryClient.cancelQueries({ queryKey: detailKey })
      const previous = queryClient.getQueryData<CollectionEntryDetail>(detailKey)
      queryClient.setQueryData<CollectionEntryDetail>(detailKey, (current) => {
        if (!current) return current
        const volumes = current.volumes.map((volume) =>
          volume.id === volumeEntryId ? applyVolumeToggle(volume, field) : volume,
        )
        return { ...current, volumes, ...volumeCounts(volumes) }
      })
      options.onMutate?.()
      return { previous }
    },
    onError: (_error, _variables, context) => {
      if (context?.previous) queryClient.setQueryData(detailKey, context.previous)
      ui.addToast(t('enrich.statusUpdateError'), 'error')
    },
    onSettled: () => {
      // Refetch only once the last rapid toggle has settled: an in-flight refetch from
      // an earlier toggle would otherwise overwrite the newer optimistic state, making a
      // quick second tap on "Lu" look like it did nothing.
      if (queryClient.isMutating({ mutationKey }) === 1) {
        queryClient.invalidateQueries({ queryKey: detailKey })
        queryClient.invalidateQueries({ queryKey: ['collection'] })
        queryClient.invalidateQueries({ queryKey: ['wishlist'] })
        queryClient.invalidateQueries({ queryKey: ['stats'] })
      }
    },
  })
}
