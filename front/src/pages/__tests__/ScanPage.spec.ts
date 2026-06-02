import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import { createRouter, createWebHashHistory } from 'vue-router'

vi.mock('@/api/manga', () => ({
  submitScan: vi.fn(),
}))

type DecodeCallback = (isbn: string) => void
const mockScannerInstances: { start: ReturnType<typeof vi.fn>; stop: ReturnType<typeof vi.fn>; isScanning: { value: boolean }; errorMessage: { value: string | null } }[] = []

vi.mock('@/composables/useBarcodeScanner', () => ({
  useBarcodeScanner: vi.fn(() => {
    const instance = {
      start: vi.fn().mockResolvedValue(undefined),
      stop: vi.fn(),
      isScanning: { value: false },
      errorMessage: { value: null },
    }
    mockScannerInstances.push(instance)
    return instance
  }),
}))

import { submitScan } from '@/api/manga'
import ScanPage from '../ScanPage.vue'

const mockSubmitScan = vi.mocked(submitScan)

const i18n = createI18n({
  legacy: false,
  locale: 'en',
  messages: {
    en: {
      scan: {
        title: 'Scan an ISBN',
        instructions: 'Point camera at barcode.',
        success: 'ISBN sent: {isbn}',
        scanAnother: 'Scan another',
        expired: 'Link expired.',
        invalidCode: 'Invalid barcode.',
        cameraError: 'Camera error.',
      },
    },
  },
})

function makeRouter(token = 'test-token') {
  const router = createRouter({
    history: createWebHashHistory(),
    routes: [
      { path: '/scan/:token', name: 'scan', component: ScanPage },
    ],
  })
  router.push(`/scan/${token}`)
  return router
}

describe('ScanPage', () => {
  beforeEach(() => {
    mockScannerInstances.length = 0
    vi.clearAllMocks()
    mockSubmitScan.mockResolvedValue(undefined)
  })

  async function mountPage(token = 'test-token') {
    const router = makeRouter(token)
    await router.isReady()
    const wrapper = mount(ScanPage, {
      global: { plugins: [i18n, router] },
    })
    await wrapper.vm.$nextTick()
    return wrapper
  }

  it('renders scan instructions', async () => {
    const wrapper = await mountPage()
    expect(wrapper.text()).toContain('Scan an ISBN')
  })

  it('calls submitScan when onScan is triggered', async () => {
    const wrapper = await mountPage('my-token')

    expect(mockScannerInstances).toHaveLength(1)
    const scanner = mockScannerInstances[0]
    const startCall = scanner.start.mock.calls[0] as unknown[]
    const onScanCallback = startCall[1] as DecodeCallback

    await onScanCallback('9782811645632')

    expect(mockSubmitScan).toHaveBeenCalledWith({ scanToken: 'my-token', isbn: '9782811645632' })

    await wrapper.vm.$nextTick()
    expect(wrapper.text()).toContain('9782811645632')
  })

  it('shows expired message on 410 error', async () => {
    mockSubmitScan.mockRejectedValueOnce({ response: { status: 410 } })

    const wrapper = await mountPage()
    const scanner = mockScannerInstances[0]
    const startCall = scanner.start.mock.calls[0] as unknown[]
    const onScanCallback = startCall[1] as DecodeCallback

    await onScanCallback('9782811645632')
    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toContain('Link expired.')
  })
})
