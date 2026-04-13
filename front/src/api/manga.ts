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
}): Promise<{ id: string }> {
  const res = await client.post('/manga', payload)
  return res.data
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
