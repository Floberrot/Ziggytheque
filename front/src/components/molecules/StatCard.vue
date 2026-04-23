<script setup lang="ts">
import { ref, watch } from 'vue'

type Color = 'primary' | 'secondary' | 'success' | 'warning' | 'info'
type Icon = 'book' | 'layers' | 'check' | 'heart'

const props = defineProps<{
  value: number
  label: string
  color: Color
  icon: Icon
  suffix?: string
}>()

const colorMap: Record<Color, { text: string; bg: string; accent: string }> = {
  primary:   { text: 'text-primary',   bg: 'bg-primary/10',   accent: 'bg-primary' },
  secondary: { text: 'text-secondary', bg: 'bg-secondary/10', accent: 'bg-secondary' },
  success:   { text: 'text-success',   bg: 'bg-success/10',   accent: 'bg-success' },
  warning:   { text: 'text-warning',   bg: 'bg-warning/10',   accent: 'bg-warning' },
  info:      { text: 'text-info',      bg: 'bg-info/10',      accent: 'bg-info' },
}

const displayValue = ref(0)

function animateTo(target: number) {
  const duration = 900
  const start = performance.now()
  const from = displayValue.value

  function step(now: number) {
    const progress = Math.min((now - start) / duration, 1)
    const eased = 1 - Math.pow(1 - progress, 3)
    displayValue.value = Math.round(from + (target - from) * eased)
    if (progress < 1) requestAnimationFrame(step)
  }

  requestAnimationFrame(step)
}

watch(() => props.value, (val) => animateTo(val), { immediate: true })
</script>

<template>
  <div class="card bg-base-100 shadow-sm overflow-hidden relative stat-card">
    <div :class="['absolute left-0 top-0 bottom-0 w-1', colorMap[color].accent]" />
    <div class="card-body p-4 pl-5 gap-3">
      <div class="flex justify-between items-start">
        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-widest">{{ label }}</p>
        <div :class="['p-2 rounded-lg shrink-0', colorMap[color].bg]">
          <svg v-if="icon === 'book'" :class="['w-5 h-5', colorMap[color].text]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>
          </svg>
          <svg v-else-if="icon === 'layers'" :class="['w-5 h-5', colorMap[color].text]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>
          </svg>
          <svg v-else-if="icon === 'check'" :class="['w-5 h-5', colorMap[color].text]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
          </svg>
          <svg v-else-if="icon === 'heart'" :class="['w-5 h-5', colorMap[color].text]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
          </svg>
        </div>
      </div>
      <p :class="['text-3xl font-bold tracking-tight', colorMap[color].text]">
        {{ displayValue }}<span v-if="suffix" class="text-xl ml-1 font-normal opacity-60">{{ suffix }}</span>
      </p>
    </div>
  </div>
</template>

<style scoped>
.stat-card {
  animation: fadeInUp 0.4s ease-out both;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(14px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
