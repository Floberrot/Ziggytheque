import { describe, it, expect } from 'vitest'
import { normalizeIsbn13 } from '../isbn'

describe('normalizeIsbn13', () => {
  // 9782723425483 and 2723425487 are checksum-valid (Berserk tome 1 FR)
  const ISBN13 = '9782723425483'
  const ISBN10 = '2723425487'

  it('accepts a bare valid ISBN-13', () => {
    expect(normalizeIsbn13(ISBN13)).toBe(ISBN13)
  })

  it('strips hyphens and spaces', () => {
    expect(normalizeIsbn13('978-2-7234-2548-3')).toBe(ISBN13)
    expect(normalizeIsbn13(' 978 2 7234 2548 3 ')).toBe(ISBN13)
  })

  it('accepts a 979-prefixed ISBN-13', () => {
    expect(normalizeIsbn13('9791034701360')).toBe('9791034701360')
  })

  it('converts a valid ISBN-10 to ISBN-13', () => {
    expect(normalizeIsbn13(ISBN10)).toBe(ISBN13)
  })

  it('converts an ISBN-10 with X check digit', () => {
    // 048665088X — checksum-valid
    expect(normalizeIsbn13('048665088X')).toBe('9780486650883')
    expect(normalizeIsbn13('048665088x')).toBe('9780486650883')
  })

  it('converts a hyphenated ISBN-10', () => {
    expect(normalizeIsbn13('2-7234-2548-7')).toBe(ISBN13)
  })

  it('drops an EAN-5 barcode add-on', () => {
    expect(normalizeIsbn13(ISBN13 + '12345')).toBe(ISBN13)
  })

  it('drops an EAN-2 barcode add-on', () => {
    expect(normalizeIsbn13(ISBN13 + '99')).toBe(ISBN13)
  })

  it('still validates the checksum when an add-on is dropped', () => {
    expect(normalizeIsbn13('9782723425484' + '12345')).toBeNull()
  })

  it('rejects an ISBN-13 with a wrong check digit', () => {
    expect(normalizeIsbn13('9782723425484')).toBeNull()
  })

  it('rejects an ISBN-10 with a wrong check digit', () => {
    expect(normalizeIsbn13('2723425480')).toBeNull()
  })

  it('rejects a 13-digit code without a 978/979 prefix', () => {
    expect(normalizeIsbn13('1234567890128')).toBeNull()
  })

  it('rejects invalid characters', () => {
    expect(normalizeIsbn13('97827A3425483')).toBeNull()
    expect(normalizeIsbn13('not-an-isbn')).toBeNull()
  })

  it('rejects wrong lengths', () => {
    expect(normalizeIsbn13('')).toBeNull()
    expect(normalizeIsbn13('978272342548')).toBeNull()
    expect(normalizeIsbn13('97827234254831')).toBeNull()
  })
})
