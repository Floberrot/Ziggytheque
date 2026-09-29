import { describe, it, expect, beforeEach } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useThemeStore, THEMES } from '../useThemeStore'

const STORAGE_KEY = 'theme'

describe('useThemeStore', () => {
  beforeEach(() => {
    localStorage.clear()
    setActivePinia(createPinia())
  })

  it('only offers the two Ziggy themes', () => {
    expect([...THEMES]).toEqual(['ziggy-dark', 'ziggy-light'])
  })

  it('defaults to Ziggy Dark when nothing is persisted', () => {
    const store = useThemeStore()

    expect(store.theme).toBe('ziggy-dark')
    expect(store.isDark).toBe(true)
  })

  it('restores a persisted Ziggy theme', () => {
    localStorage.setItem(STORAGE_KEY, 'ziggy-light')

    const store = useThemeStore()

    expect(store.theme).toBe('ziggy-light')
    expect(store.isDark).toBe(false)
  })

  it('falls back to Ziggy Dark when a removed theme was persisted', () => {
    localStorage.setItem(STORAGE_KEY, 'dracula')

    const store = useThemeStore()

    expect(store.theme).toBe('ziggy-dark')
  })

  it('persists the theme chosen with setTheme', () => {
    const store = useThemeStore()

    store.setTheme('ziggy-light')

    expect(store.theme).toBe('ziggy-light')
    expect(localStorage.getItem(STORAGE_KEY)).toBe('ziggy-light')
  })

  it('toggles between dark and light', () => {
    const store = useThemeStore()

    store.toggle()
    expect(store.theme).toBe('ziggy-light')
    expect(store.isDark).toBe(false)

    store.toggle()
    expect(store.theme).toBe('ziggy-dark')
    expect(store.isDark).toBe(true)
    expect(localStorage.getItem(STORAGE_KEY)).toBe('ziggy-dark')
  })
})
