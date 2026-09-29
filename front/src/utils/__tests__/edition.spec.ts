import { describe, it, expect } from 'vitest'
import { editionLabel } from '../edition'

describe('editionLabel', () => {
  it('joins the publisher and the special edition', () => {
    expect(editionLabel('Glénat', 'Prestige')).toBe('Glénat · Prestige')
  })

  it('shows only what is known', () => {
    expect(editionLabel('Glénat', null)).toBe('Glénat')
    expect(editionLabel(null, 'Prestige')).toBe('Prestige')
    expect(editionLabel(undefined, '  ')).toBe('')
  })
})
