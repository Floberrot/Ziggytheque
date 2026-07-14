import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { nextTick } from 'vue'
import { useActivityLogFilters } from '../useActivityLogFilters'

describe('useActivityLogFilters', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('starts with empty filters and page 1', () => {
    const filters = useActivityLogFilters()

    expect(filters.page.value).toBe(1)
    expect(filters.hasActiveFilters.value).toBe(false)
    expect(filters.params.value).toEqual({
      page: 1,
      limit: 50,
      eventType: undefined,
      status: undefined,
      collectionEntryId: undefined,
      ownerId: undefined,
      from: undefined,
      to: undefined,
      search: undefined,
    })
  })

  it('exposes selected filters in params', async () => {
    const filters = useActivityLogFilters()

    filters.eventType.value = 'http_error'
    filters.status.value = 'error'
    filters.ownerId.value = 'user-1'
    filters.collectionEntryId.value = 'ce-1'
    await nextTick()

    expect(filters.params.value.eventType).toBe('http_error')
    expect(filters.params.value.status).toBe('error')
    expect(filters.params.value.ownerId).toBe('user-1')
    expect(filters.params.value.collectionEntryId).toBe('ce-1')
    expect(filters.hasActiveFilters.value).toBe(true)
  })

  it('debounces the search input', async () => {
    const filters = useActivityLogFilters({ debounceMs: 300 })

    filters.searchInput.value = '  share  '
    await nextTick()
    expect(filters.params.value.search).toBeUndefined()

    vi.advanceTimersByTime(299)
    expect(filters.search.value).toBe('')

    vi.advanceTimersByTime(1)
    await nextTick()
    expect(filters.search.value).toBe('share')
    expect(filters.params.value.search).toBe('share')
  })

  it('only applies the last search value when typing fast', async () => {
    const filters = useActivityLogFilters({ debounceMs: 300 })

    filters.searchInput.value = 'sha'
    await nextTick()
    vi.advanceTimersByTime(150)

    filters.searchInput.value = 'share'
    await nextTick()
    vi.advanceTimersByTime(300)

    expect(filters.search.value).toBe('share')
  })

  it('resets the page to 1 when a filter changes', async () => {
    const filters = useActivityLogFilters()

    filters.page.value = 4
    filters.eventType.value = 'auth_action'
    await nextTick()

    expect(filters.page.value).toBe(1)
  })

  it('converts datetime-local values to ISO strings', async () => {
    const filters = useActivityLogFilters()

    filters.from.value = '2026-07-01T10:00'
    filters.to.value = 'not-a-date'
    await nextTick()

    expect(filters.params.value.from).toBe(new Date('2026-07-01T10:00').toISOString())
    expect(filters.params.value.to).toBeUndefined()
  })

  it('reset() clears every filter and the search input', async () => {
    const filters = useActivityLogFilters()

    filters.eventType.value = 'rss_fetch'
    filters.searchInput.value = 'abc'
    filters.from.value = '2026-07-01T10:00'
    filters.page.value = 3
    await nextTick()
    vi.runAllTimers()
    await nextTick()

    filters.reset()
    await nextTick()

    expect(filters.eventType.value).toBe('')
    expect(filters.searchInput.value).toBe('')
    expect(filters.from.value).toBe('')
    expect(filters.page.value).toBe(1)
    expect(filters.hasActiveFilters.value).toBe(false)
  })
})
