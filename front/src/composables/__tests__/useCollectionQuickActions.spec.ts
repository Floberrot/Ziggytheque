import { describe, it, expect, vi, beforeEach } from 'vitest'
import { defineComponent, h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { createPinia, setActivePinia } from 'pinia'
import { createI18n } from 'vue-i18n'
import type { CollectionEntry, QuickActionRequest } from '@/types'
import fr from '@/i18n/fr.json'

vi.mock('@/api/collection', () => ({
  toggleFollow: vi.fn(),
  updateCollectionRating: vi.fn(),
  removeFromCollection: vi.fn(),
}))

import { removeFromCollection, toggleFollow, updateCollectionRating } from '@/api/collection'
import { useUiStore } from '@/stores/useUiStore'
import { useCollectionQuickActions } from '../useCollectionQuickActions'

function entry(overrides: Partial<CollectionEntry> = {}): CollectionEntry {
  return { id: 'entry-1', rating: null, notificationsEnabled: false, ...overrides } as unknown as CollectionEntry
}

function request(target: CollectionEntry): QuickActionRequest {
  return { entry: target, point: { x: 10, y: 20 }, source: 'mouse' }
}

function setup() {
  const queryClient = new QueryClient()
  const pinia = createPinia()
  setActivePinia(pinia)
  const i18n = createI18n({ legacy: false, locale: 'fr', messages: { fr } })
  let quickActions: ReturnType<typeof useCollectionQuickActions> | undefined
  const Host = defineComponent({
    setup() {
      quickActions = useCollectionQuickActions()
      return () => h('div')
    },
  })
  mount(Host, { global: { plugins: [[VueQueryPlugin, { queryClient }], pinia, i18n] } })
  return { queryClient, quickActions: quickActions! }
}

describe('useCollectionQuickActions', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('opens and closes the quick actions of a card', () => {
    const { quickActions } = setup()

    quickActions.openQuickActions(request(entry()))
    expect(quickActions.quickActionRequest.value?.entry.id).toBe('entry-1')

    quickActions.closeQuickActions()
    expect(quickActions.quickActionRequest.value).toBeNull()
  })

  it('follows a series and shows it in the open menu at once', async () => {
    vi.mocked(toggleFollow).mockResolvedValueOnce({ notificationsEnabled: true })
    const { quickActions, queryClient } = setup()
    const invalidate = vi.spyOn(queryClient, 'invalidateQueries')
    const target = entry()
    quickActions.openQuickActions(request(target))

    quickActions.followEntry(target)
    await flushPromises()

    expect(toggleFollow).toHaveBeenCalledWith('entry-1')
    expect(quickActions.quickActionRequest.value?.entry.notificationsEnabled).toBe(true)
    expect(useUiStore().toasts.map((toast) => toast.message)).toContain(fr.quickActions.followed)
    expect(invalidate).toHaveBeenCalledWith({ queryKey: ['collection'] })
    expect(invalidate).toHaveBeenCalledWith({ queryKey: ['stats'] })
  })

  it('rates a series and patches the open menu', async () => {
    vi.mocked(updateCollectionRating).mockResolvedValueOnce(undefined)
    const { quickActions } = setup()
    const target = entry()
    quickActions.openQuickActions(request(target))

    quickActions.rateEntry(target, 8)
    await flushPromises()

    expect(updateCollectionRating).toHaveBeenCalledWith('entry-1', 8)
    expect(quickActions.quickActionRequest.value?.entry.rating).toBe(8)
  })

  it('closes the menu once the series is removed', async () => {
    vi.mocked(removeFromCollection).mockResolvedValueOnce(undefined)
    const { quickActions } = setup()
    const target = entry()
    quickActions.openQuickActions(request(target))

    quickActions.removeEntry(target)
    await flushPromises()

    expect(removeFromCollection).toHaveBeenCalledWith('entry-1')

    expect(quickActions.quickActionRequest.value).toBeNull()
    expect(quickActions.quickActionBusy.value).toBe(false)
  })

  it('tells the user when an action fails', async () => {
    vi.mocked(toggleFollow).mockRejectedValueOnce(new Error('500'))
    const { quickActions } = setup()

    quickActions.followEntry(entry())
    await flushPromises()

    expect(useUiStore().toasts.map((toast) => toast.message)).toContain(fr.quickActions.error)
  })
})
