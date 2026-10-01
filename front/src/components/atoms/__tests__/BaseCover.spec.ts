import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import BaseCover from '../BaseCover.vue'

function withNaturalSize(image: HTMLImageElement, width: number, height: number): void {
  Object.defineProperty(image, 'naturalWidth', { value: width, configurable: true })
  Object.defineProperty(image, 'naturalHeight', { value: height, configurable: true })
}

describe('BaseCover', () => {
  it('shows the book icon when there is no cover', () => {
    const wrapper = mount(BaseCover, { props: { src: null, alt: 'Berserk' } })

    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.attributes('aria-label')).toBe('Berserk')
  })

  it('routes anti-hotlink covers through the proxy', () => {
    const wrapper = mount(BaseCover, { props: { src: 'https://catalogue.bnf.fr/couverture?idArk=1' } })

    expect(wrapper.find('img').attributes('src')).toMatch(/^\/proxy\/cover\?url=/)
  })

  it('falls back and reports a cover that fails to load', async () => {
    const wrapper = mount(BaseCover, { props: { src: 'https://covers.example/1.jpg' } })

    await wrapper.find('img').trigger('error')

    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.emitted('missing')).toHaveLength(1)
  })

  it('treats a placeholder pixel as a missing cover', async () => {
    const wrapper = mount(BaseCover, { props: { src: 'https://covers.example/blank.gif' } })
    const image = wrapper.find('img')

    withNaturalSize(image.element as HTMLImageElement, 1, 1)
    await image.trigger('load')

    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.emitted('missing')).toHaveLength(1)
  })

  it('keeps a real cover', async () => {
    const wrapper = mount(BaseCover, { props: { src: 'https://covers.example/1.jpg' } })
    const image = wrapper.find('img')

    withNaturalSize(image.element as HTMLImageElement, 200, 300)
    await image.trigger('load')

    expect(wrapper.find('img').exists()).toBe(true)
    expect(wrapper.emitted('missing')).toBeUndefined()
  })

  it('tries again when the cover changes', async () => {
    const wrapper = mount(BaseCover, { props: { src: 'https://covers.example/1.jpg' } })
    await wrapper.find('img').trigger('error')

    await wrapper.setProps({ src: 'https://covers.example/2.jpg' })

    expect(wrapper.find('img').attributes('src')).toBe('https://covers.example/2.jpg')
  })

  it('renders the fallback slot', () => {
    const wrapper = mount(BaseCover, { props: { src: null }, slots: { fallback: '<span class="number">3</span>' } })

    expect(wrapper.find('.number').text()).toBe('3')
  })
})
