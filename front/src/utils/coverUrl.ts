export function coverUrl(url: string | null | undefined): string | null {
  if (!url) return null
  if (url.startsWith('https://books.google')) {
    return `/proxy/cover?url=${encodeURIComponent(url)}`
  }
  return url
}
