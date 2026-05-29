import { describe, it, expect, vi, beforeEach } from 'vitest'
import { effectScope } from 'vue'

// Mock the API before importing the composable
vi.mock('@/api/manga', () => ({
  startScanSession: vi.fn().mockResolvedValue({
    sessionId: 'test-session-123',
    mercureUrl: 'http://localhost:8000/.well-known/mercure',
    subscriberToken: 'stub-subscriber-token',
    topic: 'https://ziggytheque.app/scan-session/test-session-123',
  }),
}))

import { useScanSession } from '../useScanSession'

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

describe('useScanSession', () => {
  beforeEach(() => {
    instances.length = 0
    // Clear call history only — keeps the implementation set in the vi.mock factory
    vi.clearAllMocks()
  })

  it('calls onIsbn when isbn_scanned event is received', async () => {
    const scope = effectScope()
    const onIsbn = vi.fn()

    let scanner: ReturnType<typeof useScanSession> | undefined

    await scope.run(async () => {
      scanner = useScanSession()
      await scanner.start({ onIsbn })
    })

    expect(instances).toHaveLength(1)
    const source = instances[0]

    source.onmessage!(
      new MessageEvent('message', {
        data: JSON.stringify({ type: 'isbn_scanned', isbn: '9782344020812' }),
      }),
    )

    expect(onIsbn).toHaveBeenCalledWith('9782344020812')

    scope.stop()
  })

  it('sets sessionId and pairingUrl after start', async () => {
    const scope = effectScope()
    let scanner: ReturnType<typeof useScanSession> | undefined

    await scope.run(async () => {
      scanner = useScanSession()
      await scanner.start({ onIsbn: vi.fn() })
    })

    expect(scanner!.sessionId.value).toBe('test-session-123')
    expect(scanner!.pairingUrl.value).toContain('test-session-123')

    scope.stop()
  })

  it('closes EventSource when scope is disposed', async () => {
    const scope = effectScope()

    await scope.run(async () => {
      const scanner = useScanSession()
      await scanner.start({ onIsbn: vi.fn() })
    })

    const source = instances[0]
    expect(source.close).not.toHaveBeenCalled()
    scope.stop()
    expect(source.close).toHaveBeenCalledOnce()
  })

  it('EventSource URL contains topic and authorization', async () => {
    const scope = effectScope()

    await scope.run(async () => {
      const scanner = useScanSession()
      await scanner.start({ onIsbn: vi.fn() })
    })

    const capturedUrl = instances[0].url
    expect(capturedUrl).toContain('topic=')
    expect(capturedUrl).toContain('authorization=stub-subscriber-token')
    expect(capturedUrl).toContain('scan-session')

    scope.stop()
  })
})
