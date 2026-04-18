import client from './client'
import type { ArticlePage, ActivityLog, Notification } from '@/types'

export async function getNotifications(): Promise<Notification[]> {
  const res = await client.get('/notifications')
  return res.data
}

export interface ArticlesParams {
  page?: number
  limit?: number
  collectionEntryId?: string
}

export async function getArticles(params: ArticlesParams = {}): Promise<ArticlePage> {
  const res = await client.get('/articles', { params })
  return res.data
}

export async function getActivityLogs(limit = 50): Promise<ActivityLog[]> {
  const res = await client.get('/articles/activity-logs', { params: { limit } })
  return res.data
}
