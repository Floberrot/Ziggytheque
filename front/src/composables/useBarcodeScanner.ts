import { ref, onScopeDispose } from 'vue'
import type { DecodeHintType } from '@zxing/library'

/** Rear camera in HD: a book barcode is small and thin-lined. */
const CAMERA_CONSTRAINTS: MediaStreamConstraints = {
  audio: false,
  video: {
    facingMode: { ideal: 'environment' },
    width: { ideal: 1920 },
    height: { ideal: 1080 },
  },
}

/** Book barcodes are EAN-13 (ISBN); EAN-8 / UPC-A cover the odd import. */
const NATIVE_FORMATS = ['ean_13', 'ean_8', 'upc_a']
const NATIVE_SCAN_INTERVAL_MS = 120
const ZXING_SCAN_INTERVAL_MS = 100

// The Barcode Detection API (Chrome / Android) is not in TypeScript's DOM lib yet.
interface DetectedBarcode { rawValue: string }
interface NativeBarcodeDetector { detect(source: HTMLVideoElement): Promise<DetectedBarcode[]> }
interface NativeBarcodeDetectorConstructor {
  new (options: { formats: string[] }): NativeBarcodeDetector
  getSupportedFormats(): Promise<string[]>
}

interface ScanControls { stop(): void }

/** The i18n key explaining why the camera could not start (translated by the caller). */
function cameraErrorKey(err: unknown): string {
  if (err instanceof DOMException) {
    if (err.name === 'NotAllowedError') {
      return 'scanner.cameraDenied'
    }
    if (err.name === 'NotFoundError' || err.name === 'OverconstrainedError') {
      return 'scanner.noCamera'
    }
    if (err.name === 'NotReadableError') {
      return 'scanner.cameraBusy'
    }
  }
  return 'scanner.cameraFailed'
}

/** The browser's own detector: much faster and steadier than decoding in JS. */
async function nativeDetector(): Promise<NativeBarcodeDetector | null> {
  const Detector = (globalThis as unknown as { BarcodeDetector?: NativeBarcodeDetectorConstructor }).BarcodeDetector
  if (!Detector) return null
  try {
    const supported = await Detector.getSupportedFormats()
    const formats = NATIVE_FORMATS.filter((format) => supported.includes(format))
    return formats.includes('ean_13') ? new Detector({ formats }) : null
  } catch {
    return null
  }
}

/** Phones often open the rear camera in fixed focus: ask for continuous autofocus. */
function enableContinuousFocus(track: MediaStreamTrack | undefined): void {
  if (!track || typeof track.getCapabilities !== 'function') return
  const focusModes = (track.getCapabilities() as unknown as { focusMode?: string[] }).focusMode ?? []
  if (focusModes.includes('continuous')) {
    track.applyConstraints({ advanced: [{ focusMode: 'continuous' } as unknown as MediaTrackConstraintSet] }).catch(() => {})
  }
}

async function startNative(
  detector: NativeBarcodeDetector,
  video: HTMLVideoElement,
  onCode: (code: string) => void,
): Promise<ScanControls> {
  const stream = await navigator.mediaDevices.getUserMedia(CAMERA_CONSTRAINTS)
  video.srcObject = stream
  video.muted = true
  video.setAttribute('playsinline', 'true')
  await video.play()

  let stopped = false
  let timer: ReturnType<typeof setTimeout> | null = null
  const scanFrame = async (): Promise<void> => {
    if (stopped) return
    try {
      if (video.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA) {
        const [barcode] = await detector.detect(video)
        if (barcode && !stopped) onCode(barcode.rawValue)
      }
    } catch {
      // A frame that cannot be read: try the next one.
    }
    if (!stopped) timer = setTimeout(scanFrame, NATIVE_SCAN_INTERVAL_MS)
  }
  void scanFrame()

  return {
    stop() {
      stopped = true
      if (timer) clearTimeout(timer)
      stream.getTracks().forEach((track) => track.stop())
      video.srcObject = null
    },
  }
}

