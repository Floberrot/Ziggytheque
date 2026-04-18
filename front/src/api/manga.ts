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

/** Google Books search for individual volume covers/metadata */
export async function searchVolumeExternal(q: string, page = 1): Promise<{
  externalId: string
  title: string
  edition: string | null
  coverUrl: string | null
  language: string
  totalVolumes: number | null
}[]> {
  const res = await client.get('/manga/volume-search', { params: { q, page } })
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
  payload: { coverUrl?: string; releaseDate?: string; price?: number | null },
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
