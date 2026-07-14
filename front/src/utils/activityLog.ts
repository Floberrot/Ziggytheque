import type { EventType, LogStatus } from '@/types'

export const EVENT_TYPE_LABELS: Record<EventType, string> = {
  rss_fetch: 'RSS',
  jikan_fetch: 'Jikan',
  discord_sent: 'Discord',
  scheduler_fire: 'Scheduler',
  http_error: 'HTTP',
  worker_failure: 'Worker',
  user_action: 'API',
  collection_action: 'Collection',
  manga_action: 'Manga',
  auth_action: 'Auth',
  wishlist_action: 'Wishlist',
}

export const EVENT_TYPE_BADGES: Record<EventType, string> = {
  rss_fetch: 'badge-info',
  jikan_fetch: 'badge-info',
  discord_sent: 'badge-secondary',
  scheduler_fire: 'badge-accent',
  http_error: 'badge-error',
  worker_failure: 'badge-error',
  user_action: 'badge-neutral',
  collection_action: 'badge-primary',
  manga_action: 'badge-primary',
  auth_action: 'badge-warning',
  wishlist_action: 'badge-secondary',
}

export const STATUS_BADGES: Record<LogStatus, string> = {
  running: 'badge-warning',
  success: 'badge-success',
  error: 'badge-error',
}

export function formatDuration(durationMs: number | null): string {
  if (durationMs === null) return '—'
  if (durationMs < 1000) return `${durationMs} ms`
  return `${(durationMs / 1000).toFixed(1)} s`
}
