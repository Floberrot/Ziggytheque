import { describe, it, expect } from 'vitest'
import { coverSourceLabel } from '../coverSource'

describe('coverSourceLabel', () => {
  it('names the known cover sources', () => {
    expect(coverSourceLabel('bnf')).toBe('BnF')
    expect(coverSourceLabel('open_library')).toBe('Open Library')
    expect(coverSourceLabel('google_books')).toBe('Google Books')
    expect(coverSourceLabel('mangadex')).toBe('MangaDex')
  })

  it('shows an unknown source as the API sent it', () => {
    expect(coverSourceLabel('kitsu')).toBe('kitsu')
  })
})
