<script setup lang="ts">
import { ref, computed } from 'vue'
import { useQuery } from '@tanstack/vue-query'
import { useI18n } from 'vue-i18n'
import { getActivityLogs } from '@/api/notification'
import { getCollection } from '@/api/collection'
import ActivityLogRow from '@/components/molecules/ActivityLogRow.vue'
import type { EventType, LogStatus } from '@/types'

const { t } = useI18n()

const page      = ref(1)
const limit     = 50
const eventType = ref<EventType | ''>('')
const status    = ref<LogStatus | ''>('')
const ceId      = ref<string>('')
const searched  = ref(false)

const { data: collection } = useQuery({ queryKey: ['collection'], queryFn: getCollection })

const { data, isPending, refetch } = useQuery({
  queryKey: computed(() => ['journal', page.value, eventType.value, status.value, ceId.value]),
  queryFn: () => getActivityLogs({
    page: page.value,
    limit,
    eventType: eventType.value || undefined,
    status:    status.value    || undefined,
    collectionEntryId: ceId.value || undefined,
  }),
  enabled: searched,
  refetchInterval: computed(() => searched.value ? 30_000 : false),
})

function search() {
  page.value = 1
  searched.value = true
  refetch()
}

const EVENT_TYPES: { value: EventType | ''; label: string }[] = [
  { value: '',               label: t('journal.allTypes') },
  { value: 'rss_fetch',     label: 'RSS' },
  { value: 'jikan_fetch',   label: 'Jikan' },
  { value: 'discord_sent',  label: 'Discord' },
  { value: 'scheduler_fire',label: 'Scheduler' },
  { value: 'worker_failure',label: 'Worker' },
  { value: 'user_action',   label: t('journal.userAction') },
]

const STATUSES: { value: LogStatus | ''; label: string }[] = [
  { value: '',        label: t('journal.allStatuses') },
  { value: 'running', label: t('journal.running') },
  { value: 'success', label: t('journal.success') },
  { value: 'error',   label: t('journal.error') },
]
</script>

<template>
  <div class="p-4 sm:p-6 space-y-4">
    <h1 class="text-2xl font-bold">{{ t('journal.title') }}</h1>

    <!-- Filters -->
    <div class="flex gap-3 flex-wrap items-center">
      <select v-model="eventType" class="select select-sm select-bordered">
        <option v-for="o in EVENT_TYPES" :key="o.value" :value="o.value">{{ o.label }}</option>
      </select>
      <select v-model="status" class="select select-sm select-bordered">
        <option v-for="o in STATUSES" :key="o.value" :value="o.value">{{ o.label }}</option>
      </select>
      <select v-model="ceId" class="select select-sm select-bordered">
        <option value="">{{ t('journal.allMangas') }}</option>
        <option v-for="e in collection" :key="e.id" :value="e.id">{{ e.manga.title }}</option>
      </select>
      <button class="btn btn-sm btn-primary" :class="{ loading: isPending }" @click="search">
        {{ t('journal.search') }}
      </button>
      <span v-if="searched" class="text-xs text-base-content/40 ml-auto">
        {{ data?.total ?? 0 }} {{ t('journal.entries') }}
      </span>
    </div>

    <!-- Initial state -->
    <div v-if="!searched" class="py-16 text-center text-base-content/40 text-sm">
      {{ t('journal.hint') }}
    </div>

    <!-- Loading skeleton -->
    <div v-else-if="isPending" class="space-y-1">
      <div v-for="i in 10" :key="i" class="h-10 rounded bg-base-200 animate-pulse" />
    </div>

    <!-- Table -->
    <div v-else class="overflow-x-auto rounded-xl border border-base-300">
      <table class="table table-xs w-full">
        <thead>
          <tr class="text-base-content/50">
            <th>{{ t('journal.date') }}</th>
            <th>{{ t('journal.type') }}</th>
            <th>{{ t('journal.manga') }}</th>
            <th>{{ t('journal.source') }}</th>
            <th>{{ t('journal.status') }}</th>
            <th class="text-right">{{ t('journal.articles') }}</th>
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

    <!-- Pagination -->
    <div v-if="(data?.totalPages ?? 0) > 1" class="flex justify-center gap-2">
      <button class="btn btn-sm btn-ghost" :disabled="page === 1" @click="page--">‹</button>
      <button
        v-for="p in data!.totalPages"
        :key="p"
        class="btn btn-sm"
        :class="p === page ? 'btn-primary' : 'btn-ghost'"
        @click="page = p"
      >{{ p }}</button>
      <button class="btn btn-sm btn-ghost" :disabled="page === data!.totalPages" @click="page++">›</button>
    </div>
  </div>
</template>
