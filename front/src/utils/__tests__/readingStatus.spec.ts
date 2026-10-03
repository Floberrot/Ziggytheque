import { describe, it, expect } from 'vitest'
import fr from '@/i18n/fr.json'
import en from '@/i18n/en.json'
import { READING_STATUSES, READING_STATUS_CHIPS, READING_STATUS_DOTS, readingStatusLabelKey } from '../readingStatus'

describe('reading statuses', () => {
  it('lists the five statuses once, in the menu order', () => {
    expect(READING_STATUSES).toEqual(['not_started', 'in_progress', 'on_hold', 'completed', 'dropped'])
  })

  it('labels every status from the one shared vocabulary, in both languages', () => {
    for (const status of READING_STATUSES) {
      expect(readingStatusLabelKey(status)).toBe(`status.${status}`)
      expect(fr.status[status]).toBeTruthy()
      expect(en.status[status]).toBeTruthy()
    }
    expect(fr.status.not_started).toBe('À lire')
    expect(fr.status.on_hold).toBe('En pause')
    expect(fr.status.completed).toBe('Terminé')
  })

  it('gives every status a menu dot, and a card chip to every status but "in progress"', () => {
    for (const status of READING_STATUSES) {
      expect(READING_STATUS_DOTS[status]).toMatch(/^bg-/)
    }
    expect(Object.keys(READING_STATUS_CHIPS).sort()).toEqual(['completed', 'dropped', 'not_started', 'on_hold'])
  })
})
