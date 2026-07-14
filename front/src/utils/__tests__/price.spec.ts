import { describe, it, expect } from 'vitest'
import { formatPrice } from '../price'

describe('formatPrice', () => {
  it('formats a known currency with its symbol', () => {
    expect(formatPrice(7.2, 'EUR')).toBe('7.20 €')
    expect(formatPrice(12, 'USD')).toBe('12.00 $')
  })

  it('falls back to the currency code when unknown', () => {
    expect(formatPrice(1500, 'SEK')).toBe('1500.00 SEK')
  })
})
