import { ref, onScopeDispose } from 'vue'
import type { CoverBatchStartResponse } from '@/api/manga'

export interface CoverBatchProgress {
  total: number
  resolved: number
  failed: number
  skipped: number
  processed: number
  done: boolean
  lastType: string
  volumeNumber?: number
}

export function useCoverBatchProgress() {
  const progress = ref<CoverBatchProgress | null>(null)
  let source: EventSource | null = null

  function start(
    payload: CoverBatchStartResponse,
    callbacks: {
      onUpdate?: (progress: CoverBatchProgress) => void
      onDone?: (progress: CoverBatchProgress) => void
    },
  ): void {
    close()

    const url = new URL(payload.mercureUrl)
    url.searchParams.append('topic', payload.topic)
    url.searchParams.append('authorization', payload.subscriberToken)

    source = new EventSource(url.toString(), { withCredentials: false })

    source.onmessage = (msg) => {
      const event = JSON.parse(msg.data as string) as {
        type: string
        total: number
        resolved: number
        failed: number
        skipped: number
        processed: number
        volumeNumber?: number
      }

      progress.value = {
        total: event.total,
        resolved: event.resolved,
        failed: event.failed,
        skipped: event.skipped,
        processed: event.processed,
        done: event.type === 'batch_completed',
        lastType: event.type,
        volumeNumber: event.volumeNumber,
      }

      callbacks.onUpdate?.(progress.value)

      if (event.type === 'batch_completed') {
        callbacks.onDone?.(progress.value)
        close()
      }
    }

    source.onerror = () => close()
  }

  function close(): void {
    source?.close()
    source = null
  }

  onScopeDispose(close)

  return { progress, start, close }
}
