<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { VolumeEntry } from '@/types'
import BaseLazyImage from '@/components/atoms/BaseLazyImage.vue'
import { coverUrl } from '@/utils/coverUrl'
import { volumeOpacityClass, volumeRingClass } from '@/utils/volumeStyles'

/** The "Prices" tab: pick a tome to see what it costs in the shops. */
defineProps<{
  /** Sorted by number. */
  volumes: VolumeEntry[]
  /** The batch selection of the tomes tab, still outlined here. */
  selectedIds: Set<string>
}>()

const emit = defineEmits<{ select: [volume: VolumeEntry] }>()

const { t } = useI18n()
</script>

<template>
  <div>
    <p class="text-xs text-base-content/50 mb-4">{{ t('prices.selectVolume') }}</p>
    <!-- 3 columns max on mobile so each tile stays a comfortable tap target -->
    <div class="grid grid-cols-3 sm:grid-cols-6 md:grid-cols-8 lg:grid-cols-10 xl:grid-cols-12 gap-3 sm:gap-2">
      <div
        v-for="volume in volumes"
        :key="volume.id"
        class="group relative cursor-pointer select-none rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
        role="button"
        tabindex="0"
        :aria-label="t('prices.volumePrices', { number: volume.number })"
        @click="emit('select', volume)"
        @keydown.enter.prevent="emit('select', volume)"
        @keydown.space.prevent="emit('select', volume)"
      >
        <div
          class="aspect-[2/3] rounded-lg overflow-hidden ring-2 transition-all duration-200 relative shadow-sm"
          :class="[volumeRingClass(volume, selectedIds.has(volume.id)), volumeOpacityClass(volume), 'group-hover:scale-105 group-hover:shadow-lg']"
        >
          <BaseLazyImage v-if="volume.coverUrl" :src="coverUrl(volume.coverUrl)!" :alt="t('catalogue.tome', { number: volume.number })">
            <template #fallback>
              <div class="w-full h-full flex items-center justify-center bg-base-200">
                <span class="font-bold text-sm" :class="volume.isOwned ? 'text-base-content/50' : 'text-base-content/15'">{{ volume.number }}</span>
              </div>
            </template>
          </BaseLazyImage>
          <div v-else class="w-full h-full flex items-center justify-center bg-base-200">
            <span class="font-bold text-sm" :class="volume.isOwned ? 'text-base-content/50' : 'text-base-content/15'">{{ volume.number }}</span>
          </div>
        </div>
        <div class="text-center text-[9px] mt-0.5 tabular-nums font-semibold" :class="volume.isOwned ? 'text-base-content/60' : 'text-base-content/20'">
          T{{ volume.number }}
        </div>
      </div>
    </div>
  </div>
</template>
