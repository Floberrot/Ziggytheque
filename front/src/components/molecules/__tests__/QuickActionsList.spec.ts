import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import type { CollectionEntry } from '@/types'
import QuickActionsList from '../QuickActionsList.vue'
import fr from '@/i18n/fr.json'

function entry(overrides: Partial<CollectionEntry> = {}): CollectionEntry {
  return {
    id: 'entry-1',
    manga: { title: 'Berserk', edition: 'Glénat', specialEdition: 'Prestige', coverUrl: null },
    rating: 6,
    notificationsEnabled: false,
    ...overrides,
  } as unknown as CollectionEntry
}

function mountList(props: { entry: CollectionEntry; busy?: boolean }) {
  const i18n = createI18n({ legacy: false, locale: 'fr', messages: { fr } })
  return mount(QuickActionsList, { props, global: { plugins: [i18n] } })
}

function buttonWith(wrapper: ReturnType<typeof mountList>, text: string) {
  const button = wrapper.findAll('button').find((candidate) => candidate.text().includes(text))
  if (!button) throw new Error(`No button "${text}"`)
  return button
}

describe('QuickActionsList', () => {
  it('opens the series', async () => {
    const wrapper = mountList({ entry: entry() })

    await buttonWith(wrapper, 'Ouvrir la série').trigger('click')

    expect(wrapper.emitted('open')).toHaveLength(1)
  })

  it('offers to follow, or to stop following', async () => {
    const wrapper = mountList({ entry: entry() })
    await buttonWith(wrapper, 'Suivre les sorties').trigger('click')
    expect(wrapper.emitted('toggleFollow')).toHaveLength(1)

    await wrapper.setProps({ entry: entry({ notificationsEnabled: true }) })
    expect(wrapper.text()).toContain('Ne plus suivre')
  })

  it('rates with the hearts, two points per heart', async () => {
    const wrapper = mountList({ entry: entry() })
    const hearts = wrapper.findAll('[role="radio"]')

    expect(hearts).toHaveLength(5)
    expect(hearts[2].attributes('aria-checked')).toBe('true')
    await hearts[4].trigger('click')

    expect(wrapper.emitted('rate')).toEqual([[10]])
  })

  it('asks for a confirmation before removing', async () => {
    const wrapper = mountList({ entry: entry() })

    await buttonWith(wrapper, 'Retirer de la collection').trigger('click')
    expect(wrapper.emitted('remove')).toBeUndefined()

    await buttonWith(wrapper, 'Retirer').trigger('click')
    expect(wrapper.emitted('remove')).toHaveLength(1)
  })

  it('cancels the removal', async () => {
    const wrapper = mountList({ entry: entry() })

    await buttonWith(wrapper, 'Retirer de la collection').trigger('click')
    await buttonWith(wrapper, 'Annuler').trigger('click')

    expect(wrapper.text()).toContain('Retirer de la collection')
    expect(wrapper.emitted('remove')).toBeUndefined()
  })
})
