// Hosts that serve an anti-hotlink placeholder ("You can read this at…", a blank
// BnF image) unless the image is fetched server-side with the right Referer. Route
// them through the backend cover proxy so the real cover — or a clean 404 — comes back.
const PROXIED_HOSTS = ['https://books.google', 'https://uploads.mangadex.org/', 'https://catalogue.bnf.fr/']

/** Narrower than this, a "cover" is a tracking pixel or a "no cover" placeholder. */
const MIN_COVER_SIDE_PX = 40

export function coverUrl(url: string | null | undefined): string | null {
  if (!url) return null
  // Old records kept http:// links, which an https page refuses to load.
  const secureUrl = url.startsWith('http://') ? `https://${url.slice('http://'.length)}` : url
  if (PROXIED_HOSTS.some((host) => secureUrl.startsWith(host))) {
    return `/proxy/cover?url=${encodeURIComponent(secureUrl)}`
  }
  return secureUrl
}

export function isPlaceholderImage(image: { naturalWidth: number; naturalHeight: number }): boolean {
  return image.naturalWidth < MIN_COVER_SIDE_PX || image.naturalHeight < MIN_COVER_SIDE_PX
}
