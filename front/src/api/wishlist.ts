import client from './client'
import type { WishlistItem } from '@/types'

export async function getWishlist(): Promise<WishlistItem[]> {
  const res = await client.get('/wishlist')
  return res.data
}

export async function addToWishlist(mangaId: string): Promise<{ id: string }> {
  const res = await client.post('/wishlist', { mangaId })
  return res.data
}

export async function removeFromWishlist(id: string): Promise<void> {
  await client.delete(`/wishlist/${id}`)
}

export async function purchaseWishlistItem(id: string): Promise<void> {
  await client.post(`/wishlist/${id}/purchase`)
}
