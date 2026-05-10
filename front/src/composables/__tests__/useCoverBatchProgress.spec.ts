import { describe, it, expect, vi, beforeEach } from 'vitest'
import { effectScope } from 'vue'
import { useCoverBatchProgress } from '../useCoverBatchProgress'
import type { CoverBatchStartResponse } from '@/api/manga'

interface MockEventSourceInstance {
  onmessage: ((event: MessageEvent) => void) | null
  onerror: (() => void) | null
  close: ReturnType<typeof vi.fn>
  url: string
}

const instances: MockEventSourceInstance[] = []

class MockEventSource {
  onmessage: ((event: MessageEvent) => void) | null = null
  onerror: (() => void) | null = null
  close = vi.fn()
  url: string

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

  it('calls onUpdate for each event and onDone on batch_completed', () => {
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
    expect(onUpdate).toHaveBeenCalledOnce()
    expect(onUpdate.mock.calls[0][0]).toMatchObject({ resolved: 1, done: false })

    source.onmessage!(new MessageEvent('message', { data: makeEventData('batch_completed') }))
    expect(onDone).toHaveBeenCalledOnce()
    expect(onDone.mock.calls[0][0]).toMatchObject({ done: true })
    expect(source.close).toHaveBeenCalledOnce()

    scope.stop()
  })

  it('closes EventSource on onerror', () => {
    const scope = effectScope()

    scope.run(() => {
      const { start } = useCoverBatchProgress()
      start(mockPayload, {})
    })

    const source = instances[0]
    source.onerror!()
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

  it('URL contains topic and authorization query params', () => {
    const scope = effectScope()

    scope.run(() => {
      const { start } = useCoverBatchProgress()
      start(mockPayload, {})
    })

    const capturedUrl = instances[0].url
    expect(capturedUrl).toContain('topic=')
    expect(capturedUrl).toContain('authorization=stub-token')
    expect(capturedUrl).toContain(encodeURIComponent('https://ziggytheque.app/cover-batch/test-batch-id'))

    scope.stop()
  })

  it('progress ref is updated with each event', () => {
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
    expect(progressRef!.value).toMatchObject({ processed: 0, done: false })

    source.onmessage!(new MessageEvent('message', { data: makeEventData('batch_completed') }))
    expect(progressRef!.value).toMatchObject({ done: true })

    scope.stop()
  })
})
