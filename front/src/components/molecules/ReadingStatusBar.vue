<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{ breakdown: Record<string, number> }>()

const { t } = useI18n()

const ORDER = ['in_progress', 'completed', 'on_hold', 'not_started', 'dropped'] as const

const STATUS_STYLE: Record<string, { bar: string; dot: string }> = {
  in_progress: { bar: 'bg-primary', dot: 'bg-primary' },
  completed: { bar: 'bg-success', dot: 'bg-success' },
  on_hold: { bar: 'bg-warning', dot: 'bg-warning' },
  not_started: { bar: 'bg-base-content/25', dot: 'bg-base-content/25' },
  dropped: { bar: 'bg-error/70', dot: 'bg-error/70' },
}

const total = computed(() =>
  Object.values(props.breakdown).reduce((acc, count) => acc + count, 0),
)

const segments = computed(() =>
  ORDER.map((status) => ({
    status,
    count: props.breakdown[status] ?? 0,
    widthPct: total.value > 0 ? ((props.breakdown[status] ?? 0) / total.value) * 100 : 0,
    style: STATUS_STYLE[status],
  })).filter((segment) => segment.count > 0),
)
</script>

<template>
  <div v-if="total > 0" class="space-y-4">
    <!-- Segmented bar -->
    <div class="flex h-3 w-full overflow-hidden rounded-full bg-base-200">
      <div
        v-for="segment in segments"
        :key="segment.status"
        class="h-full transition-all duration-500 first:rounded-l-full last:rounded-r-full"
        :class="segment.style.bar"
        :style="{ width: `${segment.widthPct}%` }"
      />
    </div>
    <!-- Legend -->
    <ul class="grid grid-cols-2 gap-x-4 gap-y-2">
      <li v-for="segment in segments" :key="segment.status" class="flex items-center gap-2 text-sm">
        <span class="h-2.5 w-2.5 rounded-full shrink-0" :class="segment.style.dot" />
        <span class="truncate text-base-content/70 flex-1">{{ t(`status.${segment.status}`) }}</span>
        <span class="font-semibold tabular-nums">{{ segment.count }}</span>
      </li>
    </ul>
  </div>
  <p v-else class="text-sm text-base-content/40 italic py-6 text-center">{{ t('common.noData') }}</p>
</template>
