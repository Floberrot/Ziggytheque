import { defineStore } from 'pinia'
import { ref } from 'vue'

export const THEMES = [
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
] as const

export type Theme = (typeof THEMES)[number]

export const useThemeStore = defineStore('theme', () => {
  const stored = localStorage.getItem('theme') as Theme | null
  const theme = ref<Theme>(stored && (THEMES as readonly string[]).includes(stored) ? stored : 'dark')

  function setTheme(t: Theme) {
    theme.value = t
    localStorage.setItem('theme', t)
  }

  return { theme, setTheme }
})
