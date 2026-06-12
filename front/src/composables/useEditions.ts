import { ref, computed } from 'vue'
import { discoverEditions, mangaEditions } from '@/api/manga'
import type { ExternalEdition } from '@/api/manga'

export type { ExternalEdition }

export interface EditionGroup {
  country: string | null
  language: string
  editions: ExternalEdition[]
}

function buildGroups(editions: ExternalEdition[]): EditionGroup[] {
  const map = new Map<string, EditionGroup>()
  for (const edition of editions) {
    const key = edition.country ?? edition.language
    if (!map.has(key)) {
      map.set(key, { country: edition.country, language: edition.language, editions: [] })
    }
    map.get(key)!.editions.push(edition)
  }
  return [...map.values()]
}

export function useEditions() {
  const editions = ref<ExternalEdition[]>([])
  const isLoading = ref(false)
  const error = ref<string | null>(null)

  const groupedByCountry = computed(() => buildGroups(editions.value))

  async function discover(
    query: string,
    author?: string | null,
    language?: string | null,
  ): Promise<void> {
    const trimmed = query.trim()
    if (!trimmed) return
    isLoading.value = true
    error.value = null
    try {
      editions.value = await discoverEditions({ q: trimmed, author, language })
    } catch {
      error.value = 'Recherche indisponible'
      editions.value = []
    } finally {
      isLoading.value = false
    }
  }

  async function loadForManga(mangaId: string): Promise<void> {
    isLoading.value = true
    error.value = null
    try {
      editions.value = await mangaEditions(mangaId)
    } catch {
      error.value = 'Recherche indisponible'
      editions.value = []
    } finally {
      isLoading.value = false
    }
  }

  return { editions, groupedByCountry, isLoading, error, discover, loadForManga }
}
