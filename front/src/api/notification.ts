import client from './client'
import type { ArticlePage, ActivityLogPage, Notification } from '@/types'

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

export interface ActivityLogParams {
  page?: number
  limit?: number
  eventType?: string
  status?: string
  collectionEntryId?: string
}

export async function getActivityLogs(params: ActivityLogParams = {}): Promise<ActivityLogPage> {
  const res = await client.get('/articles/activity-logs', { params })
  return res.data
}
