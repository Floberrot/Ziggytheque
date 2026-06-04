<script setup lang="ts">
import { computed } from 'vue'
import { Doughnut } from 'vue-chartjs'
import { Chart as ChartJS, ArcElement, Tooltip, DoughnutController } from 'chart.js'
import { useI18n } from 'vue-i18n'

ChartJS.register(ArcElement, Tooltip, DoughnutController)

const props = withDefaults(
  defineProps<{ breakdown: Record<string, number>; centerLabel?: string }>(),
  { centerLabel: undefined },
)

const { t, te } = useI18n()

/**
 * Curated, vibrant palette — assigned by descending slice size so the dominant
 * genre always gets the boldest colour. Cycles if there are more genres than
 * entries.
 */
const PALETTE = [
  '#54D964', // green
  '#C84BE0', // magenta
  '#8B5CF6', // violet
  '#22D3EE', // cyan
  '#F472B6', // pink
  '#FBBF24', // amber
  '#34D399', // emerald
  '#60A5FA', // blue
  '#FB7185', // rose
  '#A3E635', // lime
  '#E879F9', // fuchsia
  '#2DD4BF', // teal
]

function genreLabel(key: string): string {
  return te(`genre.${key}`) ? t(`genre.${key}`) : key
}

/** Slices sorted largest → smallest, each carrying its colour and percentage. */
const slices = computed(() => {
  const entries = Object.entries(props.breakdown).filter(([, count]) => count > 0)
  const sum = entries.reduce((acc, [, count]) => acc + count, 0) || 1
  return entries
    .sort((a, b) => b[1] - a[1])
    .map(([key, count], index) => ({
      key,
      label: genreLabel(key),
      count,
      percentage: Math.round((count / sum) * 100),
      color: PALETTE[index % PALETTE.length],
    }))
})

const total = computed(() => slices.value.reduce((acc, slice) => acc + slice.count, 0))

const chartData = computed(() => ({
  labels: slices.value.map((slice) => slice.label),
  datasets: [
    {
      data: slices.value.map((slice) => slice.count),
      backgroundColor: slices.value.map((slice) => slice.color),
      borderWidth: 0,
      borderRadius: 6,
      spacing: 2,
      hoverOffset: 10,
    },
  ],
}))

const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  cutout: '72%',
  plugins: {
    legend: { display: false },
    tooltip: {
      padding: 10,
      cornerRadius: 8,
      displayColors: false,
      callbacks: {
        label: (ctx: { raw: unknown; dataIndex: number }) => {
          const slice = slices.value[ctx.dataIndex]
          return ` ${slice.count} (${slice.percentage}%)`
        },
      },
    },
  },
}))
</script>

<template>
  <div class="flex flex-col sm:flex-row items-center gap-5 sm:gap-7">
    <!-- Donut with centered total -->
    <div class="relative h-44 w-44 shrink-0">
      <Doughnut :data="chartData" :options="chartOptions" />
      <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
        <span class="text-3xl font-bold leading-none tracking-tight">{{ total }}</span>
        <span class="mt-1 text-[10px] font-semibold uppercase tracking-widest text-base-content/40">
          {{ centerLabel ?? t('genre.chartCenter') }}
        </span>
      </div>
    </div>

    <!-- Custom legend -->
    <ul class="flex-1 w-full space-y-2 self-stretch">
      <li
        v-for="slice in slices"
        :key="slice.key"
        class="flex items-center gap-3 group"
      >
        <span class="h-3 w-3 rounded-full shrink-0 ring-2 ring-transparent" :style="{ backgroundColor: slice.color }" />
        <span class="text-sm font-medium truncate flex-1 capitalize">{{ slice.label }}</span>
        <span class="text-sm font-semibold tabular-nums">{{ slice.count }}</span>
        <span class="text-xs text-base-content/40 tabular-nums w-9 text-right">{{ slice.percentage }}%</span>
      </li>
    </ul>
  </div>
</template>
