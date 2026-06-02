import { describe, it, expect, vi, beforeEach } from 'vitest'
import { effectScope } from 'vue'

type DecodeCallback = (result: { getText: () => string } | null) => void

const mockDecode = vi.fn()
const mockStop = vi.fn()

vi.mock('@zxing/browser', () => {
  class MockBrowserMultiFormatReader {
    decodeFromVideoDevice = mockDecode
  }
  return { BrowserMultiFormatReader: MockBrowserMultiFormatReader }
})

import { useBarcodeScanner } from '../useBarcodeScanner'

function decodeCallback(): DecodeCallback {
  const args = mockDecode.mock.calls[0] as unknown[]
  return args[2] as DecodeCallback
}

describe('useBarcodeScanner', () => {
  beforeEach(() => {
    mockDecode.mockReset()
    mockDecode.mockResolvedValue({ stop: mockStop })
    mockStop.mockReset()
  })

  it('calls onDecode when barcode is scanned', async () => {
    const scope = effectScope()
    const onDecode = vi.fn()
    const video = document.createElement('video')

    await scope.run(async () => {
      const { start } = useBarcodeScanner()
      await start(video, onDecode)
    })

    decodeCallback()({ getText: () => '9782811645632' })

    expect(onDecode).toHaveBeenCalledWith('9782811645632')

    scope.stop()
  })

  it('is one-shot: stops the camera and ignores repeat decodes', async () => {
    const scope = effectScope()
    const onDecode = vi.fn()
    const video = document.createElement('video')

    await scope.run(async () => {
      const { start } = useBarcodeScanner()
      await start(video, onDecode)
    })

    const cb = decodeCallback()
    cb({ getText: () => '9782811645632' })
    cb({ getText: () => '9782811645632' })

    expect(onDecode).toHaveBeenCalledTimes(1)
    expect(mockStop).toHaveBeenCalled()

    scope.stop()
  })

  it('does not call onDecode when result is null', async () => {
    const scope = effectScope()
    const onDecode = vi.fn()
    const video = document.createElement('video')

    await scope.run(async () => {
      const { start } = useBarcodeScanner()
      await start(video, onDecode)
    })

    decodeCallback()(null)

    expect(onDecode).not.toHaveBeenCalled()

    scope.stop()
  })

  it('stops scanner controls when scope is disposed', async () => {
    const scope = effectScope()
    const video = document.createElement('video')

    await scope.run(async () => {
      const { start } = useBarcodeScanner()
      await start(video, vi.fn())
    })

    expect(mockStop).not.toHaveBeenCalled()
    scope.stop()
    expect(mockStop).toHaveBeenCalledOnce()
  })

  it('sets errorMessage on NotAllowedError', async () => {
    mockDecode.mockRejectedValueOnce(new DOMException('Permission denied', 'NotAllowedError'))

    const scope = effectScope()
    const video = document.createElement('video')
    let errorMsg: string | null = null

    await scope.run(async () => {
      const { start, errorMessage } = useBarcodeScanner()
      await start(video, vi.fn())
      errorMsg = errorMessage.value
    })

    expect(errorMsg).toBeTruthy()

    scope.stop()
  })
})
