import { onScopeDispose } from 'vue'
import type { ScanSessionResponse } from '@/api/manga'

export function useScanSession() {
  let source: EventSource | null = null

  function start(payload: ScanSessionResponse, callbacks: { onResult?: (isbn: string) => void }): void {
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

  function close(): void {
    source?.close()
    source = null
  }

  onScopeDispose(close)

  return { start, close }
}
