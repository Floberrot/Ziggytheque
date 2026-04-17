import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import CoverSourceBadge from '../CoverSourceBadge.vue'
import type { CoverSource } from '@/api/manga'

describe('CoverSourceBadge', () => {
  it('renders nothing when source is null', () => {
    const wrapper = mount(CoverSourceBadge, {
      props: { source: null as CoverSource },
    })
    expect(wrapper.html()).toBe('<!--v-if-->')
  })

  it('renders nothing when source is none', () => {
    const wrapper = mount(CoverSourceBadge, {
      props: { source: 'none' },
    })
    expect(wrapper.html()).toBe('<!--v-if-->')
  })

  it('renders nothing when source is google but no searchQuery', () => {
    const wrapper = mount(CoverSourceBadge, {
      props: { source: 'google' },
    })
    expect(wrapper.html()).toBe('<!--v-if-->')
  })

  it('renders Google button with SVG when source is google and searchQuery is provided', () => {
    const wrapper = mount(CoverSourceBadge, {
      props: { source: 'google', searchQuery: 'test manga' },
    })
    expect(wrapper.find('button').exists()).toBe(true)
    expect(wrapper.find('svg').exists()).toBe(true)
    expect(wrapper.html()).toContain('data-tip="Chercher sur Google Books"')
  })

  it('opens Google Books with correct query when button is clicked', () => {
    const openSpy = vi.spyOn(window, 'open').mockImplementation(() => null)
    const wrapper = mount(CoverSourceBadge, {
      props: { source: 'google', searchQuery: 'Naruto' },
    })
    wrapper.find('button').trigger('click')
    expect(openSpy).toHaveBeenCalledWith(
      expect.stringContaining('books.google.com'),
      '_blank'
    )
    expect(openSpy).toHaveBeenCalledWith(
      expect.stringContaining('Naruto'),
      '_blank'
    )
    openSpy.mockRestore()
  })
})
