import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { effectScope, ref } from 'vue'
import { useBarcodeScanner } from '../useBarcodeScanner'

interface MockTrack {
  stop: ReturnType<typeof vi.fn>
}

function makeTrack(): MockTrack {
  return { stop: vi.fn() }
}

function makeStream(tracks: MockTrack[]) {
  return {
    getTracks: () => tracks,
  } as unknown as MediaStream
}

describe('useBarcodeScanner', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    // Remove BarcodeDetector from global scope
    delete (globalThis as Record<string, unknown>).BarcodeDetector
    // Reset mediaDevices
    Object.defineProperty(globalThis.navigator, 'mediaDevices', {
      value: undefined,
      configurable: true,
      writable: true,
    })
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('sets isSupported=false and error when getUserMedia is not available', async () => {
    const videoRef = ref<HTMLVideoElement | null>(null)
    const scope = effectScope()

    let scanner: ReturnType<typeof useBarcodeScanner> | undefined

    scope.run(() => {
      scanner = useBarcodeScanner(videoRef)
    })

    await scanner!.start()

    expect(scanner!.isSupported.value).toBe(false)
    expect(scanner!.error.value).toBeTruthy()

    scope.stop()
  })

  it('sets isSupported=false when barcode detection is unavailable', async () => {
    const track = makeTrack()
    const stream = makeStream([track])

    Object.defineProperty(globalThis.navigator, 'mediaDevices', {
      value: {
        getUserMedia: vi.fn().mockResolvedValue(stream),
      },
      configurable: true,
      writable: true,
    })

    // Mock import of barcode-detector/ponyfill to fail
    vi.doMock('barcode-detector/ponyfill', () => {
      throw new Error('Not available')
    })

    const videoRef = ref<HTMLVideoElement | null>(null)
    const scope = effectScope()
    let scanner: ReturnType<typeof useBarcodeScanner> | undefined

    scope.run(() => {
      scanner = useBarcodeScanner(videoRef)
    })

    await scanner!.start()

    expect(scanner!.isSupported.value).toBe(false)

    scope.stop()
  })

  it('calls onDetected with isbn and stops when ean_13 barcode detected', async () => {
    const mockIsbn = '9782344020812'
    const track = makeTrack()
    const stream = makeStream([track])

    const mockDetector = {
      detect: vi.fn().mockResolvedValue([{ rawValue: mockIsbn, format: 'ean_13' }]),
    }

    ;(globalThis as Record<string, unknown>).BarcodeDetector = vi
      .fn()
      .mockImplementation(function () { return mockDetector })

    const mockVideo = {
      srcObject: null as MediaStream | null,
      play: vi.fn().mockResolvedValue(undefined),
    } as unknown as HTMLVideoElement

    Object.defineProperty(globalThis.navigator, 'mediaDevices', {
      value: {
        getUserMedia: vi.fn().mockResolvedValue(stream),
      },
      configurable: true,
      writable: true,
    })

    // Mock requestAnimationFrame to execute synchronously
    vi.stubGlobal(
      'requestAnimationFrame',
      (callback: FrameRequestCallback) => {
        callback(0)
        return 0
      },
    )

    const videoRef = ref<HTMLVideoElement | null>(mockVideo)
    const scope = effectScope()
    let scanner: ReturnType<typeof useBarcodeScanner> | undefined

    const onDetectedCallback = vi.fn()

    scope.run(() => {
      scanner = useBarcodeScanner(videoRef)
      scanner.onDetected(onDetectedCallback)
    })

    await scanner!.start()

    expect(onDetectedCallback).toHaveBeenCalledWith(mockIsbn)
    expect(scanner!.isScanning.value).toBe(false)
    expect(track.stop).toHaveBeenCalled()

    scope.stop()
  })

  it('stops scanning and releases tracks on scope dispose', async () => {
    const track = makeTrack()
    const stream = makeStream([track])

    ;(globalThis as Record<string, unknown>).BarcodeDetector = vi.fn().mockImplementation(function () {
      return { detect: vi.fn().mockResolvedValue([]) }
    })

    const mockVideo = {
      srcObject: null as MediaStream | null,
      play: vi.fn().mockResolvedValue(undefined),
    } as unknown as HTMLVideoElement

    Object.defineProperty(globalThis.navigator, 'mediaDevices', {
      value: {
        getUserMedia: vi.fn().mockResolvedValue(stream),
      },
      configurable: true,
      writable: true,
    })

    vi.stubGlobal('requestAnimationFrame', vi.fn().mockReturnValue(0))
    vi.stubGlobal('cancelAnimationFrame', vi.fn())

    const videoRef = ref<HTMLVideoElement | null>(mockVideo)
    const scope = effectScope()
    let scanner: ReturnType<typeof useBarcodeScanner> | undefined

    scope.run(() => {
      scanner = useBarcodeScanner(videoRef)
    })

    await scanner!.start()
    scope.stop()

    expect(track.stop).toHaveBeenCalled()
  })
})
