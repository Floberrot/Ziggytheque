import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const THEMES = ['ziggy-dark', 'ziggy-light'] as const

export type Theme = (typeof THEMES)[number]

const STORAGE_KEY = 'theme'
const DEFAULT_THEME: Theme = 'ziggy-dark'

function isTheme(value: string | null): value is Theme {
  return value !== null && (THEMES as readonly string[]).includes(value)
}

/**
 * Reads the persisted theme. Any value from the old theme list (dracula, cupcake…)
 * falls back to Ziggy Dark, so a removed theme never leaves the UI unstyled.
 */
function loadTheme(): Theme {
  try {
    const stored = localStorage.getItem(STORAGE_KEY)
    return isTheme(stored) ? stored : DEFAULT_THEME
  } catch {
    // localStorage may be unavailable (private mode) — fall back to the default.
    return DEFAULT_THEME
  }
}

export const useThemeStore = defineStore('theme', () => {
  const theme = ref<Theme>(loadTheme())

  const isDark = computed(() => theme.value === 'ziggy-dark')

  function setTheme(next: Theme): void {
    theme.value = next
    try {
      localStorage.setItem(STORAGE_KEY, next)
    } catch {
      // Ignore persistence failures — the choice still applies for this session.
    }
  }

  function toggle(): void {
    setTheme(isDark.value ? 'ziggy-light' : 'ziggy-dark')
  }

  return { theme, isDark, setTheme, toggle }
})
