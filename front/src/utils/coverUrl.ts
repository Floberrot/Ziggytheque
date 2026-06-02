// Hosts that serve an anti-hotlink placeholder ("You can read this at…") unless
// the image is fetched server-side with the right Referer. Route them through the
// backend cover proxy so the real cover is displayed.
const PROXIED_HOSTS = ['https://books.google', 'https://uploads.mangadex.org/']

export function coverUrl(url: string | null | undefined): string | null {
  if (!url) return null
  if (PROXIED_HOSTS.some((host) => url.startsWith(host))) {
    return `/proxy/cover?url=${encodeURIComponent(url)}`
  }
  return url
}
