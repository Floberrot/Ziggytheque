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

export interface CoverBatchCallbacks {
  onUpdate?: (progress: CoverBatchProgress) => void
  onDone?: (progress: CoverBatchProgress) => void
  /** Called once when the stream dies for good or never completes, so the page can resync. */
  onError?: () => void
}

// Hard ceiling: if the stream goes silent without ever sending batch_completed
// (dead connection or a lost completion event), resolve the UI anyway so the
// progress toast never hangs on "Démarrage…" forever.
const WATCHDOG_MS = 300_000

const DEFAULT_HUB_PATH = '/.well-known/mercure'

// The backend often resolves every cover in a few hundred milliseconds, so all
// SSE events arrive in one burst and the toast would jump straight from
// "Démarrage…" to the final summary — unreadable. We buffer the snapshots and
// replay them at a human-readable cadence instead: each step is held for
// `stepMs`, derived from the batch size so the whole climb lasts roughly
// PACE_TARGET_MS whatever the volume count (small batch → slow steps, big batch
// → quick but still smooth). A genuinely slow stream already exceeds the
// min-gap, so it passes through untouched.
export const PACE_TARGET_MS = 2200
export const PACE_MIN_STEP_MS = 90
export const PACE_MAX_STEP_MS = 550

export function useCoverBatchProgress() {
  const progress = ref<CoverBatchProgress | null>(null)
  let source: EventSource | null = null
  let watchdog: ReturnType<typeof setTimeout> | null = null
  let settled = false

  // Paced-replay state, reset on every start().
  const queue: CoverBatchProgress[] = []
  let paceTimer: ReturnType<typeof setTimeout> | null = null
  let lastDeliveredAt: number | null = null
  let stepMs = PACE_MAX_STEP_MS
  let activeCallbacks: CoverBatchCallbacks = {}

  function start(payload: CoverBatchStartResponse, callbacks: CoverBatchCallbacks): void {
    close()
    settled = false
    activeCallbacks = callbacks
    lastDeliveredAt = null
    stepMs = PACE_MAX_STEP_MS

    // Connect same-origin so the SSE stream is reachable without CORS in dev
    // (Vite proxy) and in prod (nginx proxy). We borrow only the hub path from
    // the backend payload and always follow the current page origin for the host.
    const url = new URL(hubPathFrom(payload.mercureUrl), window.location.origin)
    url.searchParams.append('topic', payload.topic)
    url.searchParams.append('authorization', payload.subscriberToken)

    source = new EventSource(url.toString(), { withCredentials: false })

    source.onmessage = (message) => {
      const event = JSON.parse(message.data as string) as {
        type: string
        total: number
        resolved: number
        failed: number
        skipped: number
        processed: number
        volumeNumber?: number
      }

      // batch_started carries the volume count → size the cadence so the whole
      // replay lands near PACE_TARGET_MS regardless of how many tomes there are.
      if (event.type === 'batch_started') {
        stepMs = Math.min(
          PACE_MAX_STEP_MS,
          Math.max(PACE_MIN_STEP_MS, Math.round(PACE_TARGET_MS / Math.max(1, event.total))),
        )
      }

      queue.push({
        total: event.total,
        resolved: event.resolved,
        failed: event.failed,
        skipped: event.skipped,
        processed: event.processed,
        done: event.type === 'batch_completed',
        lastType: event.type,
        volumeNumber: event.volumeNumber,
      })
      scheduleDrain()
    }

    // EventSource transparently reconnects after a transient drop (readyState
    // stays CONNECTING). Only give up — and let the page resync — once the
    // browser has closed the connection for good.
    source.onerror = () => {
      if (source?.readyState === EventSource.CLOSED) {
        fail()
      }
    }

    watchdog = setTimeout(fail, WATCHDOG_MS)
  }

  // Release the next buffered snapshot once at least `stepMs` has elapsed since
  // the previous one, so a burst of events is spread out into a readable climb.
  function scheduleDrain(): void {
    if (paceTimer !== null || queue.length === 0) return
    const sinceLast = lastDeliveredAt === null ? Number.POSITIVE_INFINITY : Date.now() - lastDeliveredAt
    paceTimer = setTimeout(deliverNext, Math.max(0, stepMs - sinceLast))
  }

  function deliverNext(): void {
    paceTimer = null
    const snapshot = queue.shift()
    if (!snapshot) return

    lastDeliveredAt = Date.now()
    progress.value = snapshot

    if (snapshot.done) {
      settle()
      activeCallbacks.onDone?.(snapshot)
      return
    }

    activeCallbacks.onUpdate?.(snapshot)
    scheduleDrain()
  }

  function fail(): void {
    if (settled) return
    settle()
    activeCallbacks.onError?.()
  }

  function settle(): void {
    settled = true
    close()
  }

  function close(): void {
    if (watchdog !== null) {
      clearTimeout(watchdog)
      watchdog = null
    }
    if (paceTimer !== null) {
      clearTimeout(paceTimer)
      paceTimer = null
    }
    queue.length = 0
    source?.close()
    source = null
  }

  onScopeDispose(close)

  return { progress, start, close }
}

function hubPathFrom(rawUrl: string): string {
  try {
    return new URL(rawUrl).pathname
  } catch {
    return DEFAULT_HUB_PATH
  }
}
