import client from './client'
import type { CollectionEntry, CollectionEntryDetail, ReadingStatus, VolumeToggleField } from '@/types'

export interface CollectionFilters {
  search?: string
  genre?: string
  edition?: string
  readingStatus?: string
  sort?: 'rating_asc' | 'rating_desc'
  followed?: boolean
  page?: number
}

export interface CollectionPage {
  items: CollectionEntry[]
  total: number
  page: number
  limit: number
}

export const getCollection = (filters: CollectionFilters = {}): Promise<CollectionPage> =>
  client.get('/collection', { params: filters }).then((r) => r.data)

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

export async function batchSetVolumePrice(collectionId: string, price: number): Promise<void> {
  await client.patch(`/collection/${collectionId}/batch-price`, { price })
}

export async function updateCollectionRating(id: string, rating: number): Promise<void> {
  await client.patch(`/collection/${id}/rating`, { rating })
}

export async function toggleFollow(id: string): Promise<{ notificationsEnabled: boolean }> {
  const res = await client.patch(`/collection/${id}/follow`)
  return res.data
}
