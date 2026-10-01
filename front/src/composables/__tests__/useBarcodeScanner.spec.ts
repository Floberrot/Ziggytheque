import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { effectScope } from 'vue'

type DecodeCallback = (result: { getText: () => string } | null) => void

const mockDecode = vi.fn()
const mockStop = vi.fn()

const readerOptions = vi.fn()

vi.mock('@zxing/browser', () => {
  class MockBrowserMultiFormatOneDReader {
    decodeFromConstraints = mockDecode
    constructor(hints: unknown, options: unknown) {
      readerOptions(hints, options)
    }
  }
  return { BrowserMultiFormatOneDReader: MockBrowserMultiFormatOneDReader }
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

  it('opens the rear camera in HD and decodes book barcodes only', async () => {
    const scope = effectScope()
    await scope.run(async () => {
      const { start } = useBarcodeScanner()
      await start(document.createElement('video'), vi.fn())
    })

    const constraints = (mockDecode.mock.calls[0] as unknown[])[0] as MediaStreamConstraints
    expect(constraints.video).toMatchObject({ facingMode: { ideal: 'environment' }, width: { ideal: 1920 } })
    const [hints, options] = readerOptions.mock.calls[0] as [Map<unknown, unknown>, { delayBetweenScanAttempts: number }]
    expect(hints.size).toBe(2)
    expect(options.delayBetweenScanAttempts).toBeLessThan(500)

    scope.stop()
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

  it('reports a missing camera', async () => {
    mockDecode.mockRejectedValueOnce(new DOMException('none', 'NotFoundError'))
    const scope = effectScope()
    let scanner: ReturnType<typeof useBarcodeScanner> | undefined

    await scope.run(async () => {
      scanner = useBarcodeScanner()
      await scanner.start(document.createElement('video'), vi.fn())
    })

    expect(scanner?.errorMessage.value).toContain('Aucune caméra')
    scope.stop()
  })

  it('stops a camera that finishes opening after stop()', async () => {
    let resolveOpen: (controls: { stop: () => void }) => void = () => {}
    mockDecode.mockReturnValueOnce(new Promise((resolve) => { resolveOpen = resolve }))
    const scope = effectScope()
    let scanner: ReturnType<typeof useBarcodeScanner> | undefined

    let opening: Promise<void> | undefined
    scope.run(() => {
      scanner = useBarcodeScanner()
      opening = scanner.start(document.createElement('video'), vi.fn())
    })
    // The user closed the scanner while the camera permission prompt was open.
    await Promise.resolve()
    scanner?.stop()
    resolveOpen({ stop: mockStop })
    await opening

    expect(mockStop).toHaveBeenCalledOnce()
    expect(scanner?.isScanning.value).toBe(false)
    scope.stop()
  })

  describe('native BarcodeDetector', () => {
    const trackStop = vi.fn()
    const detect = vi.fn()

    beforeEach(() => {
      trackStop.mockReset()
      detect.mockReset()
      class MockBarcodeDetector {
        static getSupportedFormats = vi.fn().mockResolvedValue(['ean_13', 'qr_code'])
        detect = detect
      }
      vi.stubGlobal('BarcodeDetector', MockBarcodeDetector)
      Object.defineProperty(navigator, 'mediaDevices', {
        configurable: true,
        value: { getUserMedia: vi.fn().mockResolvedValue({ getTracks: () => [{ stop: trackStop }] }) },
      })
    })

    afterEach(() => {
      vi.unstubAllGlobals()
    })

    function playableVideo(): HTMLVideoElement {
      const video = document.createElement('video')
      Object.defineProperty(video, 'readyState', { value: 4 })
      video.play = vi.fn().mockResolvedValue(undefined)
      return video
    }

    it('reads codes with the browser detector instead of zxing', async () => {
      detect.mockResolvedValue([{ rawValue: '9782344050002' }])
      const scope = effectScope()
      const onDecode = vi.fn()

      await scope.run(async () => {
        const { start } = useBarcodeScanner()
        await start(playableVideo(), onDecode)
      })

      await vi.waitFor(() => expect(onDecode).toHaveBeenCalledWith('9782344050002'))
      expect(mockDecode).not.toHaveBeenCalled()
      // One-shot: the camera tracks are released after the first read.
      expect(trackStop).toHaveBeenCalled()

      scope.stop()
    })
  })

  describe('startContinuous', () => {
    it('keeps the camera on and reports every new code', async () => {
      const scope = effectScope()
      const onDecode = vi.fn()
      const video = document.createElement('video')

      await scope.run(async () => {
        const { startContinuous } = useBarcodeScanner()
        await startContinuous(video, onDecode)
      })

      const cb = decodeCallback()
      cb({ getText: () => '9782811645632' })
      cb({ getText: () => '9782723425483' })

      expect(onDecode).toHaveBeenNthCalledWith(1, '9782811645632')
      expect(onDecode).toHaveBeenNthCalledWith(2, '9782723425483')
      expect(mockStop).not.toHaveBeenCalled()

      scope.stop()
    })

    it('ignores the same code while it stays in view, then accepts it after the cooldown', async () => {
      vi.useFakeTimers()
      const scope = effectScope()
      const onDecode = vi.fn()
      const video = document.createElement('video')

      await scope.run(async () => {
        const { startContinuous } = useBarcodeScanner()
        await startContinuous(video, onDecode, 1000)
      })

      const cb = decodeCallback()
      cb({ getText: () => '9782811645632' })
      vi.advanceTimersByTime(500)
      cb({ getText: () => '9782811645632' })
      expect(onDecode).toHaveBeenCalledTimes(1)

      vi.advanceTimersByTime(1500)
      cb({ getText: () => '9782811645632' })
      expect(onDecode).toHaveBeenCalledTimes(2)

      scope.stop()
      vi.useRealTimers()
    })

    it('reports a camera permission error', async () => {
      mockDecode.mockRejectedValue(new DOMException('denied', 'NotAllowedError'))
      const scope = effectScope()
      let scanner: ReturnType<typeof useBarcodeScanner> | undefined

      await scope.run(async () => {
        scanner = useBarcodeScanner()
        await scanner.startContinuous(document.createElement('video'), vi.fn())
      })

      expect(scanner?.isScanning.value).toBe(false)
      expect(scanner?.errorMessage.value).toContain('Accès caméra refusé')

      scope.stop()
    })
  })
})
