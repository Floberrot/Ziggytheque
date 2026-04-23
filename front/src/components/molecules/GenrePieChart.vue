<script setup lang="ts">
import { computed } from 'vue'
import { Doughnut } from 'vue-chartjs'
import { Chart as ChartJS, ArcElement, Tooltip, Legend, DoughnutController } from 'chart.js'

ChartJS.register(ArcElement, Tooltip, Legend, DoughnutController)

const props = defineProps<{ breakdown: Record<string, number> }>()

function genreColor(seed: string): string {
  let hash = 0
  for (let i = 0; i < seed.length; i++) {
    hash = seed.charCodeAt(i) + ((hash << 5) - hash)
  }
  const h = Math.abs(hash) % 360
  return `hsl(${h}, 65%, 55%)`
}

const total = computed(() => Object.values(props.breakdown).reduce((sum, v) => sum + v, 0))

const chartData = computed(() => {
  const labels = Object.keys(props.breakdown)
  return {
    labels,
    datasets: [
      {
        data: Object.values(props.breakdown),
        backgroundColor: labels.map(genreColor),
        borderWidth: 2,
        borderColor: 'transparent',
        hoverOffset: 8,
      },
    ],
  }
})

const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  cutout: '62%',
  plugins: {
    legend: {
      position: 'right' as const,
      labels: {
        padding: 16,
        usePointStyle: true,
        pointStyleWidth: 10,
        font: { size: 12 },
      },
    },
    tooltip: {
      callbacks: {
        label: (ctx: { raw: unknown }) => {
          const val = ctx.raw as number
          const pct = Math.round((val / total.value) * 100)
          return ` ${val} (${pct}%)`
        },
      },
    },
  },
}))
</script>

<template>
  <Doughnut :data="chartData" :options="chartOptions" />
</template>
