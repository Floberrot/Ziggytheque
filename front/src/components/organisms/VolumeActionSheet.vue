<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { BookOpen, Check, Info, Megaphone, Package, Star } from 'lucide-vue-next'
import type { VolumeEntry, VolumeToggleField } from '@/types'
import BaseModal from '@/components/atoms/BaseModal.vue'

/**
 * The quick actions of a tome on a phone: a bottom sheet, opened by the "⋯" of the
 * tile or a long press. `volume` comes from the query cache, so an optimistic toggle
 * shows here at once.
 */
defineProps<{
  volume: VolumeEntry | null
  pending: boolean
}>()

const emit = defineEmits<{
  toggle: [field: VolumeToggleField]
  details: []
  close: []
}>()

const { t } = useI18n()
</script>

<template>
  <BaseModal
    :open="volume !== null"
    max-width-class="sm:max-w-sm"
    z-class="z-[90]"
    @close="emit('close')"
  >
    <template v-if="volume">
      <div class="px-5 pt-4 pb-2 text-[10px] font-bold uppercase tracking-widest text-base-content/40 border-b border-base-200">
        {{ t('manga.volumeSheetTitle', { number: volume.number }) }}
      </div>
      <div class="p-2">
        <!-- Announced (only when not owned) -->
        <button
          v-if="!volume.isOwned"
          class="flex items-center gap-3 w-full px-3 py-3 rounded-xl text-sm font-semibold text-left transition-colors hover:bg-base-200 disabled:opacity-50"
          :disabled="pending"
          :class="volume.isAnnounced ? 'text-secondary' : 'text-base-content/80'"
          :aria-pressed="volume.isAnnounced"
          @click="emit('toggle', 'isAnnounced')"
        >
          <Megaphone class="h-[18px] w-[18px]" :class="volume.isAnnounced ? 'text-secondary' : 'text-base-content/50'" />
          {{ t('enrich.statusAnnouncedLabel') }}
          <Check v-if="volume.isAnnounced" class="h-4 w-4 ml-auto text-secondary" />
        </button>
        <!-- Owned -->
        <button
          class="flex items-center gap-3 w-full px-3 py-3 rounded-xl text-sm font-semibold text-left transition-colors hover:bg-base-200 disabled:opacity-50"
          :disabled="pending"
          :class="volume.isOwned ? 'text-success' : 'text-base-content/80'"
          :aria-pressed="volume.isOwned"
          @click="emit('toggle', 'isOwned')"
        >
          <Package class="h-[18px] w-[18px]" :class="volume.isOwned ? 'text-success' : 'text-base-content/50'" />
          {{ t('enrich.statusOwnedLabel') }}
          <Check v-if="volume.isOwned" class="h-4 w-4 ml-auto text-success" />
        </button>
        <!-- Read (only when owned) -->
        <button
          v-if="volume.isOwned"
          class="flex items-center gap-3 w-full px-3 py-3 rounded-xl text-sm font-semibold text-left transition-colors hover:bg-base-200 disabled:opacity-50"
          :disabled="pending"
          :class="volume.isRead ? 'text-info' : 'text-base-content/80'"
          :aria-pressed="volume.isRead"
          @click="emit('toggle', 'isRead')"
        >
          <BookOpen class="h-[18px] w-[18px]" :class="volume.isRead ? 'text-info' : 'text-base-content/50'" />
          {{ t('enrich.statusReadLabel') }}
          <Check v-if="volume.isRead" class="h-4 w-4 ml-auto text-info" />
        </button>
        <!-- Wished (only when not owned) -->
        <button
          v-if="!volume.isOwned"
          class="flex items-center gap-3 w-full px-3 py-3 rounded-xl text-sm font-semibold text-left transition-colors hover:bg-base-200 disabled:opacity-50"
          :disabled="pending"
          :class="volume.isWished ? 'text-warning' : 'text-base-content/80'"
          :aria-pressed="volume.isWished"
          @click="emit('toggle', 'isWished')"
        >
          <Star
            class="h-[18px] w-[18px]"
            :class="volume.isWished ? 'text-warning' : 'text-base-content/50'"
            :fill="volume.isWished ? 'currentColor' : 'none'"
          />
          {{ t('enrich.statusWishedLabel') }}
          <Check v-if="volume.isWished" class="h-4 w-4 ml-auto text-warning" />
        </button>
        <div class="h-px bg-base-200 my-1 mx-2" />
        <button
          class="flex items-center gap-3 w-full px-3 py-3 rounded-xl text-sm font-semibold text-left transition-colors hover:bg-base-200"
          @click="emit('details')"
        >
          <Info class="h-[18px] w-[18px] text-base-content/50" />
          {{ t('manga.detailsAction') }}
        </button>
      </div>
    </template>
  </BaseModal>
</template>
