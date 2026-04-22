<script setup lang="ts">
export interface Props {
  title: string
  value: string | number
  icon?: string
  trend?: number
  unit?: string
}

defineProps<Props>()
</script>

<template>
  <div class="card bg-base-200 p-4">
    <div class="flex items-start justify-between">
      <div>
        <p class="text-sm font-medium text-base-content/70">{{ title }}</p>
        <div class="mt-2 flex items-baseline gap-2">
          <span class="text-3xl font-bold">{{ value }}</span>
          <span v-if="unit" class="text-sm text-base-content/70">{{ unit }}</span>
        </div>
        <p v-if="trend !== undefined" class="mt-2 text-xs">
          <span :class="{ 'text-success': trend >= 0, 'text-error': trend < 0 }">
            {{ trend >= 0 ? '+' : '' }}{{ trend }}%
          </span>
        </p>
      </div>
      <AIcon v-if="icon" :name="icon" size="lg" class="opacity-50" />
    </div>
  </div>
</template>
