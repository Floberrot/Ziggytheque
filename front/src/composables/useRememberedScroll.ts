import { nextTick, onBeforeUnmount, onMounted, toValue, watch, type MaybeRefOrGetter } from 'vue'
import { useAuthStore } from '@/stores/useAuthStore'
import { useCollectionFiltersStore, type RememberedList } from '@/stores/useCollectionFiltersStore'

function scrollWindowTo(top: number): void {
  window.scrollTo({ top, left: 0, behavior: 'instant' })
}

/**
 * Puts the reader back where they left a list page, however they come back to it:
 * browser back, the bottom bar, the "Collection" link of a series page.
 *
 * The offset is saved when the page goes away and restored once the list is on screen
 * again (`ready`): at once when it comes from the query cache, after its first load
 * otherwise (the page shows its top meanwhile). Later reloads of the list — a new filter —
 * never scroll. The router leaves these routes alone (`meta.rememberScroll`).
 */
export function useRememberedScroll(list: RememberedList, ready: MaybeRefOrGetter<boolean>): void {
  const listState = useCollectionFiltersStore()
  const auth = useAuthStore()
  let stopWaiting: (() => void) | null = null

  function restore(): void {
    const top = listState.scrollPositions[list] ?? 0
    // After the list is painted, so the page is tall enough to scroll that far.
    void nextTick(() => scrollWindowTo(top))
  }

  onMounted(() => {
    if (toValue(ready)) {
      restore()
      return
    }
    scrollWindowTo(0)
    stopWaiting = watch(
      () => toValue(ready),
      (isReady) => {
        if (!isReady) return
        stopWaiting?.()
        stopWaiting = null
        restore()
      },
    )
  })

  onBeforeUnmount(() => {
    stopWaiting?.()
    // Signed out (logout on this page): there is nothing to come back to.
    if (!auth.isAuthenticated) return
    listState.saveScroll(list, window.scrollY)
  })
}
