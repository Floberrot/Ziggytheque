<script setup lang="ts">
import { computed } from 'vue'
import { keepPreviousData, useQuery } from '@tanstack/vue-query'
import { useI18n } from 'vue-i18n'
import { RotateCcw, Search } from 'lucide-vue-next'
import { getActivityLogs } from '@/api/notification'
import { getCollection } from '@/api/collection'
import { getUsers } from '@/api/admin'
import { useActivityLogFilters } from '@/composables/useActivityLogFilters'
import { EVENT_TYPE_LABELS } from '@/utils/activityLog'
import ActivityLogRow from '@/components/molecules/ActivityLogRow.vue'
import ActivityLogCard from '@/components/molecules/ActivityLogCard.vue'
import BaseLoader from '@/components/atoms/BaseLoader.vue'
import type { EventType, LogStatus } from '@/types'

const { t } = useI18n()

const filters = useActivityLogFilters()
const { eventType, status, collectionEntryId, ownerId, from, to, searchInput, page } = filters

const { data: collection } = useQuery({
  queryKey: ['collection'],
  queryFn: () => getCollection(),
  staleTime: 60_000,
})

const { data: users } = useQuery({
  queryKey: ['admin', 'users', 'journal-filter'],
  queryFn: () => getUsers({ limit: 100 }),
  staleTime: 60_000,
})

const { data, isLoading, isFetching } = useQuery({
  queryKey: computed(() => ['journal', filters.params.value]),
  queryFn: () => getActivityLogs(filters.params.value),
  placeholderData: keepPreviousData,
  refetchInterval: 30_000,
})

const totalPages = computed(() =>
  Math.max(1, Math.ceil((data.value?.total ?? 0) / filters.limit)),
)

// ── Summary band (current page) ───────────────────────────────────────────

const pageErrorCount = computed(
  () => (data.value?.items ?? []).filter((log) => log.status === 'error').length,
)

const pageErrorRate = computed(() => {
  const count = data.value?.items.length ?? 0
  if (count === 0) return 0
  return Math.round((pageErrorCount.value / count) * 100)
})

const pageAverageDuration = computed(() => {
  const durations = (data.value?.items ?? [])
    .map((log) => log.durationMs)
    .filter((duration): duration is number => duration !== null)
  if (durations.length === 0) return null
  return Math.round(durations.reduce((sum, duration) => sum + duration, 0) / durations.length)
})

const EVENT_TYPES: { value: EventType | ''; label: string }[] = [
  { value: '', label: t('journal.allTypes') },
  ...(Object.entries(EVENT_TYPE_LABELS) as [EventType, string][]).map(([value, label]) => ({
    value,
    label,
  })),
]

const STATUSES: { value: LogStatus | ''; label: string }[] = [
  { value: '', label: t('journal.allStatuses') },
  { value: 'running', label: t('journal.running') },
  { value: 'success', label: t('journal.success') },
  { value: 'error', label: t('journal.error') },
]
</script>

