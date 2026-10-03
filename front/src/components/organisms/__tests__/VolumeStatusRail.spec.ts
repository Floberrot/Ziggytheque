import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import type { VolumeEntry } from '@/types'
import VolumeStatusRail from '../VolumeStatusRail.vue'
import fr from '@/i18n/fr.json'
import en from '@/i18n/en.json'

function volume(flags: Partial<VolumeEntry> = {}): VolumeEntry {
  return {
    id: 've-1',
    volumeId: 'v-1',
    number: 1,
    coverUrl: null,
    price: null,
    isOwned: false,
    isRead: false,
    isWished: false,
    isAnnounced: false,
    review: null,
    rating: null,
    isbn: '9782723425483',
    ...flags,
  }
}

function mountRail(target: VolumeEntry) {
  const i18n = createI18n({ legacy: false, locale: 'fr', fallbackLocale: 'en', messages: { en, fr } })
  return mount(VolumeStatusRail, { props: { volume: target }, global: { plugins: [i18n] } })
}

function cardWith(wrapper: ReturnType<typeof mountRail>, label: string) {
  return wrapper.findAll('button[aria-pressed]').find((button) => button.text().includes(label))
}

describe('VolumeStatusRail', () => {
  it('offers owned, wished and announced on a tome not owned — not read', () => {
    const wrapper = mountRail(volume())

    expect(cardWith(wrapper, 'Possédé')).toBeTruthy()
    expect(cardWith(wrapper, 'Souhaité')).toBeTruthy()
    expect(cardWith(wrapper, 'Annoncé')).toBeTruthy()
    expect(cardWith(wrapper, 'Tu as fini de le lire')).toBeUndefined()
  })

  it('emits the flag to flip — the page saves it', async () => {
    const wrapper = mountRail(volume({ isOwned: true }))

    expect(cardWith(wrapper, 'Possédé')!.attributes('aria-pressed')).toBe('true')
    await cardWith(wrapper, 'Tu as fini de le lire')!.trigger('click')

    expect(wrapper.emitted('toggle')).toEqual([['isRead']])
  })

  it('zooms only on a tome that has a cover', async () => {
    const withoutCover = mountRail(volume())
    expect(withoutCover.find('[role="button"]').exists()).toBe(false)

    const withCover = mountRail(volume({ coverUrl: 'https://covers.test/1.jpg' }))
    await withCover.find('[role="button"]').trigger('keydown', { key: 'Enter' })
    expect(withCover.emitted('zoom')).toHaveLength(1)
  })

  it('shows the ISBN of the tome', () => {
    expect(mountRail(volume()).text()).toContain('9782723425483')
  })
})
