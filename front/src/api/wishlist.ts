import client from './client'
import type { WishlistEntry } from '@/types'

/** Returns collection entries that have at least one wished (non-owned) volume */
export async function getWishlist(): Promise<WishlistEntry[]> {
  const res = await client.get('/wishlist')
  return res.data
}

/** Add all non-owned volumes of an oeuvre to the wishlist */
export async function addRemainingToWishlist(collectionEntryId: string): Promise<void> {
  await client.post(`/wishlist/${collectionEntryId}/add-remaining`)
}

/** Clear all wished flags for an oeuvre (remove from wishlist view) */
export async function clearWishlist(collectionEntryId: string): Promise<void> {
  await client.delete(`/wishlist/${collectionEntryId}`)
}
