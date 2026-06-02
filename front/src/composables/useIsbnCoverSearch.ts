import { ref, watch, toRef } from 'vue'
import type { MaybeRefOrGetter } from 'vue'
import { coverByIsbn } from '@/api/manga'

export interface IsbnCoverResult {
  coverUrl: string
  spineUrl: string | null
  isbn: string | null
  source: string
}

export function useIsbnCoverSearch(
  isbn: MaybeRefOrGetter<string>,
  options?: { immediate?: boolean },
) {
  const isbnRef = toRef(isbn)
  // Grouped: every source's cover for this ISBN (BnF, OpenLibrary, Google…).
  const covers = ref<IsbnCoverResult[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  async function search(): Promise<void> {
    const value = isbnRef.value
    if (!value.trim()) return

    isLoading.value = true
    error.value = null
    covers.value = []

    try {
      covers.value = await coverByIsbn(value)
    } catch {
      error.value = 'Erreur lors de la recherche de couverture.'
    } finally {
      isLoading.value = false
    }
  }

  if (options?.immediate) {
    watch(isbnRef, search, { immediate: true })
  }

  return { covers, isLoading, error, search }
}
