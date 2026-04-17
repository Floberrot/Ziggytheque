import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import CoverSourceBadge from '../CoverSourceBadge.vue'
import type { CoverSource } from '@/api/manga'

describe('CoverSourceBadge', () => {
  it('renders nothing when source is null', () => {
    const wrapper = mount(CoverSourceBadge, {
      props: { source: null as CoverSource },
    })
    expect(wrapper.html()).toBe('')
  })

  it('renders nothing when source is none', () => {
    const wrapper = mount(CoverSourceBadge, {
      props: { source: 'none' },
    })
    expect(wrapper.html()).toBe('')
  })

  it('renders Amazon logo with tooltip when source is amazon', () => {
    const wrapper = mount(CoverSourceBadge, {
      props: { source: 'amazon' },
    })
    expect(wrapper.html()).toContain('Amazon')
    expect(wrapper.html()).toContain('data-tip="Couvertures via Amazon"')
    expect(wrapper.find('svg').exists()).toBe(true)
  })

  it('renders Google logo with tooltip when source is google', () => {
    const wrapper = mount(CoverSourceBadge, {
      props: { source: 'google' },
    })
    expect(wrapper.html()).toContain('Google Books')
    expect(wrapper.html()).toContain('data-tip="Couvertures via Google Books"')
    expect(wrapper.find('svg').exists()).toBe(true)
  })
})
