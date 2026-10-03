import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import type { VolumeEntry } from '@/types'
import VolumeTile from '../VolumeTile.vue'
import fr from '@/i18n/fr.json'
import en from '@/i18n/en.json'

function volume(flags: Partial<VolumeEntry> = {}): VolumeEntry {
  return {
    id: 've-3',
    volumeId: 'v-3',
    number: 3,
    coverUrl: null,
    price: null,
    isOwned: false,
    isRead: false,
    isWished: false,
    isAnnounced: false,
    review: null,
    rating: null,
    isbn: null,
    ...flags,
  }
}

function mountTile(props: { volume: VolumeEntry; batchMode?: boolean; selected?: boolean }) {
  const i18n = createI18n({ legacy: false, locale: 'fr', fallbackLocale: 'en', messages: { en, fr } })
  return mount(VolumeTile, {
    props: { batchMode: false, selected: false, ...props },
    global: { plugins: [i18n] },
  })
}

describe('VolumeTile', () => {
  it('is a focusable button named after the tome and its statuses', () => {
    const wrapper = mountTile({ volume: volume({ isOwned: true, isRead: true }) })
    const tile = wrapper.find('[role="button"]')

    expect(tile.attributes('tabindex')).toBe('0')
    expect(tile.attributes('aria-label')).toBe('Tome 3 — Possédé, Lu')
    expect(tile.attributes('aria-pressed')).toBeUndefined()
  })

  it('names an untracked tome', () => {
    const wrapper = mountTile({ volume: volume() })

    expect(wrapper.find('[role="button"]').attributes('aria-label')).toBe('Tome 3 — Non suivi')
  })

  it('opens with the click, Enter or Space', async () => {
    const wrapper = mountTile({ volume: volume() })
    const tile = wrapper.find('[role="button"]')

    await tile.trigger('click')
    await tile.trigger('keydown', { key: 'Enter' })
    await tile.trigger('keydown', { key: ' ' })

    expect(wrapper.emitted('activate')).toHaveLength(3)
  })

  it('opens its quick actions on a right click, at the pointer', async () => {
    const wrapper = mountTile({ volume: volume() })

    await wrapper.find('[role="button"]').trigger('contextmenu', { clientX: 40, clientY: 60 })

    expect(wrapper.emitted('contextMenu')).toEqual([[{ x: 40, y: 60 }]])
  })

  it('opens the phone action sheet without opening the tome', async () => {
    const wrapper = mountTile({ volume: volume() })

    await wrapper.find('button').trigger('click')

    expect(wrapper.emitted('openActions')).toHaveLength(1)
    expect(wrapper.emitted('activate')).toBeUndefined()
  })

  it('is a pressed toggle of the selection in batch mode', () => {
    const wrapper = mountTile({ volume: volume(), batchMode: true, selected: true })

    expect(wrapper.find('[role="button"]').attributes('aria-pressed')).toBe('true')
    // No "⋯" while selecting.
    expect(wrapper.find('button').exists()).toBe(false)
  })
})
