import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import { createMemoryHistory, createRouter } from 'vue-router'
import type { CollectionEntry, ReadingStatus } from '@/types'
import MangaCard from '../MangaCard.vue'
import fr from '@/i18n/fr.json'
import en from '@/i18n/en.json'

function entry(id: string, readingStatus: ReadingStatus = 'in_progress'): CollectionEntry {
  return {
    id,
    manga: { title: 'Berserk', edition: 'Glénat', specialEdition: null, coverUrl: null, genre: null, author: 'Miura' },
    readingStatus,
    rating: null,
    ownedCount: 3,
    readCount: 1,
    wishedCount: 0,
    totalVolumes: 41,
    ownedValue: 0,
    notificationsEnabled: false,
  } as unknown as CollectionEntry
}

function mountCard(props: { entry: CollectionEntry; editions?: CollectionEntry[] }) {
  const i18n = createI18n({ legacy: false, locale: 'fr', fallbackLocale: 'en', messages: { en, fr } })
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', component: { render: () => null } },
      { path: '/collection/:id', name: 'collection-detail', component: { render: () => null } },
    ],
  })
  const push = vi.spyOn(router, 'push')
  const wrapper = mount(MangaCard, { props, global: { plugins: [i18n, router] } })
  return { wrapper, push }
}

describe('MangaCard', () => {
  it('is a link to its series, named with what is owned', () => {
    const { wrapper } = mountCard({ entry: entry('entry-1') })
    const card = wrapper.find('.manga-card')

    expect(card.attributes('role')).toBe('link')
    expect(card.attributes('tabindex')).toBe('0')
    expect(card.attributes('aria-label')).toBe('Berserk — 3/41 tomes possédés')
  })

  it('opens the series with Enter or Space', async () => {
    const { wrapper, push } = mountCard({ entry: entry('entry-1') })

    await wrapper.find('.manga-card').trigger('keydown', { key: 'Enter' })
    await wrapper.find('.manga-card').trigger('keydown', { key: ' ' })

    expect(push).toHaveBeenCalledTimes(2)
    expect(push).toHaveBeenCalledWith({ name: 'collection-detail', params: { id: 'entry-1' } })
  })

  it('opens its editions when it stands for several (a button then)', async () => {
    const editions = [entry('entry-1'), entry('entry-2')]
    const { wrapper, push } = mountCard({ entry: editions[0], editions })
    const card = wrapper.find('.manga-card')

    expect(card.attributes('role')).toBe('button')
    await card.trigger('keydown', { key: 'Enter' })

    expect(wrapper.emitted('openEditions')).toHaveLength(1)
    expect(push).not.toHaveBeenCalled()
  })

  it('opens its quick actions next to the card from the Menu key', async () => {
    const { wrapper } = mountCard({ entry: entry('entry-1') })

    await wrapper.find('.manga-card').trigger('contextmenu', { clientX: 0, clientY: 0 })

    const [request] = wrapper.emitted<[{ source: string; point: { x: number; y: number } }]>('quickActions')![0]
    expect(request.source).toBe('mouse')
    expect(request.point).toEqual({ x: 0, y: 0 })
  })

  it('flags the reading status with the shared vocabulary', () => {
    expect(mountCard({ entry: entry('a', 'not_started') }).wrapper.text()).toContain('À lire')
    expect(mountCard({ entry: entry('b', 'completed') }).wrapper.text()).toContain('Terminé')
    expect(mountCard({ entry: entry('c', 'on_hold') }).wrapper.text()).toContain('En pause')
    expect(mountCard({ entry: entry('d', 'in_progress') }).wrapper.text()).not.toContain('En cours')
  })
})
