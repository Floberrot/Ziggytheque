import { describe, it, expect, afterEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import BaseHeartRating from '../BaseHeartRating.vue'
import fr from '@/i18n/fr.json'
import en from '@/i18n/en.json'

function mountRating(props: { modelValue: number | null; readonly?: boolean; compact?: boolean }) {
  const i18n = createI18n({ legacy: false, locale: 'fr', fallbackLocale: 'en', messages: { en, fr } })
  return mount(BaseHeartRating, { props, global: { plugins: [i18n] }, attachTo: document.body })
}

describe('BaseHeartRating', () => {
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('is a radio group of ten half hearts, each one named', () => {
    const wrapper = mountRating({ modelValue: 6 })

    expect(wrapper.find('[role="radiogroup"]').attributes('aria-label')).toBe('Ta note')
    const radios = wrapper.findAll('[role="radio"]')
    expect(radios).toHaveLength(10)
    expect(radios[0].attributes('aria-label')).toBe('0.5 sur 5')
    expect(radios[5].attributes('aria-label')).toBe('3 sur 5')
    expect(radios[5].attributes('aria-checked')).toBe('true')
  })

  it('lets Tab land on the current rating only (roving tab stop)', () => {
    const wrapper = mountRating({ modelValue: 6 })

    const focusable = wrapper.findAll('[role="radio"]').filter((radio) => radio.attributes('tabindex') === '0')
    expect(focusable).toHaveLength(1)
    expect(focusable[0].attributes('data-rating-value')).toBe('6')
  })

  it('starts on the first half heart when nothing is rated yet', () => {
    const wrapper = mountRating({ modelValue: null })

    expect(wrapper.find('[tabindex="0"]').attributes('data-rating-value')).toBe('1')
  })

  it('moves along the hearts with the arrow keys and saves on Enter', async () => {
    const wrapper = mountRating({ modelValue: 6 })
    const current = wrapper.find('[data-rating-value="6"]')

    await current.trigger('keydown', { key: 'ArrowRight' })
    expect(document.activeElement?.getAttribute('data-rating-value')).toBe('7')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()

    await wrapper.find('[data-rating-value="7"]').trigger('keydown', { key: 'Enter' })
    expect(wrapper.emitted('update:modelValue')).toEqual([[7]])
  })

  it('stops at the ends and jumps with Home / End', async () => {
    const wrapper = mountRating({ modelValue: 10 })

    await wrapper.find('[data-rating-value="10"]').trigger('keydown', { key: 'ArrowRight' })
    expect(document.activeElement?.getAttribute('data-rating-value')).toBe('10')

    await wrapper.find('[data-rating-value="10"]').trigger('keydown', { key: 'Home' })
    expect(document.activeElement?.getAttribute('data-rating-value')).toBe('1')

    await wrapper.find('[data-rating-value="1"]').trigger('keydown', { key: ' ' })
    expect(wrapper.emitted('update:modelValue')).toEqual([[1]])
  })

  it('is a plain labelled image when read-only', () => {
    const wrapper = mountRating({ modelValue: 7, readonly: true })

    expect(wrapper.find('[role="radio"]').exists()).toBe(false)
    expect(wrapper.find('[role="img"]').attributes('aria-label')).toBe('Note : 3.5/5')
  })

  it('names an unrated compact chip', () => {
    const wrapper = mountRating({ modelValue: null, compact: true })

    expect(wrapper.find('[role="img"]').attributes('aria-label')).toBe('Non noté')
  })
})
