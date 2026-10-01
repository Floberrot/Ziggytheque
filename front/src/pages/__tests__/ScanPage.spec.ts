import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import { createRouter, createWebHashHistory } from 'vue-router'

vi.mock('@/api/manga', () => ({
  submitScan: vi.fn(),
}))

type DecodeCallback = (isbn: string) => void
const mockScannerInstances: {
  start: ReturnType<typeof vi.fn>
  startContinuous: ReturnType<typeof vi.fn>
  stop: ReturnType<typeof vi.fn>
  toggleTorch: ReturnType<typeof vi.fn>
  isScanning: { value: boolean }
  errorMessage: { value: string | null }
  torchAvailable: { value: boolean }
  torchOn: { value: boolean }
}[] = []

vi.mock('@/composables/useBarcodeScanner', () => ({
  useBarcodeScanner: vi.fn(() => {
    const instance = {
      start: vi.fn().mockResolvedValue(undefined),
      startContinuous: vi.fn().mockResolvedValue(undefined),
      stop: vi.fn(),
      toggleTorch: vi.fn(),
      isScanning: { value: false },
      errorMessage: { value: null },
      torchAvailable: { value: false },
      torchOn: { value: false },
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
        batchSent: 'No volume sent | 1 volume sent | {count} volumes sent',
      },
    },
  },
})

function makeRouter(token = 'test-token', query = '') {
  const router = createRouter({
    history: createWebHashHistory(),
    routes: [
      { path: '/scan/:token', name: 'scan', component: ScanPage },
    ],
  })
  router.push(`/scan/${token}${query}`)
  return router
}

describe('ScanPage', () => {
  beforeEach(() => {
    mockScannerInstances.length = 0
    vi.clearAllMocks()
    mockSubmitScan.mockResolvedValue(undefined)
  })

  async function mountPage(token = 'test-token', query = '') {
    const router = makeRouter(token, query)
    await router.isReady()
    const wrapper = mount(ScanPage, {
      global: { plugins: [i18n, router] },
    })
    await flushPromises()
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

  it('reopens the camera after an unreadable code in one-shot mode', async () => {
    mockSubmitScan.mockRejectedValueOnce({ response: { status: 422 } })

    const wrapper = await mountPage()
    const scanner = mockScannerInstances[0]
    const onScanCallback = (scanner.start.mock.calls[0] as unknown[])[1] as DecodeCallback

    await onScanCallback('1234567890128')
    await flushPromises()

    expect(wrapper.text()).toContain('Invalid barcode.')
    expect(scanner.start).toHaveBeenCalledTimes(2)
  })

  it('scans another book after a success', async () => {
    const wrapper = await mountPage()
    const scanner = mockScannerInstances[0]
    const onScanCallback = (scanner.start.mock.calls[0] as unknown[])[1] as DecodeCallback

    await onScanCallback('9782811645632')
    await flushPromises()
    await wrapper.find('button').trigger('click')
    await flushPromises()

    expect(scanner.start).toHaveBeenCalledTimes(2)
    // The new preview element is the one handed to the scanner.
    expect((scanner.start.mock.calls[1] as unknown[])[0]).toBeInstanceOf(HTMLVideoElement)
  })

  it('keeps scanning in batch mode and counts the volumes sent', async () => {
    const wrapper = await mountPage('batch-token', '?batch=1')

    const scanner = mockScannerInstances[0]
    expect(scanner.start).not.toHaveBeenCalled()
    const continuousCall = scanner.startContinuous.mock.calls[0] as unknown[]
    const onScanCallback = continuousCall[1] as DecodeCallback

    await onScanCallback('9782811645632')
    await onScanCallback('9782723425483')
    await wrapper.vm.$nextTick()

    expect(mockSubmitScan).toHaveBeenCalledTimes(2)
    expect(wrapper.text()).toContain('2 volumes sent')
    expect(wrapper.text()).toContain('9782723425483')
    expect(wrapper.text()).not.toContain('Scan another')
  })
})
