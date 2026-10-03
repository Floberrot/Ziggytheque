/** The languages of the app — one JSON file each, next to this module. */
export const SUPPORTED_LOCALES = ['fr', 'en'] as const

export type AppLocale = (typeof SUPPORTED_LOCALES)[number]

const STORAGE_KEY = 'locale'
const DEFAULT_LOCALE: AppLocale = 'fr'

export function isAppLocale(value: unknown): value is AppLocale {
  return typeof value === 'string' && (SUPPORTED_LOCALES as readonly string[]).includes(value)
}

/** The language picked in the settings — French until the user picks one. */
export function loadLocale(): AppLocale {
  try {
    const stored = localStorage.getItem(STORAGE_KEY)
    return isAppLocale(stored) ? stored : DEFAULT_LOCALE
  } catch {
    // localStorage may be unavailable (private mode): fall back to the default.
    return DEFAULT_LOCALE
  }
}

/**
 * Remembers the language for the next visits and tells the browser (screen readers,
 * hyphenation, spell check) which language the page is in.
 */
export function persistLocale(locale: AppLocale): void {
  try {
    localStorage.setItem(STORAGE_KEY, locale)
  } catch {
    // Ignore persistence failures: the choice still applies for this session.
  }
  document.documentElement.lang = locale
}
