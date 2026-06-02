import { describe, it, expect, vi, beforeEach } from 'vitest'
import { effectScope } from 'vue'
import { useScanSession } from '../useScanSession'
import type { ScanSessionResponse } from '@/api/manga'

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

const mockPayload: ScanSessionResponse = {
  sessionId: 'test-session-id',
  scanToken: 'stub-token',
  mercureUrl: 'http://localhost:8000/.well-known/mercure',
  subscriberToken: 'subscriber-token',
  topic: 'https://ziggytheque.app/cover-batch/test-session-id',
}

describe('useScanSession', () => {
  beforeEach(() => {
    instances.length = 0
    vi.clearAllMocks()
  })

  it('calls onResult when isbn is received', () => {
    const scope = effectScope()
    const onResult = vi.fn()

    scope.run(() => {
      const { start } = useScanSession()
      start(mockPayload, { onResult })
    })

    expect(instances).toHaveLength(1)
    const source = instances[0]

    source.onmessage!(
      new MessageEvent('message', {
        data: JSON.stringify({ isbn: '9782811645632' }),
      }),
    )

    expect(onResult).toHaveBeenCalledOnce()
    expect(onResult).toHaveBeenCalledWith('9782811645632')

    scope.stop()
  })

  it('does not close EventSource on onerror (lets it auto-reconnect during the wait)', () => {
    const scope = effectScope()

    scope.run(() => {
      const { start } = useScanSession()
      start(mockPayload, {})
    })

    const source = instances[0]
    source.onerror!()
    expect(source.close).not.toHaveBeenCalled()

    scope.stop()
  })

  it('closes EventSource when scope is disposed', () => {
    const scope = effectScope()

    scope.run(() => {
      const { start } = useScanSession()
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
      const { start } = useScanSession()
      start(mockPayload, {})
    })

    const capturedUrl = instances[0].url
    expect(capturedUrl).toContain('topic=')
    expect(capturedUrl).toContain('authorization=subscriber-token')
    expect(capturedUrl).toContain(encodeURIComponent('https://ziggytheque.app/cover-batch/test-session-id'))

    scope.stop()
  })
})
