import type { VolumeEntry, VolumeToggleField } from '@/types'

export type VolumeBatchAction = 'markRead' | 'markUnread' | 'markOwned' | 'wish' | 'announce' | 'unannounce'

interface VolumeBatchRule {
  /** The API flips this flag on each targeted tome. */
  field: VolumeToggleField
  /** The tomes this action changes; the others already are in the wanted state. */
  applies: (volume: VolumeEntry) => boolean
}

/**
 * What each button of the batch bar does. The API only *toggles* a flag, so an
 * action must reach the tomes that are not yet in the wanted state — never all the
 * selection ("Marquer lus" on a mixed selection would unread the read ones).
 */
export const VOLUME_BATCH_RULES: Record<VolumeBatchAction, VolumeBatchRule> = {
  markRead:   { field: 'isRead',      applies: (volume) => volume.isOwned && !volume.isRead },
  markUnread: { field: 'isRead',      applies: (volume) => volume.isOwned && volume.isRead },
  markOwned:  { field: 'isOwned',     applies: (volume) => !volume.isOwned },
  wish:       { field: 'isWished',    applies: (volume) => !volume.isOwned && !volume.isWished },
  announce:   { field: 'isAnnounced', applies: (volume) => !volume.isOwned && !volume.isAnnounced },
  unannounce: { field: 'isAnnounced', applies: (volume) => !volume.isOwned && volume.isAnnounced },
}

export function batchTargets(volumes: VolumeEntry[], action: VolumeBatchAction): VolumeEntry[] {
  return volumes.filter(VOLUME_BATCH_RULES[action].applies)
}
