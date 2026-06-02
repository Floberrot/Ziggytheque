import { ref, onScopeDispose } from 'vue'
import { BrowserMultiFormatReader, type IScannerControls } from '@zxing/browser'

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
      if (err instanceof DOMException && err.name === 'NotAllowedError') {
        errorMessage.value = 'Accès caméra refusé. Vérifiez les permissions et que la page est en HTTPS.'
      } else {
        errorMessage.value = 'Impossible de démarrer le scanner caméra.'
      }
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

  return { isScanning, errorMessage, start, stop }
}