async function startZxing(video: HTMLVideoElement, onCode: (code: string) => void): Promise<ScanControls> {
  // zxing weighs hundreds of kB and only browsers without a native detector need it:
  // it is fetched on their first scan, never shipped with the page.
  const [{ BrowserMultiFormatOneDReader }, zxingLibrary] = await Promise.all([
    import('@zxing/browser'),
    import('@zxing/library'),
  ])
  const { BarcodeFormat, DecodeHintType: DecodeHint } = zxingLibrary
  const hints = new Map<DecodeHintType, unknown>([
    [DecodeHint.POSSIBLE_FORMATS, [BarcodeFormat.EAN_13, BarcodeFormat.EAN_8, BarcodeFormat.UPC_A]],
    [DecodeHint.TRY_HARDER, true],
  ])
  const reader = new BrowserMultiFormatOneDReader(hints, {
    delayBetweenScanAttempts: ZXING_SCAN_INTERVAL_MS,
    delayBetweenScanSuccess: ZXING_SCAN_INTERVAL_MS,
  })
  return reader.decodeFromConstraints(CAMERA_CONSTRAINTS, video, (result) => {
    if (result) onCode(result.getText())
  })
}

function videoTrack(video: HTMLVideoElement): MediaStreamTrack | undefined {
  const stream = video.srcObject
  return typeof MediaStream !== 'undefined' && stream instanceof MediaStream ? stream.getVideoTracks()[0] : undefined
}

export function useBarcodeScanner() {
  const isScanning = ref(false)
  /** i18n key of the camera error, or null — the caller translates it. */
  const errorKey = ref<string | null>(null)
  /** The camera has a light the user can switch on (dim shelves, glossy covers). */
  const torchAvailable = ref(false)
  const torchOn = ref(false)
  let controls: ScanControls | null = null
  let activeTrack: MediaStreamTrack | undefined
  // A stop() while the camera is still opening must win over that late start.
  let generation = 0

  async function open(video: HTMLVideoElement, onCode: (code: string) => void): Promise<void> {
    stop()
    const current = ++generation
    errorKey.value = null
    isScanning.value = true

    try {
      const detector = await nativeDetector()
      const opened = detector ? await startNative(detector, video, onCode) : await startZxing(video, onCode)
      if (current !== generation) {
        opened.stop()
        return
      }
      controls = opened
      activeTrack = videoTrack(video)
      enableContinuousFocus(activeTrack)
      torchAvailable.value = typeof activeTrack?.getCapabilities === 'function'
        && (activeTrack.getCapabilities() as unknown as { torch?: boolean }).torch === true
    } catch (err) {
      if (current !== generation) return
      isScanning.value = false
      errorKey.value = cameraErrorKey(err)
    }
  }

  /** One-shot: the camera stops on the first code read. */
  async function start(video: HTMLVideoElement, onDecode: (isbn: string) => void): Promise<void> {
    let handled = false
    await open(video, (code) => {
      // Decoders report every frame the barcode stays in view: keep the first.
      if (handled) return
      handled = true
      stop()
      onDecode(code)
    })
  }

  /**
   * Batch mode: the camera stays on and every new code is reported. The same code
   * seen again within `cooldownMs` is ignored — it is still in front of the camera.
   */
  async function startContinuous(
    video: HTMLVideoElement,
    onDecode: (isbn: string) => void,
    cooldownMs = 3000,
  ): Promise<void> {
    let lastCode = ''
    let lastSeenAt = 0
    await open(video, (code) => {
      const now = Date.now()
      if (code === lastCode && now - lastSeenAt < cooldownMs) {
        lastSeenAt = now
        return
      }
      lastCode = code
      lastSeenAt = now
      onDecode(code)
    })
  }

  async function toggleTorch(): Promise<void> {
    if (!activeTrack || !torchAvailable.value) return
    const wanted = !torchOn.value
    try {
      await activeTrack.applyConstraints({ advanced: [{ torch: wanted } as unknown as MediaTrackConstraintSet] })
      torchOn.value = wanted
    } catch {
      torchAvailable.value = false
    }
  }

  function stop(): void {
    generation++
    if (controls) {
      controls.stop()
      controls = null
    }
    activeTrack = undefined
    torchAvailable.value = false
    torchOn.value = false
    isScanning.value = false
  }

  onScopeDispose(stop)

  return { isScanning, errorKey, torchAvailable, torchOn, start, startContinuous, toggleTorch, stop }
}
