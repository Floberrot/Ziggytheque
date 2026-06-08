import client from './client'
import type { ArticlePage, ActivityLogPage, ArticleCollectionEntry, Notification } from '@/types'

export async function getNotifications(): Promise<Notification[]> {
  const res = await client.get('/notifications')
  return res.data
}

/** Every followed work for the current account, sorted by title — not paginated. */
export async function getFollowedEntries(): Promise<ArticleCollectionEntry[]> {
  const res = await client.get('/articles/followed')
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
