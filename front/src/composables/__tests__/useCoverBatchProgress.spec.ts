import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { effectScope } from 'vue'
import { useCoverBatchProgress, PACE_MAX_STEP_MS } from '../useCoverBatchProgress'
import type { CoverBatchStartResponse } from '@/api/manga'

interface MockEventSourceInstance {
  onmessage: ((event: MessageEvent) => void) | null
  onerror: (() => void) | null
  close: ReturnType<typeof vi.fn>
  url: string
  readyState: number
}

const instances: MockEventSourceInstance[] = []

class MockEventSource {
  static readonly CONNECTING = 0
  static readonly OPEN = 1
  static readonly CLOSED = 2

  onmessage: ((event: MessageEvent) => void) | null = null
  onerror: (() => void) | null = null
  close = vi.fn()
  url: string
  readyState = MockEventSource.OPEN

  constructor(url: string) {
    this.url = url
    instances.push(this)
  }
}

vi.stubGlobal('EventSource', MockEventSource)

const mockPayload: CoverBatchStartResponse = {
  batchId: 'test-batch-id',
  mercureUrl: 'http://localhost:8000/.well-known/mercure',
  subscriberToken: 'stub-token',
  topic: 'https://ziggytheque.app/cover-batch/test-batch-id',
}

function makeEventData(type: string, overrides = {}) {
  return JSON.stringify({
    type,
    total: 5,
    resolved: 3,
    failed: 1,
    skipped: 1,
    processed: 5,
    ...overrides,
  })
}

describe('useCoverBatchProgress', () => {
  beforeEach(() => {
    instances.length = 0
    vi.clearAllMocks()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('replays each event through onUpdate and finishes on batch_completed', () => {
    vi.useFakeTimers()
    const scope = effectScope()
    const onUpdate = vi.fn()
    const onDone = vi.fn()

    scope.run(() => {
      const { start } = useCoverBatchProgress()
      start(mockPayload, { onUpdate, onDone })
    })

    expect(instances).toHaveLength(1)
    const source = instances[0]

    source.onmessage!(
      new MessageEvent('message', {
        data: makeEventData('volume_resolved', { processed: 1, resolved: 1, failed: 0, skipped: 0 }),
      }),
    )
    // Paced: the snapshot is buffered, not delivered until the cadence timer fires.
    expect(onUpdate).not.toHaveBeenCalled()
    vi.advanceTimersByTime(PACE_MAX_STEP_MS)
    expect(onUpdate).toHaveBeenCalledOnce()
    expect(onUpdate.mock.calls[0][0]).toMatchObject({ resolved: 1, done: false })

    source.onmessage!(new MessageEvent('message', { data: makeEventData('batch_completed') }))
    vi.advanceTimersByTime(PACE_MAX_STEP_MS)
    expect(onDone).toHaveBeenCalledOnce()
    expect(onDone.mock.calls[0][0]).toMatchObject({ done: true })
    expect(source.close).toHaveBeenCalledOnce()

    scope.stop()
  })

  it('connects same-origin and carries the topic + authorization params', () => {
    const scope = effectScope()

    scope.run(() => {
      const { start } = useCoverBatchProgress()
      start(mockPayload, {})
    })

    const capturedUrl = instances[0].url
    // Same-origin: the host follows the current page, not the backend payload.
    expect(capturedUrl.startsWith(window.location.origin)).toBe(true)
    expect(capturedUrl).toContain('/.well-known/mercure')
    expect(capturedUrl).toContain('topic=')
    expect(capturedUrl).toContain('authorization=stub-token')
    expect(capturedUrl).toContain(encodeURIComponent('https://ziggytheque.app/cover-batch/test-batch-id'))

    scope.stop()
  })

  it('keeps the connection on a transient error but resyncs on a permanent one', () => {
    const scope = effectScope()
    const onError = vi.fn()

    scope.run(() => {
      const { start } = useCoverBatchProgress()
      start(mockPayload, { onError })
    })

    const source = instances[0]

    // Transient drop: the browser will auto-reconnect, so we must NOT close.
    source.readyState = MockEventSource.CONNECTING
    source.onerror!()
    expect(source.close).not.toHaveBeenCalled()
    expect(onError).not.toHaveBeenCalled()

    // Permanent failure: connection is dead → close and let the page resync.
    source.readyState = MockEventSource.CLOSED
    source.onerror!()
    expect(source.close).toHaveBeenCalledOnce()
    expect(onError).toHaveBeenCalledOnce()

    scope.stop()
  })

  it('resyncs through onError when the stream never completes (watchdog)', () => {
    vi.useFakeTimers()
    const scope = effectScope()
    const onError = vi.fn()

    scope.run(() => {
      const { start } = useCoverBatchProgress()
      start(mockPayload, { onError })
    })

    const source = instances[0]
    vi.advanceTimersByTime(300_000)

    expect(onError).toHaveBeenCalledOnce()
    expect(source.close).toHaveBeenCalledOnce()

    scope.stop()
  })

  it('closes EventSource when scope is disposed', () => {
    const scope = effectScope()

    scope.run(() => {
      const { start } = useCoverBatchProgress()
      start(mockPayload, {})
    })

    const source = instances[0]
    expect(source.close).not.toHaveBeenCalled()
    scope.stop()
    expect(source.close).toHaveBeenCalledOnce()
  })

  it('progress ref is updated as events are replayed', () => {
    vi.useFakeTimers()
    const scope = effectScope()
    let progressRef: ReturnType<typeof useCoverBatchProgress>['progress'] | null = null

    scope.run(() => {
      const { progress, start } = useCoverBatchProgress()
      progressRef = progress
      start(mockPayload, {})
    })

    const source = instances[0]

    expect(progressRef!.value).toBeNull()

    source.onmessage!(new MessageEvent('message', { data: makeEventData('batch_started', { processed: 0, resolved: 0, failed: 0 }) }))
    // Buffered until the paced timer releases it.
    expect(progressRef!.value).toBeNull()
    vi.advanceTimersByTime(PACE_MAX_STEP_MS)
    expect(progressRef!.value).toMatchObject({ processed: 0, done: false })

    source.onmessage!(new MessageEvent('message', { data: makeEventData('batch_completed') }))
    vi.advanceTimersByTime(PACE_MAX_STEP_MS)
    expect(progressRef!.value).toMatchObject({ done: true })

    scope.stop()
  })
})
