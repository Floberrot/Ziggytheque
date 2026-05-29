import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import { ref } from 'vue'
import ScanViaPhone from '../ScanViaPhone.vue'

const mockPairingUrl = ref<string | null>(null)
const mockStart = vi.fn()

vi.mock('@/composables/useScanSession', () => ({
  useScanSession: () => ({
    sessionId: ref<string | null>(null),
    pairingUrl: mockPairingUrl,
    start: mockStart,
    close: vi.fn(),
  }),
}))

vi.mock('qrcode', () => ({
  default: {
    toCanvas: vi.fn().mockResolvedValue(undefined),
  },
}))

const i18n = createI18n({
  legacy: false,
  locale: 'en',
  messages: {
    en: {
      scan: {
        scanWithPhone: 'Scan with phone',
      },
    },
  },
})

describe('ScanViaPhone', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    mockPairingUrl.value = null
    mockStart.mockImplementation(async () => {
      mockPairingUrl.value = 'http://localhost:5173/scan/test-session-123'
    })
  })

  it('renders and calls start on mount', async () => {
    const wrapper = mount(ScanViaPhone, { global: { plugins: [i18n] } })
    await new Promise((resolve) => setTimeout(resolve, 0))
    expect(mockStart).toHaveBeenCalledOnce()
    wrapper.unmount()
  })

  it('renders a canvas element for QR code', () => {
    const wrapper = mount(ScanViaPhone, { global: { plugins: [i18n] } })
    expect(wrapper.find('canvas').exists()).toBe(true)
    wrapper.unmount()
  })

  it('emits isbn event when onIsbn callback is triggered', async () => {
    let capturedCallback: ((isbn: string) => void) | undefined

    mockStart.mockImplementation(async ({ onIsbn }: { onIsbn: (isbn: string) => void }) => {
      capturedCallback = onIsbn
      mockPairingUrl.value = 'http://localhost:5173/scan/test-session-123'
    })

    const wrapper = mount(ScanViaPhone, { global: { plugins: [i18n] } })
    await new Promise((resolve) => setTimeout(resolve, 0))

    capturedCallback?.('9782344020812')

    await wrapper.vm.$nextTick()
    expect(wrapper.emitted('isbn')).toBeTruthy()
    expect(wrapper.emitted('isbn')![0]).toEqual(['9782344020812'])

    wrapper.unmount()
  })
})
