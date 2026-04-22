import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

export type ThemeMode = 'light' | 'dark' | 'system'

export const useThemeStore = defineStore(
  'theme',
  () => {
    const mode = ref<ThemeMode>('system')

    function applyTheme() {
      const html = document.documentElement
      let effectiveTheme: 'light' | 'dark'

      if (mode.value === 'system') {
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
        effectiveTheme = prefersDark ? 'dark' : 'light'
        html.removeAttribute('data-theme')
      } else {
        effectiveTheme = mode.value
        html.setAttribute('data-theme', mode.value)
      }

      html.style.colorScheme = effectiveTheme
    }

    function setTheme(t: ThemeMode) {
      mode.value = t
      applyTheme()
    }

    watch(mode, applyTheme, { immediate: true })

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
      if (mode.value === 'system') {
        applyTheme()
      }
    })

    return {
      mode,
      setTheme,
    }
  },
  {
    persist: true,
  }
)
