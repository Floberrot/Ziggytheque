import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import TopAuthorsList from '../TopAuthorsList.vue'
import fr from '@/i18n/fr.json'
import en from '@/i18n/en.json'

function mountList(authors: { author: string; count: number }[]) {
  const i18n = createI18n({ legacy: false, locale: 'fr', fallbackLocale: 'en', messages: { en, fr } })
  return mount(TopAuthorsList, { props: { authors }, global: { plugins: [i18n] } })
}

describe('TopAuthorsList', () => {
  it('renders authors with their rank and count', () => {
    const wrapper = mountList([
      { author: 'Eiichiro Oda', count: 5 },
      { author: 'Kentaro Miura', count: 2 },
    ])
    const items = wrapper.findAll('li')
    expect(items).toHaveLength(2)
    expect(items[0].text()).toContain('1')
    expect(items[0].text()).toContain('Eiichiro Oda')
    expect(items[0].text()).toContain('5')
    expect(items[1].text()).toContain('Kentaro Miura')
  })

  it('shows an empty state when there are no authors', () => {
    const wrapper = mountList([])
    expect(wrapper.text()).toContain('Aucune donnée')
  })
})
