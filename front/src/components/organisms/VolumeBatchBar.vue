<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { Bell, BellOff, BookOpen, Package, Star } from 'lucide-vue-next'
import type { VolumeEntry } from '@/types'
import { VOLUME_BATCH_RULES, type VolumeBatchAction } from '@/utils/volumeBatch'

/**
 * The bar of the batch selection, pinned to the bottom of the screen: only the
 * actions that would change at least one selected tome are offered.
 */
defineProps<{
  open: boolean
  selectedVolumes: VolumeEntry[]
  processing: boolean
}>()

const emit = defineEmits<{
  apply: [action: VolumeBatchAction]
  clear: []
}>()

const { t } = useI18n()
</script>

<template>
  <Teleport to="body">
    <Transition name="slide-up">
      <div
        v-if="open"
        class="fixed bottom-0 left-0 right-0 z-50 bg-base-100/95 backdrop-blur-sm border-t-2 border-primary/40 shadow-2xl safe-bottom"
        role="toolbar"
        :aria-label="t('volumeBatch.toolbar')"
      >
        <div class="max-w-5xl mx-auto px-4 py-3 flex items-center gap-3 flex-wrap">
          <span class="badge badge-primary badge-lg shrink-0">
            {{ t('volumeBatch.count', { count: selectedVolumes.length }, selectedVolumes.length) }}
          </span>
          <div class="flex flex-wrap gap-2 flex-1 min-w-0">
            <button
              v-if="selectedVolumes.some(VOLUME_BATCH_RULES.markRead.applies)"
              class="btn btn-info btn-sm gap-1.5"
              :disabled="processing"
              @click="emit('apply', 'markRead')"
            >
              <BookOpen class="h-4 w-4" />
              {{ t('volumeBatch.markRead') }}
            </button>
            <button
              v-if="selectedVolumes.some(VOLUME_BATCH_RULES.markUnread.applies)"
              class="btn btn-info btn-sm btn-outline gap-1.5"
              :disabled="processing"
              @click="emit('apply', 'markUnread')"
            >
              <BookOpen class="h-4 w-4" />
              {{ t('volumeBatch.markUnread') }}
            </button>
            <button
              v-if="selectedVolumes.some(VOLUME_BATCH_RULES.markOwned.applies)"
              class="btn btn-success btn-sm gap-1.5"
              :disabled="processing"
              @click="emit('apply', 'markOwned')"
            >
              <Package class="h-4 w-4" />
              {{ t('volumeBatch.markOwned') }}
            </button>
            <button
              v-if="selectedVolumes.some(VOLUME_BATCH_RULES.wish.applies)"
              class="btn btn-warning btn-sm btn-outline gap-1.5"
              :disabled="processing"
              @click="emit('apply', 'wish')"
            >
              <Star class="h-4 w-4" />
              {{ t('volume.wishlist') }}
            </button>
            <button
              v-if="selectedVolumes.some(VOLUME_BATCH_RULES.announce.applies)"
              class="btn btn-secondary btn-sm btn-outline gap-1.5"
              :disabled="processing"
              @click="emit('apply', 'announce')"
            >
              <Bell class="h-4 w-4" />
              {{ t('volumeBatch.announce') }}
            </button>
            <button
              v-if="selectedVolumes.some(VOLUME_BATCH_RULES.unannounce.applies)"
              class="btn btn-secondary btn-sm gap-1.5"
              :disabled="processing"
              @click="emit('apply', 'unannounce')"
            >
              <BellOff class="h-4 w-4" />
              {{ t('volumeBatch.unannounce') }}
            </button>
          </div>
          <button class="btn btn-ghost btn-sm shrink-0" @click="emit('clear')">
            {{ t('volumeGrid.clearSelection') }}
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.slide-up-enter-active,
.slide-up-leave-active {
  transition: transform 0.25s ease, opacity 0.2s ease;
}
.slide-up-enter-from,
.slide-up-leave-to {
  transform: translateY(100%);
  opacity: 0;
}
</style>
