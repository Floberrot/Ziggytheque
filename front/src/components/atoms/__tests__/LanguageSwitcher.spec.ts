import { describe, it, expect, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import LanguageSwitcher from '../LanguageSwitcher.vue'
import fr from '@/i18n/fr.json'
import en from '@/i18n/en.json'
import { loadLocale } from '@/i18n/locale'

function mountSwitcher() {
  const i18n = createI18n({ legacy: false, locale: 'fr', fallbackLocale: 'en', messages: { en, fr } })
  return { i18n, wrapper: mount(LanguageSwitcher, { global: { plugins: [i18n] } }) }
}

describe('LanguageSwitcher', () => {
  beforeEach(() => {
    localStorage.clear()
    document.documentElement.lang = 'fr'
  })

  it('offers French and English, the current one pressed', () => {
    const { wrapper } = mountSwitcher()
    const buttons = wrapper.findAll('button')

    expect(buttons.map((button) => button.text())).toEqual(['FR', 'EN'])
    expect(buttons[0].attributes('aria-pressed')).toBe('true')
    expect(buttons[1].attributes('aria-pressed')).toBe('false')
    expect(buttons[1].attributes('aria-label')).toBe('English')
  })

  it('switches the language at once and remembers it for the next visit', async () => {
    const { wrapper, i18n } = mountSwitcher()

    await wrapper.findAll('button')[1].trigger('click')

    expect(i18n.global.locale.value).toBe('en')
    expect(localStorage.getItem('locale')).toBe('en')
    expect(loadLocale()).toBe('en')
    expect(document.documentElement.lang).toBe('en')
    expect(wrapper.findAll('button')[1].attributes('aria-pressed')).toBe('true')
  })
})

describe('loadLocale', () => {
  beforeEach(() => {
    localStorage.clear()
  })

  it('is French until a language is picked', () => {
    expect(loadLocale()).toBe('fr')
  })

  it('ignores a value that is not a language of the app', () => {
    localStorage.setItem('locale', 'klingon')

    expect(loadLocale()).toBe('fr')
  })
})
