const CURRENCY_SYMBOLS: Record<string, string> = {
  EUR: '€',
  USD: '$',
  GBP: '£',
  JPY: '¥',
}

/** Formats a price amount with its currency symbol (e.g. "7.20 €"). */
export function formatPrice(amount: number, currency: string): string {
  const symbol = CURRENCY_SYMBOLS[currency] ?? currency
  return amount.toFixed(2) + ' ' + symbol
}
