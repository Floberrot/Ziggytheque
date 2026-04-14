export interface PriceCode {
  code: string
  label: string
  value: number
  createdAt: string
}

export interface Volume {
  id: string
  number: number
  coverUrl: string | null
  priceCode: PriceCode | null
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

export interface VolumeEntry {
  id: string
  volumeId: string
  number: number
  coverUrl: string | null
  priceCode: PriceCode | null
  isOwned: boolean
  isRead: boolean
  isWishlisted: boolean
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
  wishlistCount: number
  totalVolumes: number
  addedAt: string
}

export interface CollectionEntryDetail extends CollectionEntry {
  volumes: VolumeEntry[]
}

export interface WishlistItem {
  id: string
  manga: Manga
  isPurchased: boolean
  addedAt: string
}

export interface Stats {
  totalMangas: number
  totalOwned: number
  totalRead: number
  totalWishlist: number
  collectionValue: number
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
