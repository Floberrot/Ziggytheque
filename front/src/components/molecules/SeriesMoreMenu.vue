<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { HelpCircle, MoreHorizontal, Sparkles, Star, Tag, Trash2 } from 'lucide-vue-next'
import BaseModal from '@/components/atoms/BaseModal.vue'
import { useIsMobile } from '@/composables/useMediaQuery'

/**
 * The secondary actions of a series, tucked behind a "⋯" button: a menu on desktop, a
 * bottom sheet on a phone. The page owns `open` and closes it when it runs an action.
 */
defineProps<{
  open: boolean
  /** Tomes neither owned nor wished: the "wish the missing ones" action shows when > 0. */
  missingCount: number
  autoFillDisabled: boolean
}>()

const emit = defineEmits<{
  toggle: []
  close: []
  wishMissing: []
  autoFill: []
  showPrice: []
  openGuide: []
  remove: []
}>()

const { t } = useI18n()
const isMobile = useIsMobile()
</script>

<template>
  <div class="relative" @click.stop>
    <div class="tooltip tooltip-top" :data-tip="t('manga.moreOptionsTitle')">
      <button
        class="btn btn-circle btn-sm w-9 h-9"
        :class="open ? 'btn-active border border-base-content/30' : 'btn-ghost border border-base-content/15'"
        aria-haspopup="menu"
        :aria-expanded="open"
        :aria-label="t('manga.moreOptionsTitle')"
        @click="emit('toggle')"
      >
        <MoreHorizontal class="h-5 w-5" />
      </button>
    </div>
    <Transition name="menu-pop">
      <div
        v-if="open && !isMobile"
        class="absolute right-0 top-[calc(100%+6px)] z-30 min-w-[250px] rounded-2xl border border-base-300 bg-base-100 shadow-2xl p-1.5"
        role="menu"
      >
        <button
          v-if="missingCount > 0"
          class="flex items-center gap-3 w-full px-2.5 py-2.5 rounded-xl text-sm font-semibold text-left transition-colors hover:bg-base-200"
          role="menuitem"
          @click="emit('wishMissing')"
        >
          <Star class="h-[18px] w-[18px] text-base-content/50" />
          {{ t('manga.wishMissing', { count: missingCount }, missingCount) }}
        </button>
        <button
          class="flex items-center gap-3 w-full px-2.5 py-2.5 rounded-xl text-sm font-semibold text-left transition-colors hover:bg-base-200 disabled:opacity-50"
          role="menuitem"
          :disabled="autoFillDisabled"
          @click="emit('autoFill')"
        >
          <Sparkles class="h-[18px] w-[18px] text-base-content/50" />
          {{ t('manga.autoFillCovers') }}
        </button>
        <button
          class="flex items-center gap-3 w-full px-2.5 py-2.5 rounded-xl text-sm font-semibold text-left transition-colors hover:bg-base-200"
          role="menuitem"
          @click="emit('showPrice')"
        >
          <Tag class="h-[18px] w-[18px] text-base-content/50" />
          {{ t('manga.setBatchPrice') }}
        </button>
        <div class="h-px bg-base-200 my-1 mx-2" />
        <button
          class="flex items-center gap-3 w-full px-2.5 py-2.5 rounded-xl text-sm font-semibold text-left transition-colors hover:bg-base-200"
          role="menuitem"
          @click="emit('openGuide')"
        >
          <HelpCircle class="h-[18px] w-[18px] text-base-content/50" />
          {{ t('guide.openLabel') }}
        </button>
        <button
          class="flex items-center gap-3 w-full px-2.5 py-2.5 rounded-xl text-sm font-semibold text-left transition-colors text-error hover:bg-error/10"
          role="menuitem"
          @click="emit('remove')"
        >
          <Trash2 class="h-[17px] w-[17px]" />
          {{ t('manga.removeSeries') }}
        </button>
      </div>
    </Transition>

    <!-- Mobile: secondary actions as a bottom sheet -->
    <BaseModal
      :open="open && isMobile"
      max-width-class="sm:max-w-sm"
      z-class="z-[80]"
      @close="emit('close')"
    >
      <div class="p-3 pt-4">
        <div class="px-2 pb-2 text-[10px] font-bold uppercase tracking-widest text-base-content/40">
          {{ t('manga.moreOptionsTitle') }}
        </div>
        <button
          v-if="missingCount > 0"
          class="flex items-center gap-3 w-full px-3 py-3 rounded-xl text-sm font-semibold text-left transition-colors hover:bg-base-200"
          role="menuitem"
          @click="emit('wishMissing')"
        >
          <Star class="h-[18px] w-[18px] text-base-content/50" />
          {{ t('manga.wishMissing', { count: missingCount }, missingCount) }}
        </button>
        <button
          class="flex items-center gap-3 w-full px-3 py-3 rounded-xl text-sm font-semibold text-left transition-colors hover:bg-base-200 disabled:opacity-50"
          role="menuitem"
          :disabled="autoFillDisabled"
          @click="emit('autoFill')"
        >
          <Sparkles class="h-[18px] w-[18px] text-base-content/50" />
          {{ t('manga.autoFillCovers') }}
        </button>
        <button
          class="flex items-center gap-3 w-full px-3 py-3 rounded-xl text-sm font-semibold text-left transition-colors hover:bg-base-200"
          role="menuitem"
          @click="emit('showPrice')"
        >
          <Tag class="h-[18px] w-[18px] text-base-content/50" />
          {{ t('manga.setBatchPrice') }}
        </button>
        <div class="h-px bg-base-200 my-1 mx-2" />
        <button
          class="flex items-center gap-3 w-full px-3 py-3 rounded-xl text-sm font-semibold text-left transition-colors hover:bg-base-200"
          role="menuitem"
          @click="emit('openGuide')"
        >
          <HelpCircle class="h-[18px] w-[18px] text-base-content/50" />
          {{ t('guide.openLabel') }}
        </button>
        <button
          class="flex items-center gap-3 w-full px-3 py-3 rounded-xl text-sm font-semibold text-left transition-colors text-error hover:bg-error/10"
          role="menuitem"
          @click="emit('remove')"
        >
          <Trash2 class="h-[17px] w-[17px]" />
          {{ t('manga.removeSeries') }}
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
