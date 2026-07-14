<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ChevronDown } from 'lucide-vue-next'
import type { ActivityLog } from '@/types'
import { EVENT_TYPE_BADGES, EVENT_TYPE_LABELS, STATUS_BADGES, formatDuration } from '@/utils/activityLog'
import ActivityLogDetail from '@/components/molecules/ActivityLogDetail.vue'

defineProps<{ log: ActivityLog }>()

const { t, locale } = useI18n()
const expanded = ref(false)

function formatDate(iso: string): string {
  return new Date(iso).toLocaleString(locale.value === 'fr' ? 'fr-FR' : 'en-GB')
}
</script>

<template>
  <div
    class="rounded-xl border border-base-300 bg-base-100 p-3 space-y-2"
    :class="{ 'border-error/40 bg-error/5': log.status === 'error' }"
    @click="expanded = !expanded"
  >
    <div class="flex items-center gap-2 flex-wrap">
      <span class="badge badge-xs" :class="EVENT_TYPE_BADGES[log.eventType]">
        {{ EVENT_TYPE_LABELS[log.eventType] ?? log.eventType }}
      </span>
      <span class="badge badge-xs" :class="STATUS_BADGES[log.status]">
        {{ t(`journal.${log.status}`) }}
      </span>
      <span class="ml-auto text-[11px] text-base-content/50 whitespace-nowrap">
        {{ formatDate(log.startedAt) }}
      </span>
      <ChevronDown
        class="w-3.5 h-3.5 text-base-content/40 transition-transform"
        :class="{ 'rotate-180': expanded }"
      />
    </div>

    <div class="text-xs flex items-center justify-between gap-2">
      <span class="truncate">
        {{ log.owner?.displayName ?? log.owner?.email ?? t('journal.system') }}
      </span>
      <span class="text-base-content/40 tabular-nums whitespace-nowrap">
        {{ formatDuration(log.durationMs) }}
      </span>
    </div>

    <div class="text-xs text-base-content/60 truncate">
      {{ log.sourceName }}<template v-if="log.mangaTitle"> · {{ log.mangaTitle }}</template>
    </div>

    <div v-if="expanded" class="pt-2 border-t border-base-300">
      <ActivityLogDetail :log="log" />
    </div>
  </div>
</template>
