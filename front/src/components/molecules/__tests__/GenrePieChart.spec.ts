import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import GenrePieChart from '../GenrePieChart.vue'
import fr from '@/i18n/fr.json'
import en from '@/i18n/en.json'

function mountChart(breakdown: Record<string, number>) {
  const i18n = createI18n({ legacy: false, locale: 'fr', fallbackLocale: 'en', messages: { en, fr } })
  return mount(GenrePieChart, {
    props: { breakdown },
    global: {
      plugins: [i18n],
      // Chart.js can't acquire a canvas context in jsdom — stub the renderer.
      stubs: { Doughnut: { template: '<canvas />' } },
    },
  })
}

describe('GenrePieChart', () => {
  it('renders one legend row per genre, sorted by descending count', () => {
    const wrapper = mountChart({ seinen: 4, shonen: 10, horror: 1 })
    const rows = wrapper.findAll('ul > li')
    expect(rows).toHaveLength(3)
    // Largest slice first → Shōnen, then Seinen, then Horreur
    expect(rows[0].text()).toContain('Shōnen')
    expect(rows[1].text()).toContain('Seinen')
    expect(rows[2].text()).toContain('Horreur')
  })

  it('shows the total in the centre and percentages per slice', () => {
    const wrapper = mountChart({ shonen: 3, seinen: 1 })
    // total = 4
    expect(wrapper.text()).toContain('4')
    // shonen 3/4 = 75%, seinen 1/4 = 25%
    expect(wrapper.text()).toContain('75%')
    expect(wrapper.text()).toContain('25%')
  })

  it('ignores zero-count genres', () => {
    const wrapper = mountChart({ shonen: 5, action: 0 })
    expect(wrapper.findAll('ul > li')).toHaveLength(1)
  })
})
