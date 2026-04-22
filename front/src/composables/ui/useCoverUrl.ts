import { computed } from 'vue'

export function useCoverUrl(url?: string | null) {
  return computed(() => {
    if (!url) return undefined
    if (url.startsWith('http')) return url
    return `${import.meta.env.VITE_BACKEND_URL}${url}`
  })
}
