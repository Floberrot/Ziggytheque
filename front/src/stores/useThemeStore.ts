import { defineStore } from 'pinia'
import { computed, ref, watch } from 'vue'

export type ThemeMode = 'light' | 'dark' | 'system'

const STORAGE_KEY = 'ziggy-theme-mode'

export const useThemeStore = defineStore('theme', () => {
  // Load from localStorage
  const stored = typeof localStorage !== 'undefined' ? localStorage.getItem(STORAGE_KEY) : null
  const mode = ref<ThemeMode>((stored as ThemeMode) || 'system')

  const isDark = computed(() => {
    if (mode.value === 'system') {
      return typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches
    }
    return mode.value === 'dark'
  })

  function applyTheme() {
    const html = typeof document !== 'undefined' ? document.documentElement : null
    if (!html) return

    if (mode.value === 'system') {
      html.removeAttribute('data-theme')
    } else {
      html.setAttribute('data-theme', `ziggy-${mode.value}`)
    }
  }

  function setMode(m: ThemeMode) {
    mode.value = m
    if (typeof localStorage !== 'undefined') {
      localStorage.setItem(STORAGE_KEY, m)
    }
    applyTheme()
  }

  watch(mode, applyTheme, { immediate: true })

  return { mode, isDark, setMode }
})
