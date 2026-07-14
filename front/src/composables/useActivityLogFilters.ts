import { computed, getCurrentScope, onScopeDispose, ref, watch } from 'vue'
import type { ActivityLogParams } from '@/api/notification'
import type { EventType, LogStatus } from '@/types'

export interface UseActivityLogFiltersOptions {
  /** Delay applied to the free-text search before it hits the API. */
  debounceMs?: number
  /** Page size sent to the API. */
  limit?: number
}

/**
 * Reactive filter state for the activity journal.
 *
 * Exposes raw refs bound to the filter controls, a debounced `search` value,
 * and a computed `params` object ready to be passed to getActivityLogs().
 * Any filter change resets the page to 1.
 */
export function useActivityLogFilters(options: UseActivityLogFiltersOptions = {}) {
  const debounceMs = options.debounceMs ?? 350
  const limit = options.limit ?? 50

  const eventType = ref<EventType | ''>('')
  const status = ref<LogStatus | ''>('')
  const collectionEntryId = ref('')
  const ownerId = ref('')
  /** datetime-local input values (local timezone) */
  const from = ref('')
  const to = ref('')
  const searchInput = ref('')
  const search = ref('')
  const page = ref(1)

  let debounceTimer: ReturnType<typeof setTimeout> | null = null
  watch(searchInput, (value) => {
    if (debounceTimer) clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => {
      search.value = value.trim()
    }, debounceMs)
  })

  watch([eventType, status, collectionEntryId, ownerId, from, to, search], () => {
    page.value = 1
  })

  if (getCurrentScope()) {
    onScopeDispose(() => {
      if (debounceTimer) clearTimeout(debounceTimer)
    })
  }

  function toIso(localValue: string): string | undefined {
    if (!localValue) return undefined
    const date = new Date(localValue)
    return Number.isNaN(date.getTime()) ? undefined : date.toISOString()
  }

  const params = computed<ActivityLogParams>(() => ({
    page: page.value,
    limit,
    eventType: eventType.value || undefined,
    status: status.value || undefined,
    collectionEntryId: collectionEntryId.value || undefined,
    ownerId: ownerId.value || undefined,
    from: toIso(from.value),
    to: toIso(to.value),
    search: search.value || undefined,
  }))

  function reset(): void {
    eventType.value = ''
    status.value = ''
    collectionEntryId.value = ''
    ownerId.value = ''
    from.value = ''
    to.value = ''
    searchInput.value = ''
    search.value = ''
    page.value = 1
    if (debounceTimer) clearTimeout(debounceTimer)
  }

  const hasActiveFilters = computed(
    () =>
      eventType.value !== '' ||
      status.value !== '' ||
      collectionEntryId.value !== '' ||
      ownerId.value !== '' ||
      from.value !== '' ||
      to.value !== '' ||
      search.value !== '',
  )

  return {
    eventType,
    status,
    collectionEntryId,
    ownerId,
    from,
    to,
    searchInput,
    search,
    page,
    limit,
    params,
    hasActiveFilters,
    reset,
  }
}
