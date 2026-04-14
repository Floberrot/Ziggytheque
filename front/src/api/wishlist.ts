import client from './client'
import type { CollectionEntry } from '@/types'

// GET /api/wishlist now returns CollectionEntry[] (entries with at least one wishlisted volume)
export async function getWishlist(): Promise<CollectionEntry[]> {
  const res = await client.get('/wishlist')
  return res.data
}

export async function addToWishlist(mangaId: string): Promise<{ id: string }> {
  const res = await client.post('/wishlist', { mangaId })
  return res.data
}

// id is now a CollectionEntry.id
export async function removeFromWishlist(id: string): Promise<void> {
  await client.delete(`/wishlist/${id}`)
}

// id is now a CollectionEntry.id — marks all wishlisted volumes as owned
export async function purchaseWishlistItem(id: string): Promise<void> {
  await client.post(`/wishlist/${id}/purchase`)
}
