import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import BaseLoader from '../BaseLoader.vue'
import fr from '@/i18n/fr.json'
import en from '@/i18n/en.json'

function mountLoader(
  props: { size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl'; label?: string } = {},
  attrs: Record<string, unknown> = {},
) {
  const i18n = createI18n({
    legacy: false,
    locale: 'fr',
    fallbackLocale: 'en',
    messages: { en, fr },
  })
  return mount(BaseLoader, { props, attrs, global: { plugins: [i18n] } })
}

describe('BaseLoader', () => {
  it('exposes an accessible status role with the localized default label', () => {
    const wrapper = mountLoader()

    expect(wrapper.attributes('role')).toBe('status')
    expect(wrapper.attributes('aria-label')).toBe(fr.common.loading)
  })

  it('renders the smooth ring as a faint track with a sweeping comet', () => {
    const wrapper = mountLoader()

    expect(wrapper.find('.zig-loader__track').exists()).toBe(true)
    expect(wrapper.find('.zig-loader__comet').exists()).toBe(true)
  })

  it('drives its dimensions from the size prop via CSS variables', () => {
    expect(mountLoader().attributes('style')).toContain('--zig-loader-size: 2.75rem')
    expect(mountLoader({ size: 'xs' }).attributes('style')).toContain('--zig-loader-size: 1rem')
    expect(mountLoader({ size: 'lg' }).attributes('style')).toContain('--zig-loader-size: 4rem')
  })

  it('shows a caption and uses it as the accessible name when label is provided', () => {
    const wrapper = mountLoader({ label: 'Chargement de la bibliothèque…' })

    expect(wrapper.text()).toContain('Chargement de la bibliothèque…')
    expect(wrapper.attributes('aria-label')).toBe('Chargement de la bibliothèque…')
  })

  it('forwards utility classes onto the root so callers can theme the colour', () => {
    const wrapper = mountLoader({ size: 'md' }, { class: 'text-primary' })

    expect(wrapper.classes()).toContain('zig-loader')
    expect(wrapper.classes()).toContain('text-primary')
  })
})
