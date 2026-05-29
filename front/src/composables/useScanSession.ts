import { ref, onScopeDispose } from 'vue'
import { startScanSession } from '@/api/manga'

export function useScanSession() {
  const sessionId = ref<string | null>(null)
  const pairingUrl = ref<string | null>(null)
  let eventSource: EventSource | null = null

  async function start(callbacks: { onIsbn: (isbn: string) => void }): Promise<void> {
    close()

    const sessionData = await startScanSession()
    sessionId.value = sessionData.sessionId
    pairingUrl.value = `${window.location.origin}/scan/${sessionData.sessionId}`

    const url = new URL(sessionData.mercureUrl)
    url.searchParams.append('topic', sessionData.topic)
    url.searchParams.append('authorization', sessionData.subscriberToken)

    eventSource = new EventSource(url.toString(), { withCredentials: false })

    eventSource.onmessage = (message) => {
      const event = JSON.parse(message.data as string) as {
        type: string
        isbn?: string
      }

      if (event.type === 'isbn_scanned' && event.isbn) {
        callbacks.onIsbn(event.isbn)
      }
    }

    eventSource.onerror = () => close()
  }

  function close(): void {
    eventSource?.close()
    eventSource = null
  }

  onScopeDispose(close)

  return { sessionId, pairingUrl, start, close }
}
