import { describe, it, expect } from 'vitest'
import { seedColorInt, hslToInt } from '../bookTextures'

describe('bookTextures colour helpers', () => {
  it('seedColorInt is deterministic for the same input', () => {
    expect(seedColorInt('berserk-1')).toBe(seedColorInt('berserk-1'))
  })

  it('seedColorInt stays within the 24-bit RGB range', () => {
    for (const seed of ['a', 'manga-42', 'Lorem ipsum dolor', '']) {
      const colour = seedColorInt(seed)
      expect(colour).toBeGreaterThanOrEqual(0)
      expect(colour).toBeLessThanOrEqual(0xffffff)
      expect(Number.isInteger(colour)).toBe(true)
    }
  })

  it('different seeds generally yield different colours', () => {
    expect(seedColorInt('one')).not.toBe(seedColorInt('two'))
  })

  it('hslToInt returns a 24-bit integer for boundary inputs', () => {
    const cases: [number, number, number][] = [
      [0, 0, 0],
      [0, 1, 0.5],
      [1, 1, 1],
      [0.5, 0.68, 0.46],
    ]
    for (const [h, s, l] of cases) {
      const colour = hslToInt(h, s, l)
      expect(Number.isInteger(colour)).toBe(true)
      expect(colour).toBeGreaterThanOrEqual(0)
      expect(colour).toBeLessThanOrEqual(0xffffff)
    }
  })
})
