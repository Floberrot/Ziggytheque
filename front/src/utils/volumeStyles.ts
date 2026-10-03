import type { VolumeEntry } from '@/types'

/**
 * Ring colour of a tome tile, from its status (static literals so Tailwind keeps them).
 * A tome picked in the batch selection wins over its status.
 */
export function volumeRingClass(volume: VolumeEntry, selected = false): string {
  if (selected) return 'ring-primary'
  if (volume.isOwned && volume.isRead) return 'ring-info/80'
  if (volume.isOwned) return 'ring-success/70'
  if (volume.isWished) return 'ring-warning/60'
  if (volume.isAnnounced && !volume.isOwned) return 'ring-secondary/60'
  return 'ring-base-300/30'
}

export type VolumeHeadlineStatus = 'announced' | 'owned' | 'wished' | 'none'

/** The one status a tome is summed up by: the badge and cover ring of the tome modal. */
export function volumeHeadlineStatus(volume: VolumeEntry): VolumeHeadlineStatus {
  if (volume.isAnnounced && !volume.isOwned) return 'announced'
  if (volume.isOwned) return 'owned'
  if (volume.isWished) return 'wished'
  return 'none'
}

/** Owned tomes stand out; the untracked ones fade into grey. */
export function volumeOpacityClass(volume: VolumeEntry): string {
  if (volume.isOwned) return 'opacity-100'
  if (volume.isWished) return 'opacity-65'
  if (volume.isAnnounced && !volume.isOwned) return 'opacity-60'
  return 'opacity-25 grayscale'
}
