/**
 * A link we render ourselves (articles, price offers) comes from a feed or a scraped
 * page: only http(s) may reach an href — never `javascript:` or `data:`.
 */
export function safeUrl(url: string | null | undefined): string | undefined {
  if (!url) return undefined
  try {
    const parsed = new URL(url.trim())
    return parsed.protocol === 'http:' || parsed.protocol === 'https:' ? parsed.href : undefined
  } catch {
    return undefined
  }
}
