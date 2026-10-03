import type { VolumeEntry, VolumeToggleField } from '@/types'

/**
 * Mirrors the backend ToggleVolumeHandler rules, so an optimistic cache update shows
 * exactly what the server will persist (no flicker once the refetch settles).
 */
export function applyVolumeToggle(volume: VolumeEntry, field: VolumeToggleField): VolumeEntry {
  const next = { ...volume }
  if (field === 'isOwned') {
    next.isOwned = !volume.isOwned
    // Owning a tome takes it off the wishlist and out of the announced ones.
    if (next.isOwned) {
      next.isWished = false
      next.isAnnounced = false
    }
  } else if (field === 'isRead') {
    next.isRead = !volume.isRead
  } else if (field === 'isWished') {
    next.isWished = !volume.isWished
  } else {
    next.isAnnounced = !volume.isAnnounced
  }
  return next
}

/** The counters of a series detail, recomputed from its tomes after an optimistic toggle. */
export function volumeCounts(volumes: VolumeEntry[]): {
  ownedCount: number
  readCount: number
  wishedCount: number
  ownedValue: number
} {
  return {
    ownedCount: volumes.filter((volume) => volume.isOwned).length,
    readCount: volumes.filter((volume) => volume.isRead).length,
    wishedCount: volumes.filter((volume) => volume.isWished && !volume.isOwned).length,
    ownedValue: volumes.reduce((sum, volume) => sum + (volume.isOwned ? (volume.price ?? 0) : 0), 0),
  }
}