<template>
  <div class="p-4 sm:p-6 space-y-4">
    <div class="flex items-center gap-3 flex-wrap">
      <h1 class="text-2xl font-bold">{{ t('journal.title') }}</h1>
      <!-- Live auto-refresh indicator -->
      <span
        class="flex items-center gap-1.5 text-[11px] uppercase tracking-wide text-base-content/40"
        :title="t('journal.liveHint')"
      >
        <span class="relative flex h-2 w-2">
          <span
            class="absolute inline-flex h-full w-full rounded-full bg-success opacity-60"
            :class="{ 'animate-ping': isFetching }"
          />
          <span class="relative inline-flex h-2 w-2 rounded-full bg-success" />
        </span>
        {{ t('journal.live') }}
      </span>
    </div>

    <!-- Filters -->
    <div class="rounded-xl border border-base-300 bg-base-100 p-3 space-y-3">
      <label class="input input-sm input-bordered flex items-center gap-2 w-full">
        <Search class="w-4 h-4 text-base-content/40 shrink-0" />
        <input
          v-model="searchInput"
          type="text"
          class="grow"
          :placeholder="t('journal.searchPlaceholder')"
        />
      </label>

      <div class="flex gap-2 flex-wrap items-center">
        <select v-model="eventType" class="select select-sm select-bordered">
          <option v-for="option in EVENT_TYPES" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>

        <select v-model="status" class="select select-sm select-bordered">
          <option v-for="option in STATUSES" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>

        <select v-model="ownerId" class="select select-sm select-bordered">
          <option value="">{{ t('journal.allUsers') }}</option>
          <option v-for="user in users?.items ?? []" :key="user.id" :value="user.id">
            {{ user.displayName }} — {{ user.email }}
          </option>
        </select>

        <select v-model="collectionEntryId" class="select select-sm select-bordered">
          <option value="">{{ t('journal.allMangas') }}</option>
          <option v-for="entry in collection?.items ?? []" :key="entry.id" :value="entry.id">
            {{ entry.manga.title }}
          </option>
        </select>

        <label class="flex items-center gap-1 text-xs text-base-content/60">
          {{ t('journal.from') }}
          <input v-model="from" type="datetime-local" class="input input-sm input-bordered" />
        </label>

        <label class="flex items-center gap-1 text-xs text-base-content/60">
          {{ t('journal.to') }}
          <input v-model="to" type="datetime-local" class="input input-sm input-bordered" />
        </label>

        <button
          v-if="filters.hasActiveFilters.value"
          class="btn btn-sm btn-ghost gap-1"
          @click="filters.reset()"
        >
          <RotateCcw class="w-3.5 h-3.5" />
          {{ t('journal.reset') }}
        </button>
      </div>
    </div>

    <!-- Summary band -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
      <div class="rounded-xl border border-base-300 bg-base-100 p-3">
        <div class="text-[11px] uppercase tracking-wide text-base-content/40">
          {{ t('journal.totalEntries') }}
        </div>
        <div class="text-xl font-bold tabular-nums">{{ data?.total ?? 0 }}</div>
      </div>
      <div class="rounded-xl border border-base-300 bg-base-100 p-3">
        <div class="text-[11px] uppercase tracking-wide text-base-content/40">
          {{ t('journal.pageErrors') }}
        </div>
        <div class="text-xl font-bold tabular-nums" :class="{ 'text-error': pageErrorCount > 0 }">
          {{ pageErrorCount }}
        </div>
      </div>
      <div class="rounded-xl border border-base-300 bg-base-100 p-3">
        <div class="text-[11px] uppercase tracking-wide text-base-content/40">
          {{ t('journal.pageErrorRate') }}
        </div>
        <div class="text-xl font-bold tabular-nums" :class="{ 'text-error': pageErrorRate > 0 }">
          {{ pageErrorRate }}%
        </div>
      </div>
      <div class="rounded-xl border border-base-300 bg-base-100 p-3">
        <div class="text-[11px] uppercase tracking-wide text-base-content/40">
          {{ t('journal.avgDuration') }}
        </div>
        <div class="text-xl font-bold tabular-nums">
          {{ pageAverageDuration !== null ? `${pageAverageDuration} ms` : '—' }}
        </div>
      </div>
    </div>

    <!-- Loading skeleton -->
    <div v-if="isLoading" class="space-y-1">
      <div v-for="index in 10" :key="index" class="h-10 rounded bg-base-200 animate-pulse" />
    </div>

    <template v-else>
      <!-- Desktop table -->
      <div class="hidden sm:block overflow-x-auto rounded-xl border border-base-300">
        <table class="table table-xs w-full">
          <thead>
            <tr class="text-base-content/50">
              <th class="w-6"></th>
              <th>{{ t('journal.date') }}</th>
              <th>{{ t('journal.type') }}</th>
              <th>{{ t('journal.user') }}</th>
              <th>{{ t('journal.source') }}</th>
              <th>{{ t('journal.status') }}</th>
              <th class="text-right">{{ t('journal.duration') }}</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="log in data?.items ?? []" :key="log.id">
              <ActivityLogRow :log="log" />
            </template>
          </tbody>
        </table>
        <div v-if="!data?.items?.length" class="py-12 text-center text-base-content/40 text-sm">
          {{ t('journal.empty') }}
        </div>
      </div>

      <!-- Mobile cards -->
      <div class="sm:hidden space-y-2">
        <ActivityLogCard v-for="log in data?.items ?? []" :key="log.id" :log="log" />
        <div v-if="!data?.items?.length" class="py-12 text-center text-base-content/40 text-sm">
          {{ t('journal.empty') }}
        </div>
      </div>
    </template>

    <!-- Pagination -->
    <div v-if="totalPages > 1" class="flex justify-center items-center gap-2">
      <button class="btn btn-sm btn-ghost" :disabled="page === 1 || isFetching" @click="page--">
        ‹
      </button>
      <span class="text-xs text-base-content/60 tabular-nums"> {{ page }} / {{ totalPages }} </span>
      <button
        class="btn btn-sm btn-ghost"
        :disabled="page >= totalPages || isFetching"
        @click="page++"
      >
        ›
      </button>
      <BaseLoader v-if="isFetching" size="xs" />
    </div>
  </div>
</template>
