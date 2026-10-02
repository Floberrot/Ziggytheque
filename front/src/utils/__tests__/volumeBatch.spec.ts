import { describe, it, expect } from 'vitest'
import type { VolumeEntry } from '@/types'
import { VOLUME_BATCH_RULES, batchTargets } from '../volumeBatch'

function volume(number: number, flags: Partial<VolumeEntry> = {}): VolumeEntry {
  return {
    id: `ve-${number}`,
    volumeId: `v-${number}`,
    number,
    coverUrl: null,
    price: null,
    isOwned: false,
    isRead: false,
    isWished: false,
    isAnnounced: false,
    review: null,
    rating: null,
    isbn: null,
    ...flags,
  }
}

const selection = [
  volume(1, { isOwned: true, isRead: true }),
  volume(2, { isOwned: true }),
  volume(3),
  volume(4, { isWished: true }),
  volume(5, { isAnnounced: true }),
]

function numbers(volumes: VolumeEntry[]): number[] {
  return volumes.map((item) => item.number)
}

describe('batchTargets', () => {
  it('marks read only the owned tomes not read yet — never unreads the read ones', () => {
    expect(numbers(batchTargets(selection, 'markRead'))).toEqual([2])
    expect(VOLUME_BATCH_RULES.markRead.field).toBe('isRead')
  })

  it('marks unread only the read tomes', () => {
    expect(numbers(batchTargets(selection, 'markUnread'))).toEqual([1])
  })

  it('marks owned only the tomes not owned yet', () => {
    expect(numbers(batchTargets(selection, 'markOwned'))).toEqual([3, 4, 5])
  })

  it('wishes only the missing tomes not wished yet', () => {
    expect(numbers(batchTargets(selection, 'wish'))).toEqual([3, 5])
  })

  it('announces and unannounces only the missing tomes in the other state', () => {
    expect(numbers(batchTargets(selection, 'announce'))).toEqual([3, 4])
    expect(numbers(batchTargets(selection, 'unannounce'))).toEqual([5])
  })

  it('targets nothing when every tome is already in the wanted state', () => {
    expect(batchTargets([volume(1, { isOwned: true, isRead: true })], 'markRead')).toEqual([])
  })
})
