import { describe, it, expect } from 'vitest'
import type { CollectionEntry } from '@/types'
import { foldTitle, groupByWork } from '../workGroups'

function entry(id: string, title: string, author: string | null = null): CollectionEntry {
  return { id, manga: { title, author } } as unknown as CollectionEntry
}

function ids(groups: ReturnType<typeof groupByWork>): string[][] {
  return groups.map((group) => group.entries.map((item) => item.id))
}

describe('foldTitle', () => {
  it('ignores accents, case and punctuation', () => {
    expect(foldTitle("L'Attaque des Titans")).toBe('l attaque des titans')
    expect(foldTitle('  Kentarō  MIURA ')).toBe('kentaro miura')
    expect(foldTitle(null)).toBe('')
  })
})

describe('groupByWork', () => {
  it('gathers the editions of one work, even when they are not adjacent', () => {
    const groups = groupByWork(
      [entry('a', 'Berserk'), entry('b', 'berserk '), entry('c', 'Naruto'), entry('d', 'Bersérk')],
      true,
    )

    expect(ids(groups)).toEqual([['a', 'b', 'd'], ['c']])
    expect(groups.map((group) => group.key)).toEqual(['a', 'c'])
    expect(groups[0].title).toBe('Berserk')
  })

  it('gathers legacy titles that start with the work, by the same author', () => {
    const groups = groupByWork(
      [
        entry('a', 'Attaque des titans', 'Hajime Isayama'),
        entry('b', 'Attaque des titans - Colossale', 'Isayama, Hajime'),
        entry('c', 'Berserk', 'Kentaro Miura'),
        entry('d', 'Berserk Collection', null),
      ],
      true,
    )

    expect(ids(groups)).toEqual([['a', 'b'], ['c', 'd']])
  })

  it('keeps another work that only shares the first words apart', () => {
    const groups = groupByWork(
      [entry('a', 'Monster', 'Naoki Urasawa'), entry('b', 'Monster Musume', 'Okayado'), entry('c', 'Berserker Saga')],
      true,
    )

    expect(ids(groups)).toEqual([['a'], ['b'], ['c']])
  })

  it('does not gather under a too short title', () => {
    expect(ids(groupByWork([entry('a', 'Ao'), entry('b', 'Ao Haru Ride')], true))).toEqual([['a'], ['b']])
  })

  it('keeps every entry apart when grouping is off', () => {
    expect(groupByWork([entry('a', 'Berserk'), entry('b', 'Berserk')], false)).toHaveLength(2)
  })

  it('returns no group for no entry', () => {
    expect(groupByWork([], true)).toEqual([])
  })
})
