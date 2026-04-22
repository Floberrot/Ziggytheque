import { useMediaQuery } from '@vueuse/core'
import { computed } from 'vue'

export function useBreakpoint() {
  const sm = useMediaQuery('(min-width: 640px)')
  const md = useMediaQuery('(min-width: 768px)')
  const lg = useMediaQuery('(min-width: 1024px)')
  const xl = useMediaQuery('(min-width: 1280px)')
  const twoXl = useMediaQuery('(min-width: 1536px)')

  const isMobile = computed(() => !sm.value)
  const isTablet = computed(() => sm.value && !lg.value)
  const isDesktop = computed(() => lg.value)

  return {
    sm,
    md,
    lg,
    xl,
    twoXl,
    isMobile,
    isTablet,
    isDesktop,
  }
}
