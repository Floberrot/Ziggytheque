import type { CollectionEntry } from '@/types'

export interface WorkGroup {
  /** Render key — the id of the group's first entry. */
  key: string
  title: string
  entries: CollectionEntry[]
}

/** A work title this short is too vague to gather other titles under it ("Ao", "Nana"…). */
const MIN_HEAD_LENGTH = 3

/** Accent-, case- and punctuation-insensitive form, as the backend's TextFold. */
export function foldTitle(value: string | null | undefined): string {
  return (value ?? '')
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLocaleLowerCase()
    .replace(/[^\p{L}\p{N}]+/gu, ' ')
    .trim()
}

/** Same author, or one side does not say: "Miura, Kentarō" and "Kentaro Miura" agree. */
function sameAuthor(left: string | null, right: string | null): boolean {
  const leftWords = foldTitle(left).split(' ').filter((word) => word.length >= 3)
  const rightWords = new Set(foldTitle(right).split(' ').filter((word) => word.length >= 3))
  if (leftWords.length === 0 || rightWords.size === 0) return true
  return leftWords.some((word) => rightWords.has(word))
}

/**
 * Gathers the editions of one work into one group, so the collection shows one
 * card per work. Same title (accents and case aside) is the same work; a title that
 * starts with another one, word for word, by the same author, is too — the
 * "Berserk Collection" or "Attaque des titans - Colossale" typed before special
 * editions existed. Groups keep the order of their first entry; with `enabled`
 * false every entry is its own group.
 */
export function groupByWork(entries: CollectionEntry[], enabled: boolean): WorkGroup[] {
  const groups: (WorkGroup & { workKey: string })[] = []
  const groupsByKey = new Map<string, WorkGroup & { workKey: string }>()

  for (const entry of entries) {
    const workKey = foldTitle(entry.manga.title)
    const group = enabled ? findGroup(workKey, entry, groupsByKey) : undefined
    if (group) {
      group.entries.push(entry)
      continue
    }

    const created = { key: entry.id, workKey, title: entry.manga.title.trim(), entries: [entry] }
    groups.push(created)
    if (enabled && !groupsByKey.has(workKey)) groupsByKey.set(workKey, created)
  }

  return groups.map(({ key, title, entries: groupEntries }) => ({ key, title, entries: groupEntries }))
}

function findGroup(
  workKey: string,
  entry: CollectionEntry,
  groupsByKey: Map<string, WorkGroup & { workKey: string }>,
): (WorkGroup & { workKey: string }) | undefined {
  const exact = groupsByKey.get(workKey)
  if (exact) return exact

  // The shortest earlier title this one starts with, word for word.
  const words = workKey.split(' ')
  for (let length = 1; length < words.length; length++) {
    const head = words.slice(0, length).join(' ')
    const group = head.length >= MIN_HEAD_LENGTH ? groupsByKey.get(head) : undefined
    if (group && sameAuthor(group.entries[0].manga.author, entry.manga.author)) return group
  }
  return undefined
}
