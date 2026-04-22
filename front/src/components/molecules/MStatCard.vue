<script setup lang="ts">
import AIcon from '../atoms/AIcon.vue'

interface Props {
  title: string
  value: string | number
  icon?: string
  trend?: 'up' | 'down'
  trendValue?: number
}

defineProps<Props>()
</script>

<template>
  <div class="card bg-base-200/50 border border-base-300">
    <div class="card-body p-4">
      <div class="flex items-start justify-between">
        <div>
          <p class="text-sm text-base-content/60 mb-1">{{ title }}</p>
          <div class="flex items-baseline gap-2">
            <p class="text-3xl font-bold">{{ value }}</p>
            <div v-if="trend" :class="['flex items-center gap-1 text-xs', trend === 'up' ? 'text-success' : 'text-error']">
              <AIcon :name="trend === 'up' ? 'lucide:trending-up' : 'lucide:trending-down'" size="sm" />
              <span>{{ trendValue }}%</span>
            </div>
          </div>
        </div>
        <AIcon v-if="icon" :name="icon" size="lg" class="text-primary/40" />
      </div>
    </div>
  </div>
</template>
