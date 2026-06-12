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

/** Translate a summary into French (English → French for now). */
export async function translateSummary(text: string): Promise<string> {
  const res = await client.post('/manga/translate-summary', { text })
  return res.data.translated
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
  const params: Record<string, string | number> = { q, page, provider }
  if (volumeNumber != null) params.volumeNumber = volumeNumber
  if (edition != null) params.edition = edition
  const res = await client.get('/manga/volume-search', { params })
  return res.data
}

export async function updateManga(
  id: string,
  payload: { title?: string; edition?: string; coverUrl?: string },
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

export async function coverByIsbn(isbn: string): Promise<{
  coverUrl: string
  spineUrl: string | null
  isbn: string | null
  source: string
}[]> {
  const res = await client.get('/manga/cover-by-isbn', { params: { isbn } })
  return res.data
}

export interface ScanSessionResponse {
  sessionId: string
  scanToken: string
  mercureUrl: string
  subscriberToken: string
  topic: string
}

export async function createScanSession(payload: {
  mangaId: string
  volumeId: string
}): Promise<ScanSessionResponse> {
  const res = await client.post('/scan/sessions', payload)
  return res.data
}

export async function submitScan(payload: { scanToken: string; isbn: string }): Promise<void> {
  await client.post('/scan/submit', payload)
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

export type EditionFormat = 'broche' | 'relie' | 'coffret' | 'deluxe' | 'omnibus' | 'unknown'

export interface ExternalEdition {
  workTitle: string
  editionLabel: string
  publisher: string | null
  language: string
  country: string | null
  format: EditionFormat
  volumeCount: number | null
  isbnSample: string | null
  coverUrl: string | null
  source: string
  externalId: string | null
  editionLine: string | null
}

export async function discoverEditions(params: {
  q: string
  author?: string | null
  language?: string | null
}): Promise<ExternalEdition[]> {
  const res = await client.get('/manga/editions', { params })
  return res.data
}

export async function mangaEditions(mangaId: string): Promise<ExternalEdition[]> {
  const res = await client.get(`/manga/${mangaId}/editions`)
  return res.data
}

export type PriceKind = 'merchant_live' | 'publisher_reference'

export interface PriceOffer {
  kind: PriceKind
  merchant: string
  merchantLogo: string
  currency: string
  amount: number
  url: string | null
  source: string
}

export interface VolumePricesResponse {
  offers: PriceOffer[]
  hasIsbn: boolean
  marketplace: string | null
}

export async function getVolumePrices(
  mangaId: string,
  volumeId: string,
  marketplace?: string | null,
): Promise<VolumePricesResponse> {
  const params: Record<string, string> = {}
  if (marketplace) params.marketplace = marketplace
  const res = await client.get(`/manga/${mangaId}/volumes/${volumeId}/prices`, { params })
  return res.data
}
