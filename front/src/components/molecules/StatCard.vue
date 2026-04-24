<script setup lang="ts">
import { ref, watch } from 'vue'
import { Book, Layers, Check, Heart } from 'lucide-vue-next'

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
          <Book v-if="icon === 'book'" :class="['w-5 h-5', colorMap[color].text]" />
          <Layers v-else-if="icon === 'layers'" :class="['w-5 h-5', colorMap[color].text]" />
          <Check v-else-if="icon === 'check'" :class="['w-5 h-5', colorMap[color].text]" />
          <Heart v-else-if="icon === 'heart'" :class="['w-5 h-5', colorMap[color].text]" />
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
