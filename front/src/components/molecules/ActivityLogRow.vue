<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { ChevronRight } from 'lucide-vue-next'
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
  <tr
    class="hover:bg-base-200/50 cursor-pointer transition-colors"
    :class="{ 'bg-error/5': log.status === 'error' }"
    @click="expanded = !expanded"
  >
    <td class="w-6 pr-0">
      <ChevronRight
        class="w-3 h-3 text-base-content/40 transition-transform"
        :class="{ 'rotate-90': expanded }"
      />
    </td>
    <td class="text-[11px] text-base-content/50 whitespace-nowrap">
      {{ formatDate(log.startedAt) }}
    </td>
    <td>
      <span class="badge badge-xs" :class="EVENT_TYPE_BADGES[log.eventType]">
        {{ EVENT_TYPE_LABELS[log.eventType] ?? log.eventType }}
      </span>
    </td>
    <td class="text-xs truncate max-w-40" :title="log.owner?.email">
      {{ log.owner?.displayName ?? log.owner?.email ?? t('journal.system') }}
    </td>
    <td class="text-xs text-base-content/60 truncate max-w-48">
      {{ log.sourceName }}<template v-if="log.mangaTitle"> · {{ log.mangaTitle }}</template>
    </td>
    <td>
      <span class="badge badge-xs" :class="STATUS_BADGES[log.status]">
        {{ t(`journal.${log.status}`) }}
      </span>
    </td>
    <td class="text-xs text-right tabular-nums text-base-content/40 whitespace-nowrap">
      {{ formatDuration(log.durationMs) }}
    </td>
  </tr>
  <!-- Expanded detail row -->
  <tr v-if="expanded">
    <td colspan="7" class="bg-base-200/60 px-4 py-3">
      <ActivityLogDetail :log="log" />
    </td>
  </tr>
</template>
