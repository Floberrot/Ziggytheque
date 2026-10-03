import { onScopeDispose } from 'vue'
import { createScanSession, type ScanSessionResponse } from '@/api/manga'

interface ScanSessionCallbacks {
  onResult?: (isbn: string) => void
}

export function useScanSession() {
  let source: EventSource | null = null
  // Left (tab switched, page closed) while the session was being created: never listen.
  let disposed = false

  function start(payload: ScanSessionResponse, callbacks: ScanSessionCallbacks): void {
    close()

    const url = new URL(payload.mercureUrl)
    url.searchParams.append('topic', payload.topic)
    url.searchParams.append('authorization', payload.subscriberToken)

    source = new EventSource(url.toString(), { withCredentials: false })

    source.onmessage = (msg) => {
      const event = JSON.parse(msg.data as string) as { isbn?: string }
      if (event.isbn) {
        callbacks.onResult?.(event.isbn)
      }
    }

    // Do NOT close on error: the user may take a while to fetch their phone and
    // scan, so the connection can sit idle and blip. Let EventSource auto-reconnect
    // (recoverable errors) rather than tearing down and missing the result.
    source.onerror = () => {}
  }

  /**
   * Opens a phone hand-off and listens to it: for one tome, or — without a target —
   * for the add page's shelf scan. It only creates a short-lived channel, nothing of
   * the collection changes. Resolves with the session, whose token the QR code carries.
   */
  async function open(
    target: { mangaId: string; volumeId: string } | null,
    callbacks: ScanSessionCallbacks,
  ): Promise<ScanSessionResponse> {
    const session = target ? await createScanSession(target) : await createScanSession()
    if (!disposed) start(session, callbacks)
    return session
  }

  function close(): void {
    source?.close()
    source = null
  }

  onScopeDispose(() => {
    disposed = true
    close()
  })

  return { start, open, close }
}
