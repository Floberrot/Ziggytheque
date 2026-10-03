<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { IsbnCoverResult } from '@/composables/useIsbnCoverSearch'
import BaseCover from '@/components/atoms/BaseCover.vue'
import { coverSourceLabel } from '@/utils/coverSource'

/** The covers found for an ISBN (typed or scanned), one per source: a tap applies one. */
defineProps<{
  covers: IsbnCoverResult[]
  applying: boolean
}>()

const emit = defineEmits<{
  apply: [cover: IsbnCoverResult]
  /** The image turned out broken or blank: the modal stops offering it. */
  missing: [coverUrl: string]
}>()

const { t } = useI18n()
</script>

<template>
  <div>
    <p class="text-sm font-medium mb-2">{{ t('enrich.coverFound') }}</p>
    <TransitionGroup name="cover-pop" tag="div" class="grid grid-cols-2 sm:grid-cols-3 gap-4" appear>
      <button
        v-for="(cover, index) in covers"
        :key="cover.source + index"
        class="group flex flex-col gap-1.5 text-left"
        :style="{ transitionDelay: Math.min(index, 8) * 35 + 'ms' }"
        :disabled="applying"
        @click="emit('apply', cover)"
      >
        <div class="w-full aspect-[2/3] rounded-lg overflow-hidden bg-base-200 ring-2 ring-transparent transition-all duration-150 cursor-pointer group-hover:ring-primary group-hover:scale-[1.03] group-hover:shadow-lg active:scale-95">
          <BaseCover :src="cover.coverUrl" :alt="coverSourceLabel(cover.source)" class="w-full h-full" @missing="emit('missing', cover.coverUrl)" />
        </div>
        <span class="badge badge-sm badge-ghost w-full justify-center font-medium">{{ coverSourceLabel(cover.source) }}</span>
      </button>
    </TransitionGroup>
  </div>
</template>

<style scoped>
/* Covers appear with a soft, slightly staggered fade-in instead of popping in */
.cover-pop-enter-active {
  transition: opacity 0.28s ease, transform 0.28s cubic-bezier(0.22, 0.61, 0.36, 1);
}
.cover-pop-leave-active {
  transition: opacity 0.15s ease;
}
.cover-pop-enter-from {
  opacity: 0;
  transform: scale(0.94) translateY(8px);
}
.cover-pop-leave-to {
  opacity: 0;
}
</style>
