import client from './client'
import type { Manga, MangaDetail } from '@/types'

export async function searchManga(q: string): Promise<Manga[]> {
  const res = await client.get('/manga', { params: { q } })
  return res.data
}

export async function getManga(id: string): Promise<MangaDetail> {
  const res = await client.get(`/manga/${id}`)
  return res.data
}

export async function importManga(payload: {
  title: string
  language: string
  edition?: string
  author?: string
  summary?: string
  coverUrl?: string
  genre?: string
  externalId?: string
  totalVolumes?: number
  publisher?: string
  editionYear?: number
  externalWorkId?: string
}): Promise<{ id: string }> {
  const res = await client.post('/manga', payload)
  return res.data
}

export type CoverProvider = 'composite' | 'mangadex' | 'openlibrary' | 'googlebooks'

export async function searchVolumeExternal(
  q: string,
  page = 1,
  volumeNumber?: number | null,
  edition?: string | null,
  provider: CoverProvider = 'composite',
  isbn?: string | null,
  publisher?: string | null,
  year?: number | null,
  language = 'fr',
): Promise<{
  externalId: string | null
  title: string
  edition: string | null
  coverUrl: string | null
  spineUrl: string | null
  isbn: string | null
  language: string
  totalVolumes: number | null
  source: string | null
}[]> {
  const params: Record<string, string | number> = { q, page, provider, language }
  if (volumeNumber != null) params.volumeNumber = volumeNumber
  if (edition != null) params.edition = edition
  if (isbn != null) params.isbn = isbn
  if (publisher != null) params.publisher = publisher
  if (year != null) params.year = year
  const res = await client.get('/manga/volume-search', { params })
  return res.data
}

export interface DiscoveredEdition {
  publisher: string
  editionLabel: string | null
  year: number | null
  language: string
  coverUrl: string | null
  volumeCount: number | null
  sampleIsbn: string | null
  source: string
}

export async function discoverEditions(title: string, country = 'FR'): Promise<DiscoveredEdition[]> {
  const res = await client.get('/manga/editions', { params: { title, country } })
  return res.data
}

export interface ScanSessionStartResponse {
  sessionId: string
  mercureUrl: string
  subscriberToken: string
  topic: string
}

export async function startScanSession(): Promise<ScanSessionStartResponse> {
  const res = await client.post('/manga/scan-session')
  return res.data
}

export async function postScannedIsbn(sessionId: string, isbn: string): Promise<void> {
  await client.post(`/manga/scan-session/${sessionId}/isbn`, { isbn })
}

export async function updateManga(
  id: string,
  payload: { title?: string; edition?: string; coverUrl?: string; publisher?: string; editionYear?: number },
): Promise<void> {
  await client.patch(`/manga/${id}`, payload)
}

export async function updateVolume(
  mangaId: string,
  volumeId: string,
  payload: { coverUrl?: string; releaseDate?: string; price?: number | null; spineUrl?: string; isbn?: string },
): Promise<void> {
  await client.patch(`/manga/${mangaId}/volumes/${volumeId}`, payload)
}

export async function addVolume(
  mangaId: string,
  payload: {
    number: number
    coverUrl?: string
    releaseDate?: string
  },
): Promise<{ id: string }> {
  const res = await client.post(`/manga/${mangaId}/volumes`, payload)
  return res.data
}

export interface CoverBatchStartResponse {
  batchId: string
  mercureUrl: string
  subscriberToken: string
  topic: string
}

export async function autoFillCovers(
  mangaId: string,
  payload: { force?: boolean; volumeIds?: string[] | null } = {},
): Promise<CoverBatchStartResponse> {
  const res = await client.post(`/manga/${mangaId}/auto-covers`, payload)
  return res.data
}
