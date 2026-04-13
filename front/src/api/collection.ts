import client from './client'
import type { CollectionEntry, CollectionEntryDetail, ReadingStatus } from '@/types'

export async function getCollection(): Promise<CollectionEntry[]> {
  const res = await client.get('/collection')
  return res.data
}

export async function getCollectionEntry(id: string): Promise<CollectionEntryDetail> {
  const res = await client.get(`/collection/${id}`)
  return res.data
}

export async function addToCollection(mangaId: string): Promise<{ id: string }> {
  const res = await client.post('/collection', { mangaId })
  return res.data
}

export async function removeFromCollection(id: string): Promise<void> {
  await client.delete(`/collection/${id}`)
}

export async function updateReadingStatus(id: string, status: ReadingStatus): Promise<void> {
  await client.patch(`/collection/${id}/status`, { status })
}

export async function toggleVolume(
  collectionId: string,
  volumeEntryId: string,
  field: 'isOwned' | 'isRead',
): Promise<void> {
  await client.patch(`/collection/${collectionId}/volumes/${volumeEntryId}/toggle`, { field })
}
