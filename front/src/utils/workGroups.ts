import type { CollectionEntry } from '@/types'

export interface WorkGroup {
  /** Render key — unique even when a work shows up twice. */
  key: string
  title: string
  entries: CollectionEntry[]
}

/**
 * Folds adjacent entries of the same work (case-insensitive title) into one group,
 * so the editions of a work — sorted next to each other by the API — are shown
 * together. With `enabled` false every entry is its own group.
 */
export function groupAdjacentByWork(entries: CollectionEntry[], enabled: boolean): WorkGroup[] {
  const groups: (WorkGroup & { workKey: string })[] = []
  for (const entry of entries) {
    const workKey = entry.manga.title.trim().toLocaleLowerCase()
    const current = groups[groups.length - 1]
    if (enabled && current && current.workKey === workKey) {
      current.entries.push(entry)
    } else {
      groups.push({ key: entry.id, workKey, title: entry.manga.title, entries: [entry] })
    }
  }
  return groups.map(({ key, title, entries: groupEntries }) => ({ key, title, entries: groupEntries }))
}
