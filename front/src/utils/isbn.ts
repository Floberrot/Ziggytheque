/**
 * Shared ISBN normalization — mirrors the backend `Isbn` value object so what the
 * front auto-saves is exactly what the API will store.
 *
 * Accepts ISBN-13 and ISBN-10 (converted to ISBN-13), tolerates hyphens, spaces and
 * a trailing EAN-2/EAN-5 barcode add-on. Returns the canonical 13-digit string, or
 * `null` when the input is not a valid ISBN.
 */
export function normalizeIsbn13(raw: string): string | null {
  let stripped = raw.trim().toUpperCase().replace(/[-\s]/g, '')

  if (!/^[0-9]+X?$/.test(stripped)) return null

  // Barcode scanners may append an EAN-2 or EAN-5 add-on after the 13 digits.
  const addOnMatch = stripped.match(/^(97[89][0-9]{10})[0-9]{2}(?:[0-9]{3})?$/)
  if (addOnMatch) stripped = addOnMatch[1]

  if (stripped.length === 13) {
    if (!stripped.startsWith('978') && !stripped.startsWith('979')) return null
    return isValidIsbn13Checksum(stripped) ? stripped : null
  }

  if (stripped.length === 10) {
    if (!isValidIsbn10Checksum(stripped)) return null
    const first12 = '978' + stripped.slice(0, 9)
    return first12 + isbn13CheckDigit(first12)
  }

  return null
}

function isValidIsbn13Checksum(isbn13: string): boolean {
  return isbn13CheckDigit(isbn13.slice(0, 12)) === isbn13[12]
}

function isbn13CheckDigit(first12: string): string {
  let sum = 0
  for (let position = 0; position < 12; position++) {
    const digit = Number(first12[position])
    sum += position % 2 === 0 ? digit : digit * 3
  }
  return String((10 - (sum % 10)) % 10)
}

function isValidIsbn10Checksum(isbn10: string): boolean {
  let sum = 0
  for (let position = 0; position < 9; position++) {
    sum += Number(isbn10[position]) * (10 - position)
  }
  const lastChar = isbn10[9]
  sum += lastChar === 'X' ? 10 : Number(lastChar)
  return sum % 11 === 0
}
