import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import SyncVolumesPanel from '../SyncVolumesPanel.vue'
import fr from '@/i18n/fr.json'
import en from '@/i18n/en.json'

function mountPanel(target: number | '') {
  const i18n = createI18n({ legacy: false, locale: 'fr', fallbackLocale: 'en', messages: { en, fr } })
  return mount(SyncVolumesPanel, {
    props: { totalVolumes: 12, pending: false, target },
    global: { plugins: [i18n] },
  })
}

function createButton(wrapper: ReturnType<typeof mountPanel>) {
  return wrapper.findAll('button').find((button) => button.text().includes('Créer les tomes'))!
}

describe('SyncVolumesPanel', () => {
  it('explains how many tomes the series has, in a full sentence', () => {
    const wrapper = mountPanel('')

    expect(wrapper.text()).toContain('Cette série compte actuellement 12 tomes.')
    expect(wrapper.find('strong').text()).toBe('12 tomes')
  })

  it('only creates tomes past the last one', async () => {
    const tooLow = mountPanel(12)
    expect(createButton(tooLow).attributes('disabled')).toBeDefined()
    await tooLow.find('input').trigger('keydown', { key: 'Enter' })
    expect(tooLow.emitted('submit')).toBeUndefined()

    const valid = mountPanel(15)
    expect(createButton(valid).attributes('disabled')).toBeUndefined()
    await valid.find('input').trigger('keydown', { key: 'Enter' })
    expect(valid.emitted('submit')).toHaveLength(1)
  })

  it('hands the typed number back to the page', async () => {
    const wrapper = mountPanel('')

    await wrapper.find('input').setValue('20')

    expect(wrapper.emitted('update:target')).toEqual([[20]])
  })
})
