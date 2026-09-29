import client from './client'

export type CatalogueSearchMode = 'title' | 'author' | 'isbn'

export interface CatalogueVolume {
  number: number
  isbn: string | null
  coverUrl: string | null
}

/** The user's collection entry for a catalogue series, when they already follow it. */
export interface CatalogueCollectionStatus {
  entryId: string
  ownedNumbers: number[]
}

/** One series as a collector sees it: a work, at one publisher, in one edition. */
export interface CatalogueEdition {
  workTitle: string
  publisher: string | null
  /** Name of the special edition as the catalogue records it — null for the standard run. */
  specialEdition: string | null
  author: string | null
  coverUrl: string | null
  volumeCount: number
  volumes: CatalogueVolume[]
  collection: CatalogueCollectionStatus | null
}

export interface CatalogueSearchResult {
  query: string
  /** Tome asked in the query ("berserk 5" → 5), preselected when a series is opened. */
  requestedVolume: number | null
  editions: CatalogueEdition[]
}

export interface CatalogueRegistration {
  collectionEntryId: string
  mangaId: string
  seriesCreated: boolean
  entryCreated: boolean
  totalVolumes: number
  addedNumbers: number[]
  alreadyOwnedNumbers: number[]
  /** Tome number → volume entry id, for the tomes that were asked. */
  volumeEntryIds: Record<string, string>
}

export interface ScanResult {
  edition: Omit<CatalogueEdition, 'collection'>
  volumeNumber: number
  alreadyOwned: boolean
  registration: CatalogueRegistration
}

export interface AddFromCataloguePayload {
  workTitle: string
  publisher: string | null
  specialEdition: string | null
  author: string | null
  coverUrl: string | null
  volumeCount: number
  volumes: CatalogueVolume[]
  ownedNumbers: number[]
}

export async function searchCatalogue(q: string, mode: CatalogueSearchMode): Promise<CatalogueSearchResult> {
  const res = await client.get('/catalogue/search', { params: { q, mode } })
  return res.data
}

export async function getCatalogueEdition(identity: {
  workTitle: string
  publisher: string | null
  specialEdition: string | null
}): Promise<CatalogueEdition> {
  const params: Record<string, string> = { workTitle: identity.workTitle }
  if (identity.publisher) params.publisher = identity.publisher
  if (identity.specialEdition) params.specialEdition = identity.specialEdition
  const res = await client.get('/catalogue/edition', { params })
  return res.data
}

export async function addFromCatalogue(payload: AddFromCataloguePayload): Promise<CatalogueRegistration> {
  const res = await client.post('/catalogue/add', payload)
  return res.data
}

export async function scanIsbn(isbn: string): Promise<ScanResult> {
  const res = await client.post('/catalogue/scan', { isbn })
  return res.data
}
