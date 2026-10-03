<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

/** Owned / read (/ wished, when there are some) meters of a series, out of its tomes. */
const props = defineProps<{
  ownedCount: number
  readCount: number
  wishedCount: number
  totalVolumes: number
}>()

const { t } = useI18n()

// Static class literals per meter so Tailwind keeps them.
const meters = computed(() => {
  const list = [
    { key: 'owned', count: props.ownedCount, labelKey: 'manga.meterOwned', numberClass: 'text-success', barClass: 'bg-success/80' },
    { key: 'read', count: props.readCount, labelKey: 'manga.meterRead', numberClass: 'text-info', barClass: 'bg-info/80' },
  ]
  if (props.wishedCount > 0) {
    list.push({ key: 'wished', count: props.wishedCount, labelKey: 'manga.meterWished', numberClass: 'text-warning', barClass: 'bg-warning/80' })
  }
  return list
})

function widthOf(count: number): string {
  return (props.totalVolumes ? Math.round((count / props.totalVolumes) * 100) : 0) + '%'
}
</script>

<template>
  <div class="flex flex-wrap gap-x-8 gap-y-4">
    <div v-for="meter in meters" :key="meter.key" class="flex-1 min-w-[150px]">
      <div class="flex items-baseline gap-1.5 mb-2 whitespace-nowrap">
        <b class="font-extrabold text-lg leading-none" :class="meter.numberClass">{{ meter.count }}</b>
        <span class="text-sm text-base-content/70 font-semibold">{{ t(meter.labelKey, meter.count) }}</span>
        <span class="text-xs text-base-content/40 font-bold">/ {{ totalVolumes }}</span>
      </div>
      <div class="h-1.5 rounded-full bg-base-content/10 overflow-hidden">
        <div
          class="h-full rounded-full transition-[width] duration-500"
          :class="meter.barClass"
          :style="{ width: widthOf(meter.count) }"
        />
      </div>
    </div>
  </div>
</template>
