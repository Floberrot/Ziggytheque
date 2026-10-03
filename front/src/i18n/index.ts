import { createI18n } from 'vue-i18n'
import en from './en.json'
import fr from './fr.json'
import { loadLocale } from './locale'

/**
 * The app's one i18n instance. Components use `useI18n()`; code outside a component
 * (router guard, query client) uses `i18n.global.t`.
 */
export const i18n = createI18n({
  legacy: false,
  locale: loadLocale(),
  fallbackLocale: 'en',
  messages: { en, fr },
})
