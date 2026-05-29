import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import EditionPicker from '../EditionPicker.vue'
import type { DiscoveredEdition } from '@/api/manga'

const mockDiscoverEditions = vi.fn()

vi.mock('@/api/manga', () => ({
  discoverEditions: (...args: unknown[]) => mockDiscoverEditions(...args),
}))

vi.mock('@/components/molecules/EditionCard.vue', () => ({
  default: {
    name: 'EditionCard',
    props: ['edition'],
    emits: ['select'],
    template: '<button data-test="edition-card" @click="$emit(\'select\')">{{ edition.publisher }}</button>',
  },
}))

const i18n = createI18n({
  legacy: false,
  locale: 'en',
  messages: {
    en: {
      add: {
        editionsFor: 'Available editions for',
        noEditionsFound: 'No editions found — fill in manually',
        fillManually: 'Fill manually',
      },
      common: { or: 'or' },
      country: { fr: 'France', it: 'Italy', es: 'Spain', de: 'Germany', us: 'United States', jp: 'Japan' },
    },
  },
})

const sampleEdition: DiscoveredEdition = {
  publisher: 'Glénat',
  editionLabel: 'Grand Format',
  year: 2020,
  language: 'fr',
  coverUrl: null,
  volumeCount: 12,
  sampleIsbn: '9782344020814',
  source: 'bnf',
}

const flushTimers = () => new Promise((resolve) => setTimeout(resolve, 0))

describe('EditionPicker', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('shows loading spinner while fetching', async () => {
    mockDiscoverEditions.mockReturnValue(new Promise(() => {}))

    const wrapper = mount(EditionPicker, {
      global: { plugins: [i18n] },
      props: { title: 'Berserk' },
    })

    await wrapper.vm.$nextTick()

    expect(wrapper.find('.loading').exists()).toBe(true)
  })

  it('shows edition cards when editions are loaded', async () => {
    mockDiscoverEditions.mockResolvedValue([sampleEdition])

    const wrapper = mount(EditionPicker, {
      global: { plugins: [i18n] },
      props: { title: 'Berserk' },
    })

    await flushTimers()

    expect(wrapper.findAll('[data-test="edition-card"]').length).toBe(1)
    expect(wrapper.text()).toContain('Glénat')
  })

  it('emits select with the edition when a card is clicked', async () => {
    mockDiscoverEditions.mockResolvedValue([sampleEdition])

    const wrapper = mount(EditionPicker, {
      global: { plugins: [i18n] },
      props: { title: 'Berserk' },
    })

    await flushTimers()

    await wrapper.find('[data-test="edition-card"]').trigger('click')

    expect(wrapper.emitted('select')).toBeTruthy()
    expect(wrapper.emitted('select')![0]).toEqual([sampleEdition])
  })

  it('emits skip when fill manually button is clicked', async () => {
    mockDiscoverEditions.mockResolvedValue([])

    const wrapper = mount(EditionPicker, {
      global: { plugins: [i18n] },
      props: { title: 'Berserk' },
    })

    await flushTimers()

    await wrapper.find('[data-test="fill-manually"]').trigger('click')

    expect(wrapper.emitted('skip')).toBeTruthy()
  })

  it('shows no editions found message when the list is empty', async () => {
    mockDiscoverEditions.mockResolvedValue([])

    const wrapper = mount(EditionPicker, {
      global: { plugins: [i18n] },
      props: { title: 'Berserk' },
    })

    await flushTimers()

    expect(wrapper.text()).toContain('No editions found')
  })

  it('defaults to France and calls discoverEditions with the FR country code', async () => {
    mockDiscoverEditions.mockResolvedValue([])

    mount(EditionPicker, {
      global: { plugins: [i18n] },
      props: { title: 'One Piece' },
    })

    await flushTimers()

    expect(mockDiscoverEditions).toHaveBeenCalledWith('One Piece', 'FR')
  })

  it('honours the initialCountry prop', async () => {
    mockDiscoverEditions.mockResolvedValue([])

    mount(EditionPicker, {
      global: { plugins: [i18n] },
      props: { title: 'One Piece', initialCountry: 'US' },
    })

    await flushTimers()

    expect(mockDiscoverEditions).toHaveBeenCalledWith('One Piece', 'US')
  })

  it('re-fetches editions when a different country is selected', async () => {
    mockDiscoverEditions.mockResolvedValue([])

    const wrapper = mount(EditionPicker, {
      global: { plugins: [i18n] },
      props: { title: 'Berserk' },
    })

    await flushTimers()
    expect(mockDiscoverEditions).toHaveBeenLastCalledWith('Berserk', 'FR')

    await wrapper.find('[data-test="country-JP"]').trigger('click')
    await flushTimers()

    expect(mockDiscoverEditions).toHaveBeenLastCalledWith('Berserk', 'JP')
    expect(mockDiscoverEditions).toHaveBeenCalledTimes(2)
  })
})
