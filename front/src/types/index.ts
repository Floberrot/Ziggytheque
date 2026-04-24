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
