import axios from 'axios'
import client from './client'
import type { CreateShareResponse, ShareSnapshot } from '@/types'

/** Freezes the current user's public stats and returns the permanent share link. */
export async function createShare(): Promise<CreateShareResponse> {
  const res = await client.post('/share')
  return res.data
}

/**
 * Reads a public snapshot. Uses a bare axios call (not the authenticated client)
 * so the share page works for logged-out visitors and never triggers the global
 * 401 → /login redirect baked into the shared client.
 */
export async function getShare(token: string): Promise<ShareSnapshot> {
  const res = await axios.get(`/api/share/${token}`)
  return res.data
}
