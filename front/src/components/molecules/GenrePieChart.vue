<script setup lang="ts">
import { computed } from 'vue'
import { Pie } from 'vue-chartjs'
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js'

ChartJS.register(ArcElement, Tooltip, Legend)

const props = defineProps<{ breakdown: Record<string, number> }>()

function genreColor(seed: string): string {
  let hash = 0
  for (let i = 0; i < seed.length; i++) {
    hash = seed.charCodeAt(i) + ((hash << 5) - hash)
  }
  const h = Math.abs(hash) % 360
  return `hsl(${h}, 65%, 55%)`
}

const chartData = computed(() => {
  const labels = Object.keys(props.breakdown)
  return {
    labels,
    datasets: [
      {
        data: Object.values(props.breakdown),
        backgroundColor: labels.map(genreColor),
        borderWidth: 2,
      },
    ],
  }
})

const chartOptions = {
  responsive: true,
  plugins: {
    legend: { position: 'right' as const },
  },
}
</script>

<template>
  <Pie :data="chartData" :options="chartOptions" />
</template>
