<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { MonthlyAddition } from '@/types'

const props = defineProps<{ data: MonthlyAddition[] }>()

const { locale } = useI18n()

const max = computed(() => Math.max(1, ...props.data.map((point) => point.count)))
const totalAdded = computed(() => props.data.reduce((acc, point) => acc + point.count, 0))

const bars = computed(() =>
  props.data.map((point) => {
    const [year, month] = point.month.split('-').map(Number)
    const date = new Date(year, month - 1, 1)
    return {
      key: point.month,
      count: point.count,
      heightPct: Math.round((point.count / max.value) * 100),
      monthShort: date
        .toLocaleDateString(locale.value === 'fr' ? 'fr-FR' : 'en-US', { month: 'short' })
        .replace('.', ''),
      isFirstOfYear: month === 1,
      year,
    }
  }),
)
</script>

<template>
  <div class="space-y-3">
    <div class="flex items-end gap-1.5 h-32">
      <div
        v-for="bar in bars"
        :key="bar.key"
        class="flex-1 h-full flex flex-col justify-end items-center group relative"
      >
        <!-- tooltip -->
        <span
          class="absolute -top-1 opacity-0 group-hover:opacity-100 transition-opacity text-[11px] font-semibold bg-base-300 text-base-content px-1.5 py-0.5 rounded-md shadow pointer-events-none z-10"
        >
          {{ bar.count }}
        </span>
        <div
          class="w-full rounded-t-md transition-all duration-300 ease-out"
          :class="bar.count > 0 ? 'bg-gradient-to-t from-primary/60 to-primary group-hover:from-primary group-hover:to-primary' : 'bg-base-300/60'"
          :style="{ height: `${Math.max(bar.heightPct, bar.count > 0 ? 6 : 3)}%` }"
        />
      </div>
    </div>
    <div class="flex gap-1.5">
      <div v-for="bar in bars" :key="bar.key" class="flex-1 text-center">
        <span class="text-[9px] uppercase tracking-wide text-base-content/35">{{ bar.monthShort }}</span>
      </div>
    </div>
    <p class="text-xs text-base-content/40 pt-1">
      <span class="font-semibold text-base-content/60">{{ totalAdded }}</span>
      {{ ' ' }}
      <slot name="caption" />
    </p>
  </div>
</template>
