import { describe, expect, it } from 'vitest'
import { coverUrl, isPlaceholderImage } from '@/utils/coverUrl'

describe('coverUrl', () => {
  it('returns null without a url', () => {
    expect(coverUrl(null)).toBeNull()
    expect(coverUrl('')).toBeNull()
  })

  it('routes anti-hotlink hosts through the cover proxy', () => {
    const bnf = 'https://catalogue.bnf.fr/couverture?appName=NE&idArk=ark:/12148/cb1&couverture=1'
    expect(coverUrl(bnf)).toBe(`/proxy/cover?url=${encodeURIComponent(bnf)}`)
    expect(coverUrl('https://books.google.com/books/content?id=1')).toMatch(/^\/proxy\/cover\?url=/)
  })

  it('upgrades http links before deciding', () => {
    expect(coverUrl('http://books.google.com/x')).toBe(`/proxy/cover?url=${encodeURIComponent('https://books.google.com/x')}`)
    expect(coverUrl('http://example.org/cover.jpg')).toBe('https://example.org/cover.jpg')
  })

  it('leaves other hosts alone', () => {
    expect(coverUrl('https://covers.openlibrary.org/b/isbn/1-L.jpg')).toBe('https://covers.openlibrary.org/b/isbn/1-L.jpg')
  })
})

describe('isPlaceholderImage', () => {
  it('flags tracking pixels and tiny placeholders', () => {
    expect(isPlaceholderImage({ naturalWidth: 1, naturalHeight: 1 })).toBe(true)
    expect(isPlaceholderImage({ naturalWidth: 300, naturalHeight: 20 })).toBe(true)
  })

  it('keeps real covers', () => {
    expect(isPlaceholderImage({ naturalWidth: 128, naturalHeight: 192 })).toBe(false)
  })
})
