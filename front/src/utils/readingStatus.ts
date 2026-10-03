import type { ReadingStatus } from '@/types'

/** Every reading status of a series, in the order the series page offers them. */
export const READING_STATUSES: readonly ReadingStatus[] = [
  'not_started',
  'in_progress',
  'on_hold',
  'completed',
  'dropped',
]

/**
 * The i18n key of a status label. One vocabulary for the whole app (series menu,
 * collection cards, quick filters, dashboard breakdown): `status.<value>`.
 */
export function readingStatusLabelKey(status: ReadingStatus): string {
  return `status.${status}`
}

/** Colour dot of each status in the series status menu (static literals for Tailwind). */
export const READING_STATUS_DOTS: Record<ReadingStatus, string> = {
  not_started: 'bg-base-content/40',
  in_progress: 'bg-primary',
  on_hold: 'bg-warning',
  completed: 'bg-success',
  dropped: 'bg-error',
}

/** Chip of a collection card: only the statuses worth flagging ("in progress" is the norm). */
export const READING_STATUS_CHIPS: Partial<Record<ReadingStatus, string>> = {
  dropped: 'bg-error/20 text-error border border-error/30 backdrop-blur-sm',
  on_hold: 'bg-warning/20 text-warning border border-warning/30 backdrop-blur-sm',
  not_started: 'bg-base-content/8 text-base-content/40 border border-base-content/12 backdrop-blur-sm',
  completed: 'bg-success/20 text-success border border-success/30 backdrop-blur-sm',
}
