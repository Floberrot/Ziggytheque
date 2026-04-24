export interface Volume {
  id: string
  number: number
  coverUrl: string | null
  price: number | null
  releaseDate: string | null
}

export interface Manga {
  id: string
  title: string
  edition: string
  language: string
  author: string | null
  summary: string | null
  coverUrl: string | null
  genre: string | null
  externalId: string | null
  totalVolumes: number
  createdAt: string
}

export interface MangaDetail extends Manga {
  volumes: Volume[]
}

export type ReadingStatus = 'not_started' | 'in_progress' | 'completed' | 'on_hold' | 'dropped'
export type VolumeToggleField = 'isOwned' | 'isRead' | 'isWished' | 'isAnnounced'

export interface VolumeEntry {
  id: string
  volumeId: string
  number: number
  coverUrl: string | null
  price: number | null
  isOwned: boolean
  isRead: boolean
  isWished: boolean
  isAnnounced: boolean
  review: string | null
  rating: number | null
}

export interface CollectionEntry {
  id: string
  manga: Manga
  readingStatus: ReadingStatus
  review: string | null
  rating: number | null
  ownedCount: number
  readCount: number
  wishedCount: number
  totalVolumes: number
  addedAt: string
  ownedValue: number
  notificationsEnabled: boolean
}

export interface ArticleCollectionEntry {
  id: string
  manga: {
    id: string
    title: string
    coverUrl: string | null
  }
}

export interface Article {
  id: string
  collectionEntry: ArticleCollectionEntry
  title: string
  url: string
  sourceName: string
  author: string | null
  imageUrl: string | null
  snippet: string | null
  publishedAt: string | null
  createdAt: string
}

export interface ArticlePage {
  items: Article[]
  total: number
  page: number
  limit: number
  totalPages: number
}

export type EventType =
  | 'rss_fetch'
  | 'jikan_fetch'
  | 'discord_sent'
  | 'scheduler_fire'
  | 'http_error'
  | 'worker_failure'
  | 'user_action'

export type LogStatus = 'running' | 'success' | 'error'

export interface ActivityLog {
  id: string
  eventType: EventType
  sourceName: string
  collectionEntryId: string | null
  mangaTitle: string | null
  status: LogStatus
  errorMessage: string | null
  newArticlesCount: number | null
  metadata: Record<string, unknown> | null
  durationMs: number | null
  startedAt: string
  finishedAt: string | null
}

export interface ActivityLogPage {
  items: ActivityLog[]
  total: number
  page: number
  limit: number
  totalPages: number
}

export interface CollectionEntryDetail extends CollectionEntry {
  volumes: VolumeEntry[]
}

/** Alias: wishlist view reuses CollectionEntryDetail (filtered to wished volumes) */
export type WishlistEntry = CollectionEntryDetail

export interface Stats {
  totalMangas: number
  totalOwned: number
  totalRead: number
  totalWishlist: number
  ownedValue: number
  wishlistValue: number
  totalValue: number
  genreBreakdown: Record<string, number>
  recentAdditions: CollectionEntry[]
}

export interface Notification {
  id: string
  type: string
  message: string
  isRead: boolean
  createdAt: string
}
