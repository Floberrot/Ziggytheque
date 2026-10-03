import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import BaseLoader from '../BaseLoader.vue'
import fr from '@/i18n/fr.json'
import en from '@/i18n/en.json'

interface LoaderProps {
  variant?: 'inline' | 'section' | 'page'
  size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl'
  label?: string
  tone?: 'current' | 'primary' | 'warning'
}

function mountLoader(props: LoaderProps = {}, attrs: Record<string, unknown> = {}) {
  const i18n = createI18n({
    legacy: false,
    locale: 'fr',
    fallbackLocale: 'en',
    messages: { en, fr },
  })
  return mount(BaseLoader, { props, attrs, global: { plugins: [i18n] } })
}

describe('BaseLoader', () => {
  it('is a status with a hidden, localized "loading" text', () => {
    const wrapper = mountLoader()

    expect(wrapper.attributes('role')).toBe('status')
    expect(wrapper.find('.sr-only').text()).toBe(fr.common.loading)
  })

  it('renders as a span so it fits inside a button or a paragraph', () => {
    expect(mountLoader().element.tagName).toBe('SPAN')
  })

  it('hides the ring itself from assistive technologies', () => {
    const wrapper = mountLoader()
    const spinner = wrapper.find('.zig-loader__spinner')

    expect(spinner.attributes('aria-hidden')).toBe('true')
    expect(spinner.find('.zig-loader__track').exists()).toBe(true)
    expect(spinner.find('.zig-loader__comet').exists()).toBe(true)
  })

  it('shows a caption, announced in place of the hidden text', () => {
    const wrapper = mountLoader({ label: 'Création du lien…' })

    expect(wrapper.find('.zig-loader__label').text()).toBe('Création du lien…')
    expect(wrapper.find('.sr-only').exists()).toBe(false)
  })

  it('is an inline loader by default, small and in the surrounding text colour', () => {
    const wrapper = mountLoader()

    expect(wrapper.classes()).toContain('zig-loader--inline')
    expect(wrapper.attributes('style')).toContain('--zig-loader-size: 1rem')
    expect(wrapper.classes()).not.toContain('text-primary')
  })

  it('stands in for a section in the primary colour', () => {
    const wrapper = mountLoader({ variant: 'section' })

    expect(wrapper.classes()).toContain('zig-loader--section')
    expect(wrapper.classes()).toContain('text-primary')
    expect(wrapper.attributes('style')).toContain('--zig-loader-size: 3rem')
  })

  it('stands in for a whole page with the largest ring', () => {
    const wrapper = mountLoader({ variant: 'page' })

    expect(wrapper.classes()).toContain('zig-loader--page')
    expect(wrapper.attributes('style')).toContain('--zig-loader-size: 4rem')
  })

  it('lets an explicit size win over the variant default', () => {
    expect(mountLoader({ size: 'md' }).attributes('style')).toContain('--zig-loader-size: 2rem')
    expect(mountLoader({ variant: 'section', size: 'sm' }).attributes('style')).toContain(
      '--zig-loader-size: 1.5rem',
    )
    expect(mountLoader({ size: 'md' }).attributes('style')).toContain('--zig-loader-thickness: 3px')
  })

  it('takes another tone, or the surrounding colour', () => {
    expect(mountLoader({ variant: 'section', tone: 'warning' }).classes()).toContain('text-warning')
    expect(mountLoader({ variant: 'section', tone: 'current' }).classes()).not.toContain(
      'text-primary',
    )
    expect(mountLoader({ tone: 'primary' }).classes()).toContain('text-primary')
  })

  it('forwards utility classes onto the root so callers can theme the colour', () => {
    const wrapper = mountLoader({ size: 'md' }, { class: 'text-primary' })

    expect(wrapper.classes()).toContain('zig-loader')
    expect(wrapper.classes()).toContain('text-primary')
  })
})
