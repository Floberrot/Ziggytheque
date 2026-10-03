import { describe, it, expect } from 'vitest'
import type { VolumeEntry } from '@/types'
import { volumeHeadlineStatus, volumeOpacityClass, volumeRingClass } from '../volumeStyles'

function volume(flags: Partial<VolumeEntry> = {}): VolumeEntry {
  return {
    id: 've-1',
    volumeId: 'v-1',
    number: 1,
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

describe('volumeRingClass', () => {
  it('colours a tome by its status, read before owned', () => {
    expect(volumeRingClass(volume({ isOwned: true, isRead: true }))).toBe('ring-info/80')
    expect(volumeRingClass(volume({ isOwned: true }))).toBe('ring-success/70')
    expect(volumeRingClass(volume({ isWished: true }))).toBe('ring-warning/60')
    expect(volumeRingClass(volume({ isAnnounced: true }))).toBe('ring-secondary/60')
    expect(volumeRingClass(volume())).toBe('ring-base-300/30')
  })

  it('shows the batch selection over the status', () => {
    expect(volumeRingClass(volume({ isOwned: true }), true)).toBe('ring-primary')
  })
})

describe('volumeHeadlineStatus', () => {
  it('sums a tome up by one status, an announced tome first unless owned', () => {
    expect(volumeHeadlineStatus(volume({ isAnnounced: true, isWished: true }))).toBe('announced')
    expect(volumeHeadlineStatus(volume({ isAnnounced: true, isOwned: true }))).toBe('owned')
    expect(volumeHeadlineStatus(volume({ isWished: true }))).toBe('wished')
    expect(volumeHeadlineStatus(volume())).toBe('none')
  })
})

describe('volumeOpacityClass', () => {
  it('fades the tomes the user does not own', () => {
    expect(volumeOpacityClass(volume({ isOwned: true, isWished: true }))).toBe('opacity-100')
    expect(volumeOpacityClass(volume({ isWished: true }))).toBe('opacity-65')
    expect(volumeOpacityClass(volume({ isAnnounced: true }))).toBe('opacity-60')
    expect(volumeOpacityClass(volume())).toBe('opacity-25 grayscale')
  })
})
