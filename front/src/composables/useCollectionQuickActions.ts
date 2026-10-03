import { computed, ref } from 'vue'
import { useMutation, useQueryClient } from '@tanstack/vue-query'
import { useI18n } from 'vue-i18n'
import { removeFromCollection, toggleFollow, updateCollectionRating } from '@/api/collection'
import { useUiStore } from '@/stores/useUiStore'
import type { CollectionEntry, QuickActionRequest } from '@/types'

/**
 * The quick actions of a collection card (right click, long press, Menu key): follow
 * the series' releases, rate it, remove it. Shared by the collection and the dashboard
 * pages, which call it — the mutations stay page-level — and hand what it returns to
 * CollectionQuickActions.
 */
export function useCollectionQuickActions() {
  const queryClient = useQueryClient()
  const ui = useUiStore()
  const { t } = useI18n()

  /** The card whose quick actions are open, or null. */
  const quickActionRequest = ref<QuickActionRequest | null>(null)

  function openQuickActions(request: QuickActionRequest): void {
    quickActionRequest.value = request
  }

  function closeQuickActions(): void {
    quickActionRequest.value = null
  }

  function refreshCollection(): void {
    queryClient.invalidateQueries({ queryKey: ['collection'] })
    queryClient.invalidateQueries({ queryKey: ['stats'] })
  }

  /** The open menu reflects the change at once, before the list is refetched. */
  function patchRequestedEntry(entryId: string, patch: Partial<CollectionEntry>): void {
    const request = quickActionRequest.value
    if (request?.entry.id === entryId) {
      quickActionRequest.value = { ...request, entry: { ...request.entry, ...patch } }
    }
  }

  const followMutation = useMutation({
    mutationFn: (entry: CollectionEntry) => toggleFollow(entry.id),
    onSuccess: (result, entry) => {
      patchRequestedEntry(entry.id, { notificationsEnabled: result.notificationsEnabled })
      ui.addToast(t(result.notificationsEnabled ? 'quickActions.followed' : 'quickActions.unfollowed'), 'success')
      refreshCollection()
    },
    onError: () => ui.addToast(t('quickActions.error'), 'error'),
  })

  const rateMutation = useMutation({
    mutationFn: ({ entry, rating }: { entry: CollectionEntry; rating: number }) =>
      updateCollectionRating(entry.id, rating),
    onSuccess: (_result, { entry, rating }) => {
      patchRequestedEntry(entry.id, { rating })
      ui.addToast(t('quickActions.rated'), 'success')
      refreshCollection()
    },
    onError: () => ui.addToast(t('quickActions.error'), 'error'),
  })

  const removeMutation = useMutation({
    mutationFn: (entry: CollectionEntry) => removeFromCollection(entry.id),
    onSuccess: () => {
      closeQuickActions()
      ui.addToast(t('collection.removed'), 'success')
      refreshCollection()
    },
    onError: () => ui.addToast(t('quickActions.error'), 'error'),
  })

  const quickActionBusy = computed(
    () => followMutation.isPending.value || rateMutation.isPending.value || removeMutation.isPending.value,
  )

  function followEntry(entry: CollectionEntry): void {
    followMutation.mutate(entry)
  }

  function rateEntry(entry: CollectionEntry, rating: number): void {
    rateMutation.mutate({ entry, rating })
  }

  function removeEntry(entry: CollectionEntry): void {
    removeMutation.mutate(entry)
  }

  return {
    quickActionRequest,
    quickActionBusy,
    openQuickActions,
    closeQuickActions,
    followEntry,
    rateEntry,
    removeEntry,
  }
}
