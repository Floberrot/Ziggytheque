import { describe, it, expect, beforeEach } from 'vitest'
import { nextTick } from 'vue'
import { useCoverProvider, COVER_PROVIDERS } from '../useCoverProvider'

describe('useCoverProvider', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  it('defaults to the composite (auto) provider', () => {
    const { provider } = useCoverProvider()
    expect(provider.value).toBe('composite')
  })

  it('restores a persisted provider', () => {
    localStorage.setItem('ziggytheque.coverProvider', 'mangadex')
    const { provider } = useCoverProvider()
    expect(provider.value).toBe('mangadex')
  })

  it('ignores an unknown persisted provider', () => {
    localStorage.setItem('ziggytheque.coverProvider', 'nope')
    const { provider } = useCoverProvider()
    expect(provider.value).toBe('composite')
  })

  it('persists the provider when it changes', async () => {
    const { provider } = useCoverProvider()
    provider.value = 'googlebooks'
    await nextTick()
    expect(localStorage.getItem('ziggytheque.coverProvider')).toBe('googlebooks')
  })

  it('only offers the title-capable sources (Auto, MangaDex, Google Books)', () => {
    expect(COVER_PROVIDERS.map((option) => option.key)).toEqual([
      'composite',
      'mangadex',
      'googlebooks',
    ])
  })
})
