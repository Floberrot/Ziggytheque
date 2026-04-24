<script setup lang="ts">
import type { ActivityLog, EventType } from '@/types'
import { ref } from 'vue'

defineProps<{ log: ActivityLog }>()
const expanded = ref(false)

const eventTypeLabel: Record<EventType, string> = {
  rss_fetch:      'RSS',
  jikan_fetch:    'Jikan',
  discord_sent:   'Discord',
  scheduler_fire: 'Scheduler',
  http_error:     'HTTP Error',
  worker_failure: 'Worker',
  user_action:    'API',
}

const statusClass: Record<string, string> = {
  running: 'badge-warning',
  success: 'badge-success',
  error:   'badge-error',
}
</script>

<template>
  <tr
    class="hover:bg-base-200/50 cursor-pointer transition-colors"
    :class="{ 'bg-error/5': log.status === 'error' }"
    @click="expanded = !expanded"
  >
    <td class="text-[11px] text-base-content/50 whitespace-nowrap">
      {{ new Date(log.startedAt).toLocaleString('fr-FR') }}
    </td>
    <td>
      <span class="badge badge-xs badge-outline font-mono">
        {{ eventTypeLabel[log.eventType] ?? log.eventType }}
      </span>
    </td>
    <td class="text-xs truncate max-w-40">{{ log.mangaTitle ?? '—' }}</td>
    <td class="text-xs text-base-content/60 truncate max-w-32">{{ log.sourceName }}</td>
    <td>
      <span class="badge badge-xs" :class="statusClass[log.status]">{{ log.status }}</span>
    </td>
    <td class="text-xs text-right tabular-nums">
      <span v-if="log.newArticlesCount !== null" class="text-success font-semibold">
        +{{ log.newArticlesCount }}
      </span>
      <span v-else>—</span>
    </td>
    <td class="text-xs text-right tabular-nums text-base-content/40">
      {{ log.durationMs !== null ? `${log.durationMs}ms` : '—' }}
    </td>
  </tr>
  <!-- Expanded detail row -->
  <tr v-if="expanded">
    <td colspan="7" class="bg-base-200 px-4 py-3 text-xs font-mono">
      <div v-if="log.errorMessage" class="text-error mb-2">{{ log.errorMessage }}</div>
      <pre v-if="log.metadata" class="text-base-content/60 whitespace-pre-wrap text-[10px]">{{ JSON.stringify(log.metadata, null, 2) }}</pre>
    </td>
  </tr>
</template>
