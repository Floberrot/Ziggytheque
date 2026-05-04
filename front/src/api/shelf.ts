import client from './client'

export interface ShelfVolume {
  id: string
  number: number
  coverUrl: string | null
}

export interface ShelfCollection {
  id: string
  manga: {
    id: string
    title: string
    edition: string | null
    coverUrl: string | null
  }
  volumes: ShelfVolume[]
}

export const getShelf = (): Promise<ShelfCollection[]> =>
  client.get('/shelf').then((r) => r.data)
