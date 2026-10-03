import { describe, it, expect } from 'vitest'
import type { RouteLocationNormalizedLoaded } from 'vue-router'
import { scrollBehavior } from '../scrollBehavior'

function route(path: string, meta: Record<string, unknown> = {}): RouteLocationNormalizedLoaded {
  return { path, meta } as unknown as RouteLocationNormalizedLoaded
}

function scrollFor(
  to: RouteLocationNormalizedLoaded,
  from: RouteLocationNormalizedLoaded,
  savedPosition: { left: number; top: number } | null = null,
) {
  return scrollBehavior(to, from, savedPosition)
}

describe('router scrollBehavior', () => {
  it('leaves a list page that remembers its place to restore it itself', () => {
    const collection = route('/collection', { rememberScroll: true })

    expect(scrollFor(collection, route('/collection/42'))).toBe(false)
    expect(scrollFor(collection, route('/collection/42'), { left: 0, top: 900 })).toBe(false)
  })

  it('goes back to where the reader was on back / forward', () => {
    const saved = { left: 0, top: 640 }

    expect(scrollFor(route('/collection/42'), route('/add'), saved)).toEqual(saved)
  })

  it('stays put when only the query changes (a tab of the series page)', () => {
    expect(scrollFor(route('/collection/42'), route('/collection/42'))).toBe(false)
  })

  it('opens any other page at its top', () => {
    expect(scrollFor(route('/collection/42'), route('/collection', { rememberScroll: true }))).toEqual({
      top: 0,
    })
    expect(scrollFor(route('/dashboard'), route('/wishlist'))).toEqual({ top: 0 })
  })
})
