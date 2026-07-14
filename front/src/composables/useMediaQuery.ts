import { onScopeDispose, ref, type Ref } from 'vue'

/**
 * Reactive CSS media-query matcher.
 *
 * Returns a ref that stays in sync with `window.matchMedia(query)`, so
 * components can branch between mobile and desktop presentations
 * (e.g. bottom sheet vs. anchored popover).
 */
export function useMediaQuery(query: string): Ref<boolean> {
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
    return ref(false)
  }

  const mediaQueryList = window.matchMedia(query)
  const matches = ref(mediaQueryList.matches)

  function onChange(event: MediaQueryListEvent): void {
    matches.value = event.matches
  }

  mediaQueryList.addEventListener('change', onChange)
  onScopeDispose(() => mediaQueryList.removeEventListener('change', onChange))

  return matches
}

/**
 * True below Tailwind's `sm` breakpoint (640px) — the threshold every
 * modal/popover in the app uses to switch to its bottom-sheet layout.
 */
export function useIsMobile(): Ref<boolean> {
  return useMediaQuery('(max-width: 639px)')
}
