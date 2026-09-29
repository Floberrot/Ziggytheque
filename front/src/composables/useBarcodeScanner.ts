import { ref, onScopeDispose } from 'vue'
import { BrowserMultiFormatReader, type IScannerControls } from '@zxing/browser'

function cameraErrorMessage(err: unknown): string {
  if (err instanceof DOMException && err.name === 'NotAllowedError') {
    return 'Accès caméra refusé. Vérifiez les permissions et que la page est en HTTPS.'
  }
  return 'Impossible de démarrer le scanner caméra.'
}

export function useBarcodeScanner() {
  const isScanning = ref(false)
  const errorMessage = ref<string | null>(null)
  let controls: IScannerControls | null = null

  async function start(video: HTMLVideoElement, onDecode: (isbn: string) => void): Promise<void> {
    stop()
    errorMessage.value = null
    isScanning.value = true
    let handled = false

    try {
      const reader = new BrowserMultiFormatReader()
      controls = await reader.decodeFromVideoDevice(undefined, video, (result) => {
        // One-shot: zxing fires this on every frame, so stop the camera on the
        // first hit to avoid re-triggering the search and leaving the camera on.
        if (result && !handled) {
          handled = true
          stop()
          onDecode(result.getText())
        }
      })
    } catch (err) {
      isScanning.value = false
      errorMessage.value = cameraErrorMessage(err)
    }
  }

  /**
   * Batch mode: the camera stays on and every new code is reported. The same code
   * seen again within `cooldownMs` is ignored — zxing fires on every frame while a
   * barcode stays in view.
   */
  async function startContinuous(
    video: HTMLVideoElement,
    onDecode: (isbn: string) => void,
    cooldownMs = 3000,
  ): Promise<void> {
    stop()
    errorMessage.value = null
    isScanning.value = true
    let lastCode = ''
    let lastSeenAt = 0

    try {
      const reader = new BrowserMultiFormatReader()
      controls = await reader.decodeFromVideoDevice(undefined, video, (result) => {
        if (!result) return
        const code = result.getText()
        const now = Date.now()
        if (code === lastCode && now - lastSeenAt < cooldownMs) {
          lastSeenAt = now
          return
        }
        lastCode = code
        lastSeenAt = now
        onDecode(code)
      })
    } catch (err) {
      isScanning.value = false
      errorMessage.value = cameraErrorMessage(err)
    }
  }

  function stop(): void {
    if (controls) {
      controls.stop()
      controls = null
    }
    isScanning.value = false
  }

  onScopeDispose(stop)

  return { isScanning, errorMessage, start, startContinuous, stop }
}
