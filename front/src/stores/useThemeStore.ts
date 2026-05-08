import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const THEMES = [
  'ziggy-dark',
  'ziggy-light',
  'light',
  'dark',
  'cupcake',
  'bumblebee',
  'emerald',
  'corporate',
  'synthwave',
  'retro',
  'cyberpunk',
  'valentine',
  'halloween',
  'garden',
  'forest',
  'aqua',
  'lofi',
  'pastel',
  'fantasy',
  'wireframe',
  'black',
  'luxury',
  'dracula',
  'cmyk',
  'autumn',
  'business',
  'acid',
  'lemonade',
  'night',
  'coffee',
  'winter',
  'dim',
  'nord',
  'sunset',
  'abyss',
] as const

export type Theme = (typeof THEMES)[number]

const DARK_THEMES = new Set<string>([
  'ziggy-dark',
  'dark', 'synthwave', 'halloween', 'forest', 'aqua', 'black',
  'luxury', 'dracula', 'business', 'night', 'coffee', 'dim', 'sunset', 'abyss',
])

export const useThemeStore = defineStore('theme', () => {
  const stored = localStorage.getItem('theme') as Theme | null
  const theme = ref<Theme>(stored && (THEMES as readonly string[]).includes(stored) ? stored : 'ziggy-dark')

  const isDark = computed(() => DARK_THEMES.has(theme.value))

  function setTheme(t: Theme) {
    theme.value = t
    localStorage.setItem('theme', t)
  }

  return { theme, isDark, setTheme }
})
