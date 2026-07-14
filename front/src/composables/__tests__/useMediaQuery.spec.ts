import { describe, it, expect, vi, beforeEach } from 'vitest'
import { effectScope } from 'vue'
import { useMediaQuery, useIsMobile } from '../useMediaQuery'

type ChangeListener = (event: { matches: boolean }) => void

function stubMatchMedia(initialMatches: boolean) {
  const listeners: ChangeListener[] = []
  const mediaQueryList = {
    matches: initialMatches,
    addEventListener: vi.fn((_type: string, listener: ChangeListener) => listeners.push(listener)),
    removeEventListener: vi.fn((_type: string, listener: ChangeListener) => {
      const index = listeners.indexOf(listener)
      if (index !== -1) listeners.splice(index, 1)
    }),
  }
  const matchMedia = vi.fn(() => mediaQueryList)
  vi.stubGlobal('matchMedia', matchMedia)
  return {
    matchMedia,
    fireChange(matches: boolean) {
      listeners.forEach((listener) => listener({ matches }))
    },
    mediaQueryList,
  }
}

describe('useMediaQuery', () => {
  beforeEach(() => {
    vi.unstubAllGlobals()
  })

  it('reflects the initial match state', () => {
    stubMatchMedia(true)
    const matches = useMediaQuery('(max-width: 639px)')
    expect(matches.value).toBe(true)
  })

  it('updates when the media query changes', () => {
    const stub = stubMatchMedia(false)
    const matches = useMediaQuery('(max-width: 639px)')
    expect(matches.value).toBe(false)
    stub.fireChange(true)
    expect(matches.value).toBe(true)
  })

  it('removes its listener when the effect scope is disposed', () => {
    const stub = stubMatchMedia(false)
    const scope = effectScope()
    scope.run(() => useMediaQuery('(max-width: 639px)'))
    scope.stop()
    expect(stub.mediaQueryList.removeEventListener).toHaveBeenCalled()
  })

  it('falls back to false when matchMedia is unavailable', () => {
    vi.stubGlobal('matchMedia', undefined)
    const matches = useMediaQuery('(max-width: 639px)')
    expect(matches.value).toBe(false)
  })

  it('useIsMobile queries the sm breakpoint', () => {
    const stub = stubMatchMedia(true)
    const isMobile = useIsMobile()
    expect(stub.matchMedia).toHaveBeenCalledWith('(max-width: 639px)')
    expect(isMobile.value).toBe(true)
  })
})
