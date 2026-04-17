import client from './client'
import type { Manga, MangaDetail } from '@/types'

export type CoverSource = 'openlibrary' | 'google' | 'none' | null

export interface ExternalMangaResult {
  externalId: string
  title: string
  edition: string | null
  coverUrl: string | null
  language: string
  totalVolumes: number | null
}

export interface CoverSearchResponse {
  source: CoverSource
  results: ExternalMangaResult[]
}

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
  edition: string
  language: string
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

export async function searchVolumeExternal(q: string): Promise<CoverSearchResponse> {
  const res = await client.get('/manga/volume-search', { params: { q } })
  return res.data
}

export async function updateManga(
  id: string,
  payload: { title?: string; edition?: string },
): Promise<void> {
  await client.patch(`/manga/${id}`, payload)
}

export async function updateVolume(
  mangaId: string,
  volumeId: string,
  payload: { coverUrl?: string; releaseDate?: string; priceCode?: string },
): Promise<void> {
  await client.patch(`/manga/${mangaId}/volumes/${volumeId}`, payload)
}

export async function addVolume(
  mangaId: string,
  payload: {
    number: number
    coverUrl?: string
    priceCode?: string
    releaseDate?: string
  },
): Promise<{ id: string }> {
  const res = await client.post(`/manga/${mangaId}/volumes`, payload)
  return res.data
}
