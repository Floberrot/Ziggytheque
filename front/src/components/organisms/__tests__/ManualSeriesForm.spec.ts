import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import type { ManualSeriesDraft } from '@/types'
import ManualSeriesForm from '../ManualSeriesForm.vue'
import fr from '@/i18n/fr.json'
import en from '@/i18n/en.json'

const EMPTY: ManualSeriesDraft = {
  title: '',
  publisher: '',
  specialEdition: '',
  author: '',
  totalVolumes: '',
  coverUrl: '',
}

function mountForm(draft: ManualSeriesDraft = EMPTY) {
  const i18n = createI18n({ legacy: false, locale: 'fr', fallbackLocale: 'en', messages: { en, fr } })
  return mount(ManualSeriesForm, {
    props: { modelValue: draft, submitting: false },
    global: { plugins: [i18n] },
  })
}

function lastDraft(wrapper: ReturnType<typeof mountForm>): ManualSeriesDraft {
  const updates = wrapper.emitted<[ManualSeriesDraft]>('update:modelValue')!
  return updates[updates.length - 1][0]
}

describe('ManualSeriesForm', () => {
  it('hands every field back to the page, which owns the draft', async () => {
    const wrapper = mountForm()

    await wrapper.find('input[type="text"][required]').setValue('Berserk')

    expect(lastDraft(wrapper)).toEqual({ ...EMPTY, title: 'Berserk' })
  })

  it('reads the volume count as a number, like v-model on a number input', async () => {
    const wrapper = mountForm()
    const count = wrapper.find('input[type="number"]')

    await count.setValue('42')
    expect(lastDraft(wrapper).totalVolumes).toBe(42)

    await count.setValue('')
    expect(lastDraft(wrapper).totalVolumes).toBe('')
  })

  it('cannot be submitted without a title', () => {
    const wrapper = mountForm()

    expect(wrapper.find('button[type="submit"]').attributes('disabled')).toBeDefined()
  })

  it('submits a titled series', async () => {
    const wrapper = mountForm({ ...EMPTY, title: 'Berserk' })

    expect(wrapper.find('button[type="submit"]').attributes('disabled')).toBeUndefined()
    await wrapper.find('form').trigger('submit')

    expect(wrapper.emitted('submit')).toHaveLength(1)
  })
})
