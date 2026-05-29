import { ref, onScopeDispose, toValue } from 'vue'
import type { MaybeRefOrGetter } from 'vue'

type DetectedCallback = (isbn: string) => void

interface BarcodeScannerComposable {
  isSupported: ReturnType<typeof ref<boolean>>
  isScanning: ReturnType<typeof ref<boolean>>
  error: ReturnType<typeof ref<string | null>>
  start: () => Promise<void>
  stop: () => void
  onDetected: (callback: DetectedCallback) => void
}

interface BarcodeDetectorLike {
  detect: (source: HTMLVideoElement) => Promise<Array<{ rawValue: string; format: string }>>
}

interface BarcodeDetectorConstructor {
  new (options: { formats: string[] }): BarcodeDetectorLike
  getSupportedFormats?: () => Promise<string[]>
}

export function useBarcodeScanner(
  videoRef: MaybeRefOrGetter<HTMLVideoElement | null>,
): BarcodeScannerComposable {
  const isSupported = ref(false)
  const isScanning = ref(false)
  const error = ref<string | null>(null)

  let detector: BarcodeDetectorLike | null = null
  let stream: MediaStream | null = null
  let animationFrameId: number | null = null
  let detectedCallback: DetectedCallback | null = null

  async function resolveDetector(): Promise<BarcodeDetectorLike | null> {
    if ('BarcodeDetector' in globalThis) {
      const NativeDetector = (globalThis as Record<string, unknown>)
        .BarcodeDetector as BarcodeDetectorConstructor
      return new NativeDetector({ formats: ['ean_13'] })
    }

    try {
      const { BarcodeDetector: Ponyfill } = await import('barcode-detector/ponyfill')
      const PonyfillConstructor = Ponyfill as unknown as BarcodeDetectorConstructor
      return new PonyfillConstructor({ formats: ['ean_13'] })
    } catch {
      return null
    }
  }

  async function start(): Promise<void> {
    error.value = null

    if (!navigator.mediaDevices?.getUserMedia) {
      isSupported.value = false
      error.value = 'Camera not available'
      return
    }

    const resolvedDetector = await resolveDetector()
    if (resolvedDetector === null) {
      isSupported.value = false
      error.value = 'Barcode detection not supported'
      return
    }

    detector = resolvedDetector
    isSupported.value = true

    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'environment' },
      })
    } catch (err) {
      isSupported.value = false
      error.value = err instanceof Error ? err.message : 'Camera access denied'
      return
    }

    const videoElement = toValue(videoRef)
    if (videoElement) {
      videoElement.srcObject = stream
      await videoElement.play()
    }

    isScanning.value = true
    scanLoop()
  }

  function scanLoop(): void {
    const videoElement = toValue(videoRef)
    if (!isScanning.value || detector === null || videoElement === null) {
      return
    }

    animationFrameId = requestAnimationFrame(async () => {
      if (!isScanning.value || detector === null) return

      try {
        const barcodes = await detector.detect(videoElement)
        for (const barcode of barcodes) {
          if (barcode.format === 'ean_13' && barcode.rawValue) {
            detectedCallback?.(barcode.rawValue)
            stop()
            return
          }
        }
      } catch {
        // detection errors are non-fatal — continue scanning
      }

      scanLoop()
    })
  }

  function stop(): void {
    isScanning.value = false

    if (animationFrameId !== null) {
      cancelAnimationFrame(animationFrameId)
      animationFrameId = null
    }

    if (stream !== null) {
      for (const track of stream.getTracks()) {
        track.stop()
      }
      stream = null
    }

    const videoElement = toValue(videoRef)
    if (videoElement) {
      videoElement.srcObject = null
    }
  }

  function onDetected(callback: DetectedCallback): void {
    detectedCallback = callback
  }

  onScopeDispose(stop)

  return { isSupported, isScanning, error, start, stop, onDetected }
}
