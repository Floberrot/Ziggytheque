import { describe, it, expect } from 'vitest'
import type { CollectionEntry } from '@/types'
import { groupAdjacentByWork } from '../workGroups'

function entry(id: string, title: string): CollectionEntry {
  return { id, manga: { title } } as unknown as CollectionEntry
}

describe('groupAdjacentByWork', () => {
  it('groups adjacent editions of the same work, case-insensitively', () => {
    const groups = groupAdjacentByWork(
      [entry('a', 'Berserk'), entry('b', 'berserk '), entry('c', 'Naruto'), entry('d', 'Berserk')],
      true,
    )

    expect(groups.map((group) => group.entries.map((item) => item.id))).toEqual([['a', 'b'], ['c'], ['d']])
    expect(groups.map((group) => group.key)).toEqual(['a', 'c', 'd'])
    expect(groups[0].title).toBe('Berserk')
  })

  it('keeps every entry apart when grouping is off', () => {
    const groups = groupAdjacentByWork([entry('a', 'Berserk'), entry('b', 'Berserk')], false)

    expect(groups).toHaveLength(2)
  })

  it('returns no group for no entry', () => {
    expect(groupAdjacentByWork([], true)).toEqual([])
  })
})
