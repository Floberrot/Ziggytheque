import { ref, watch, toRef } from 'vue'
import type { MaybeRefOrGetter } from 'vue'
import { coverByIsbn } from '@/api/manga'
import { normalizeIsbn13 } from '@/utils/isbn'

export interface IsbnCoverResult {
  coverUrl: string
  spineUrl: string | null
  isbn: string | null
  source: string
}

/**
 * Cover search by ISBN. The input is normalized (separators / EAN add-on stripped,
 * ISBN-10 converted) before hitting the API, and `error` distinguishes an invalid
 * ISBN or a backend failure (i18n keys, translated by the caller) from the plain
 * "0 result" empty state (`covers` empty, `error` null).
 */
export function useIsbnCoverSearch(
  isbn: MaybeRefOrGetter<string>,
  options?: { immediate?: boolean },
) {
  const isbnRef = toRef(isbn)
  // Grouped: every source's cover for this ISBN (BnF, OpenLibrary, Google…).
  const covers = ref<IsbnCoverResult[]>([])
  const isLoading = ref(false)
  /** i18n key of the current error, or null. */
  const error = ref<string | null>(null)

  async function search(): Promise<void> {
    const rawValue = isbnRef.value
    if (!rawValue.trim()) return

    covers.value = []
    error.value = null

    const normalized = normalizeIsbn13(rawValue)
    if (!normalized) {
      error.value = 'enrich.isbnInvalid'
      return
    }

    isLoading.value = true

    try {
      covers.value = await coverByIsbn(normalized)
    } catch {
      error.value = 'enrich.isbnSearchError'
    } finally {
      isLoading.value = false
    }
  }

  if (options?.immediate) {
    watch(isbnRef, search, { immediate: true })
  }

  return { covers, isLoading, error, search }
}
