import client from './client'
import type { CollectionEntry, CollectionEntryDetail, ReadingStatus, VolumeToggleField } from '@/types'

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
  field: VolumeToggleField,
): Promise<void> {
  await client.patch(`/collection/${collectionId}/volumes/${volumeEntryId}/toggle`, { field })
}

export async function addRemainingToWishlist(collectionId: string): Promise<void> {
  await client.post(`/collection/${collectionId}/add-to-wishlist`)
}

export async function purchaseVolume(collectionId: string, volumeEntryId: string): Promise<void> {
  await client.post(`/collection/${collectionId}/volumes/${volumeEntryId}/purchase`)
}

export async function syncVolumes(collectionId: string, upToVolume?: number): Promise<void> {
  await client.post(`/collection/${collectionId}/sync-volumes`, upToVolume ? { upToVolume } : {})
}
