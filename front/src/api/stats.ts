import client from './client'
import type { Stats } from '@/types'

export async function getStats(): Promise<Stats> {
  const res = await client.get('/stats')
  return res.data
}
