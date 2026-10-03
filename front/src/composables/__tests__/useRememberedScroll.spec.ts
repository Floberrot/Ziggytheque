import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { defineComponent, h, ref, type Ref } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { useRememberedScroll } from '../useRememberedScroll'
import { useCollectionFiltersStore } from '@/stores/useCollectionFiltersStore'
import { useAuthStore } from '@/stores/useAuthStore'

/** A list page: `ready` is true once its list is on screen. */
function mountListPage(ready: Ref<boolean>) {
  const ListPage = defineComponent({
    setup() {
      useRememberedScroll('collection', ready)
      return () => h('div')
    },
  })
  return mount(ListPage)
}

function setWindowScroll(offset: number): void {
  Object.defineProperty(window, 'scrollY', { value: offset, configurable: true, writable: true })
}

function scrolledTo(top: number) {
  return { top, left: 0, behavior: 'instant' }
}

function mockWindowScroll() {
  return vi.spyOn(window, 'scrollTo').mockImplementation(() => {})
}

describe('useRememberedScroll', () => {
  let scrollTo: ReturnType<typeof mockWindowScroll>

  beforeEach(() => {
    sessionStorage.clear()
    setActivePinia(createPinia())
    useAuthStore().setToken('signed.in.token')
    scrollTo = mockWindowScroll()
  })

  afterEach(() => {
    scrollTo.mockRestore()
    setWindowScroll(0)
  })

  it('puts the reader back at the saved offset when the list comes from the cache', async () => {
    useCollectionFiltersStore().saveScroll('collection', 480)

    mountListPage(ref(true))
    await flushPromises()

    expect(scrollTo).toHaveBeenCalledTimes(1)
    expect(scrollTo).toHaveBeenCalledWith(scrolledTo(480))
  })

  it('shows the top while the list loads, then the saved offset once it is there', async () => {
    useCollectionFiltersStore().saveScroll('collection', 480)
    const ready = ref(false)

    mountListPage(ready)
    expect(scrollTo).toHaveBeenLastCalledWith(scrolledTo(0))

    ready.value = true
    await flushPromises()

    expect(scrollTo).toHaveBeenLastCalledWith(scrolledTo(480))
  })

  it('never scrolls again when the list reloads later (a new filter)', async () => {
    useCollectionFiltersStore().saveScroll('collection', 480)
    const ready = ref(true)
    mountListPage(ready)
    await flushPromises()

    ready.value = false
    await flushPromises()
    ready.value = true
    await flushPromises()

    expect(scrollTo).toHaveBeenCalledTimes(1)
  })

  it('opens at the top on a first visit', async () => {
    mountListPage(ref(true))
    await flushPromises()

    expect(scrollTo).toHaveBeenCalledWith(scrolledTo(0))
  })

  it('saves the offset when the page goes away', () => {
    const wrapper = mountListPage(ref(true))
    setWindowScroll(1234)

    wrapper.unmount()

    expect(useCollectionFiltersStore().scrollPositions.collection).toBe(1234)
  })

  it('remembers nothing once the reader has signed out', () => {
    const wrapper = mountListPage(ref(true))
    setWindowScroll(1234)

    useAuthStore().logout()
    wrapper.unmount()

    expect(useCollectionFiltersStore().scrollPositions.collection).toBeUndefined()
  })

  it('does not scroll another page when its list arrives after the reader left', async () => {
    useCollectionFiltersStore().saveScroll('collection', 480)
    const ready = ref(false)
    const wrapper = mountListPage(ready)

    wrapper.unmount()
    ready.value = true
    await flushPromises()

    expect(scrollTo).toHaveBeenCalledTimes(1)
    expect(scrollTo).toHaveBeenCalledWith(scrolledTo(0))
  })
})
