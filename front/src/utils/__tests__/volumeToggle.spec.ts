import { describe, it, expect } from 'vitest'
import type { VolumeEntry } from '@/types'
import { applyVolumeToggle, volumeCounts } from '../volumeToggle'

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

describe('applyVolumeToggle', () => {
  it('owning a tome takes it off the wishlist and the announced ones', () => {
    const toggled = applyVolumeToggle(volume(1, { isWished: true, isAnnounced: true }), 'isOwned')

    expect(toggled).toMatchObject({ isOwned: true, isWished: false, isAnnounced: false })
  })

  it('giving a tome back keeps its other flags', () => {
    const toggled = applyVolumeToggle(volume(1, { isOwned: true, isRead: true }), 'isOwned')

    expect(toggled).toMatchObject({ isOwned: false, isRead: true })
  })

  it('flips the read, wished and announced flags on their own', () => {
    expect(applyVolumeToggle(volume(1, { isOwned: true }), 'isRead').isRead).toBe(true)
    expect(applyVolumeToggle(volume(1, { isWished: true }), 'isWished').isWished).toBe(false)
    expect(applyVolumeToggle(volume(1), 'isAnnounced').isAnnounced).toBe(true)
  })

  it('never mutates the tome it is given', () => {
    const original = volume(1)

    applyVolumeToggle(original, 'isOwned')

    expect(original.isOwned).toBe(false)
  })
})

describe('volumeCounts', () => {
  it('counts owned, read and still-missing wished tomes and sums the owned prices', () => {
    const counts = volumeCounts([
      volume(1, { isOwned: true, isRead: true, price: 7.5 }),
      volume(2, { isOwned: true, price: null }),
      volume(3, { isWished: true, price: 9 }),
      // A wished flag left on an owned tome is not a wish anymore.
      volume(4, { isOwned: true, isWished: true, price: 2.5 }),
    ])

    expect(counts).toEqual({ ownedCount: 3, readCount: 1, wishedCount: 1, ownedValue: 10 })
  })

  it('is all zeros for a series without tomes', () => {
    expect(volumeCounts([])).toEqual({ ownedCount: 0, readCount: 0, wishedCount: 0, ownedValue: 0 })
  })
})
