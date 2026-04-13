import client from './client'
import type { Notification } from '@/types'

export async function getNotifications(): Promise<Notification[]> {
  const res = await client.get('/notifications')
  return res.data
}

export async function markNotificationRead(id: string): Promise<void> {
  await client.patch(`/notifications/${id}/read`)
}
