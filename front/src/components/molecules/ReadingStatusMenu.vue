<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Check, ChevronDown, HelpCircle } from 'lucide-vue-next'
import type { ReadingStatus } from '@/types'
import { READING_STATUSES, READING_STATUS_DOTS, readingStatusLabelKey } from '@/utils/readingStatus'
import BaseLoader from '@/components/atoms/BaseLoader.vue'
import BaseModal from '@/components/atoms/BaseModal.vue'
import { useIsMobile } from '@/composables/useMediaQuery'

/**
 * The reading status of a series: a pill that opens the five statuses — a menu under
 * it on desktop, a bottom sheet on a phone. The page owns `open` (a click elsewhere
 * closes it, opening the other menu of the bar too).
 */
const props = defineProps<{
  status: ReadingStatus
  open: boolean
  pending: boolean
}>()

const emit = defineEmits<{
  toggle: []
  close: []
  pick: [status: ReadingStatus]
  openGuide: []
}>()

const { t } = useI18n()
const isMobile = useIsMobile()

const currentDot = computed(() => READING_STATUS_DOTS[props.status] ?? READING_STATUS_DOTS.not_started)
</script>

<template>
  <div class="relative" @click.stop>
    <button
      class="inline-flex items-center gap-2 h-9 pl-3 pr-2.5 rounded-full border bg-base-100/60 text-sm font-bold transition-colors"
      :class="open ? 'border-primary' : 'border-base-content/15 hover:border-base-content/30'"
      :disabled="pending"
      aria-haspopup="menu"
      :aria-expanded="open"
      @click="emit('toggle')"
    >
      <span class="w-2.5 h-2.5 rounded-full shrink-0" :class="currentDot" />
      <span>{{ t(readingStatusLabelKey(status)) }}</span>
      <BaseLoader v-if="pending" size="xs" />
      <ChevronDown v-else class="h-3.5 w-3.5 text-base-content/40 transition-transform" :class="open ? 'rotate-180' : ''" />
    </button>
    <Transition name="menu-pop">
      <div
        v-if="open && !isMobile"
        class="absolute left-0 top-[calc(100%+6px)] z-30 min-w-[230px] rounded-2xl border border-base-300 bg-base-100 shadow-2xl p-1.5"
        role="menu"
      >
        <div class="px-2.5 py-2 text-[10px] font-bold uppercase tracking-widest text-base-content/40 flex items-center justify-between">
          {{ t('manga.readingStatusTitle') }}
          <button class="text-base-content/35 hover:text-primary" :aria-label="t('guide.openTooltip')" @click="emit('openGuide')">
            <HelpCircle class="h-3.5 w-3.5" />
          </button>
        </div>
        <button
          v-for="option in READING_STATUSES"
          :key="option"
          class="flex items-center gap-2.5 w-full px-2.5 py-2 rounded-xl text-sm font-semibold text-left transition-colors hover:bg-base-200"
          :class="status === option ? 'text-base-content' : 'text-base-content/70'"
          role="menuitemradio"
          :aria-checked="status === option"
          @click="emit('pick', option)"
        >
          <span class="w-2.5 h-2.5 rounded-full shrink-0" :class="READING_STATUS_DOTS[option]" />
          {{ t(readingStatusLabelKey(option)) }}
          <Check v-if="status === option" class="h-4 w-4 ml-auto text-primary" />
        </button>
      </div>
    </Transition>

    <!-- Mobile: reading status as a bottom sheet -->
    <BaseModal
      :open="open && isMobile"
      max-width-class="sm:max-w-sm"
      z-class="z-[80]"
      @close="emit('close')"
    >
      <div class="p-3 pt-4">
        <div class="px-2 pb-2 text-[10px] font-bold uppercase tracking-widest text-base-content/40 flex items-center justify-between">
          {{ t('manga.readingStatusTitle') }}
          <button class="text-base-content/35 hover:text-primary" :aria-label="t('guide.openTooltip')" @click="emit('openGuide')">
            <HelpCircle class="h-4 w-4" />
          </button>
        </div>
        <button
          v-for="option in READING_STATUSES"
          :key="option"
          class="flex items-center gap-3 w-full px-3 py-3 rounded-xl text-sm font-semibold text-left transition-colors hover:bg-base-200"
          :class="status === option ? 'text-base-content' : 'text-base-content/70'"
          role="menuitemradio"
          :aria-checked="status === option"
          @click="emit('pick', option)"
        >
          <span class="w-2.5 h-2.5 rounded-full shrink-0" :class="READING_STATUS_DOTS[option]" />
          {{ t(readingStatusLabelKey(option)) }}
          <Check v-if="status === option" class="h-4 w-4 ml-auto text-primary" />
        </button>
      </div>
    </BaseModal>
  </div>
</template>

<style scoped>
.menu-pop-enter-active,
.menu-pop-leave-active {
  transition: opacity 0.14s ease, transform 0.16s cubic-bezier(0.22, 0.61, 0.36, 1);
  transform-origin: top;
}
.menu-pop-enter-from,
.menu-pop-leave-to {
  opacity: 0;
  transform: translateY(-6px) scale(0.97);
}
</style>
