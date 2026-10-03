import { describe, it, expect, vi, beforeEach } from 'vitest'
import { defineComponent, h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { createPinia, setActivePinia } from 'pinia'
import { createI18n } from 'vue-i18n'
import type { CollectionEntryDetail, VolumeEntry } from '@/types'

vi.mock('@/api/collection', () => ({
  toggleVolume: vi.fn(),
}))

import { toggleVolume } from '@/api/collection'
import { useUiStore } from '@/stores/useUiStore'
import { useVolumeToggle } from '../useVolumeToggle'

const mockToggleVolume = vi.mocked(toggleVolume)

function volume(number: number, flags: Partial<VolumeEntry> = {}): VolumeEntry {
  return {
    id: `ve-${number}`,
    volumeId: `v-${number}`,
    number,
    coverUrl: null,
    price: 7,
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

function detail(volumes: VolumeEntry[]): CollectionEntryDetail {
  return {
    id: 'entry-1',
    volumes,
    ownedCount: volumes.filter((item) => item.isOwned).length,
    readCount: 0,
    wishedCount: volumes.filter((item) => item.isWished).length,
    ownedValue: 0,
  } as unknown as CollectionEntryDetail
}

function setup(onMutate = vi.fn()) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  const pinia = createPinia()
  setActivePinia(pinia)
  const i18n = createI18n({ legacy: false, locale: 'en', messages: { en: { enrich: { statusUpdateError: 'Status failed' } } } })
  let toggle: ReturnType<typeof useVolumeToggle> | undefined
  const Host = defineComponent({
    setup() {
      toggle = useVolumeToggle('entry-1', { onMutate })
      return () => h('div')
    },
  })
  mount(Host, { global: { plugins: [[VueQueryPlugin, { queryClient }], pinia, i18n] } })
  return { queryClient, toggle: toggle!, onMutate }
}

describe('useVolumeToggle', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('updates the series detail at once, before the server answers', async () => {
    let finish: () => void = () => {}
    mockToggleVolume.mockReturnValueOnce(new Promise<void>((resolve) => { finish = resolve }))
    const { queryClient, toggle, onMutate } = setup()
    queryClient.setQueryData(['collection', 'entry-1'], detail([volume(1, { isWished: true }), volume(2)]))

    toggle.mutate({ volumeEntryId: 've-1', field: 'isOwned' })
    await flushPromises()

    const optimistic = queryClient.getQueryData<CollectionEntryDetail>(['collection', 'entry-1'])!
    expect(optimistic.volumes[0]).toMatchObject({ isOwned: true, isWished: false })
    expect(optimistic).toMatchObject({ ownedCount: 1, wishedCount: 0, ownedValue: 7 })
    expect(onMutate).toHaveBeenCalledOnce()
    expect(mockToggleVolume).toHaveBeenCalledWith('entry-1', 've-1', 'isOwned')

    finish()
    await flushPromises()
  })

  it('puts the detail back and tells the user when the server refuses', async () => {
    mockToggleVolume.mockRejectedValueOnce(new Error('500'))
    const { queryClient, toggle } = setup()
    const before = detail([volume(1)])
    queryClient.setQueryData(['collection', 'entry-1'], before)

    toggle.mutate({ volumeEntryId: 've-1', field: 'isRead' })
    await flushPromises()

    expect(queryClient.getQueryData(['collection', 'entry-1'])).toEqual(before)
    expect(useUiStore().toasts.map((toast) => toast.message)).toContain('Status failed')
  })

  it('refetches the lists the tome appears in once settled', async () => {
    mockToggleVolume.mockResolvedValueOnce(undefined)
    const { queryClient, toggle } = setup()
    queryClient.setQueryData(['collection', 'entry-1'], detail([volume(1)]))
    const invalidate = vi.spyOn(queryClient, 'invalidateQueries')

    toggle.mutate({ volumeEntryId: 've-1', field: 'isWished' })
    await flushPromises()

    const keys = invalidate.mock.calls.map(([filters]) => (filters as { queryKey?: unknown } | undefined)?.queryKey)
    expect(keys).toEqual(expect.arrayContaining([['collection', 'entry-1'], ['collection'], ['wishlist'], ['stats']]))
  })
})
