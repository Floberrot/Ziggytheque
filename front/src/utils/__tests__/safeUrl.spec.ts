import { describe, it, expect } from 'vitest'
import { safeUrl } from '../safeUrl'

describe('safeUrl', () => {
  it('keeps http and https links', () => {
    expect(safeUrl('https://www.manga-news.com/actus')).toBe('https://www.manga-news.com/actus')
    expect(safeUrl('http://example.org/a')).toBe('http://example.org/a')
  })

  it('drops script and data links', () => {
    expect(safeUrl('javascript:alert(1)')).toBeUndefined()
    expect(safeUrl(' JavaScript:alert(1)')).toBeUndefined()
    expect(safeUrl('data:text/html,<script>alert(1)</script>')).toBeUndefined()
  })

  it('drops relative, empty and malformed values', () => {
    expect(safeUrl('/actus')).toBeUndefined()
    expect(safeUrl('')).toBeUndefined()
    expect(safeUrl(null)).toBeUndefined()
    expect(safeUrl('not a url')).toBeUndefined()
  })
})
