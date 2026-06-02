import { describe, it, expect, vi, beforeEach } from 'vitest'
import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import BaseLazyImage from '../BaseLazyImage.vue'

const observers: MockIntersectionObserver[] = []

class MockIntersectionObserver {
  observe = vi.fn()
  disconnect = vi.fn()
  unobserve = vi.fn()
  takeRecords = vi.fn(() => [])
  root = null
  rootMargin = ''
  thresholds: number[] = []
  private readonly callback: IntersectionObserverCallback

  constructor(callback: IntersectionObserverCallback) {
    this.callback = callback
    observers.push(this)
  }

  trigger(isIntersecting: boolean): void {
    this.callback(
      [{ isIntersecting } as IntersectionObserverEntry],
      this as unknown as IntersectionObserver,
    )
  }
}

vi.stubGlobal('IntersectionObserver', MockIntersectionObserver)

describe('BaseLazyImage', () => {
  beforeEach(() => {
    observers.length = 0
  })

  it('does not request the image until it scrolls into view', async () => {
    const wrapper = mount(BaseLazyImage, { props: { src: 'http://x/cover.jpg', alt: 'Tome 1' } })

    // Off-screen: no <img> in the DOM, so no network request is fired.
    expect(wrapper.find('img').exists()).toBe(false)
    expect(observers).toHaveLength(1)

    observers[0].trigger(true)
    await nextTick()

    const img = wrapper.find('img')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe('http://x/cover.jpg')
    expect(img.attributes('alt')).toBe('Tome 1')
    // Loads once: the observer is disconnected after the tile appears.
    expect(observers[0].disconnect).toHaveBeenCalled()
  })

  it('fades the image in once it has loaded', async () => {
    const wrapper = mount(BaseLazyImage, { props: { src: 'http://x/c.jpg' } })

    observers[0].trigger(true)
    await nextTick()

    const img = wrapper.find('img')
    expect(img.attributes('style')).toContain('opacity: 0')

    await img.trigger('load')
    expect(wrapper.find('img').attributes('style')).toContain('opacity: 1')
  })

  it('shows the fallback slot when the image fails to load', async () => {
    const wrapper = mount(BaseLazyImage, {
      props: { src: 'http://x/broken.jpg' },
      slots: { fallback: '<span class="fb">42</span>' },
    })

    observers[0].trigger(true)
    await nextTick()
    await wrapper.find('img').trigger('error')

    expect(wrapper.find('.fb').exists()).toBe(true)
    expect(wrapper.find('img').exists()).toBe(false)
  })
})
