import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import ReadingStatusBar from '../ReadingStatusBar.vue'
import fr from '@/i18n/fr.json'
import en from '@/i18n/en.json'

function mountBar(breakdown: Record<string, number>) {
  const i18n = createI18n({ legacy: false, locale: 'fr', fallbackLocale: 'en', messages: { en, fr } })
  return mount(ReadingStatusBar, { props: { breakdown }, global: { plugins: [i18n] } })
}

describe('ReadingStatusBar', () => {
  it('renders a legend entry per non-empty status', () => {
    const wrapper = mountBar({ in_progress: 2, completed: 3, dropped: 0 })
    const items = wrapper.findAll('li')
    expect(items).toHaveLength(2)
    expect(wrapper.text()).toContain('En cours')
    expect(wrapper.text()).toContain('Terminé')
    expect(wrapper.text()).not.toContain('Abandonné')
  })

  it('sizes each segment proportionally to its share', () => {
    const wrapper = mountBar({ in_progress: 1, completed: 3 })
    const segments = wrapper.findAll('.flex.h-3 > div')
    expect(segments).toHaveLength(2)
    // in_progress 1/4 = 25%, completed 3/4 = 75%
    expect(segments[0].attributes('style')).toContain('25%')
    expect(segments[1].attributes('style')).toContain('75%')
  })

  it('shows an empty state when there is nothing to read', () => {
    const wrapper = mountBar({})
    expect(wrapper.text()).toContain('Aucune donnée')
  })
})
