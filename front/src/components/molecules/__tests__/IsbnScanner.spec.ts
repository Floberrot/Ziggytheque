import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref } from 'vue'
import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import IsbnScanner from '../IsbnScanner.vue'

// Mock the composable
const mockOnDetected = vi.fn()
const mockStart = vi.fn()
const mockStop = vi.fn()
const mockIsSupported = ref(true)
const mockIsScanning = ref(false)
const mockError = ref<string | null>(null)

vi.mock('@/composables/useBarcodeScanner', () => ({
  useBarcodeScanner: () => ({
    isSupported: mockIsSupported,
    isScanning: mockIsScanning,
    error: mockError,
    start: mockStart,
    stop: mockStop,
    onDetected: mockOnDetected,
  }),
}))

const i18n = createI18n({
  legacy: false,
  locale: 'en',
  messages: {
    en: {
      scan: {
        title: 'Scan ISBN',
        cameraUnavailable: 'Camera not available',
      },
      common: { cancel: 'Cancel' },
    },
  },
})

describe('IsbnScanner', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockIsSupported.value = true
    mockIsScanning.value = false
    mockError.value = null
  })

  it('renders start button when not scanning', () => {
    const wrapper = mount(IsbnScanner, { global: { plugins: [i18n] } })
    expect(wrapper.find('button').text()).toContain('Scan ISBN')
  })

  it('calls onDetected callback with isbn on register', () => {
    mount(IsbnScanner, { global: { plugins: [i18n] } })
    expect(mockOnDetected).toHaveBeenCalledOnce()
  })

  it('shows camera unavailable message when not supported', async () => {
    mockIsSupported.value = false
    mockError.value = 'Camera not available'

    const wrapper = mount(IsbnScanner, { global: { plugins: [i18n] } })
    expect(wrapper.text()).toContain('Camera not available')
  })

  it('emits detected event when onDetected callback is triggered', async () => {
    let capturedCallback: ((isbn: string) => void) | undefined

    vi.mocked(mockOnDetected).mockImplementation((cb: (isbn: string) => void) => {
      capturedCallback = cb
    })

    const wrapper = mount(IsbnScanner, { global: { plugins: [i18n] } })

    capturedCallback?.('9782344020812')

    await wrapper.vm.$nextTick()
    expect(wrapper.emitted('detected')).toBeTruthy()
    expect(wrapper.emitted('detected')![0]).toEqual(['9782344020812'])
  })
})
