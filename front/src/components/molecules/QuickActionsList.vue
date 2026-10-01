<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Bell, BellOff, BookOpen, Heart, Trash2 } from 'lucide-vue-next'
import type { CollectionEntry } from '@/types'
import BaseCover from '@/components/atoms/BaseCover.vue'
import EditionBadge from '@/components/molecules/EditionBadge.vue'

/** The quick actions on one series: open, follow its releases, rate, remove. */
const props = defineProps<{ entry: CollectionEntry; busy?: boolean }>()

const emit = defineEmits<{
  open: []
  toggleFollow: []
  rate: [rating: number]
  remove: []
}>()

const { t } = useI18n()

// Removing a series is not undoable: it takes a second, explicit tap.
const confirmingRemove = ref(false)
watch(() => props.entry.id, () => { confirmingRemove.value = false })

const HEARTS = [1, 2, 3, 4, 5] as const

function heartFill(heart: number): string {
  const rating = props.entry.rating ?? 0
  if (rating >= heart * 2) return 'fill-current'
  if (rating === heart * 2 - 1) return 'fill-current opacity-50'
  return ''
}
</script>

<template>
  <div class="flex flex-col gap-1">
    <div class="flex items-center gap-3 px-2 pb-2 mb-1 border-b border-base-200">
      <BaseCover :src="entry.manga.coverUrl" class="w-9 h-[3.25rem] rounded-md shrink-0" icon-class="h-4 w-4" />
      <div class="min-w-0">
        <p class="text-sm font-bold leading-tight line-clamp-2">{{ entry.manga.title }}</p>
        <EditionBadge :publisher="entry.manga.edition" :special-edition="entry.manga.specialEdition" size="xs" />
      </div>
    </div>

    <button type="button" class="quick-action" :disabled="busy" @click="emit('open')">
      <BookOpen class="h-4 w-4" />
      {{ t('quickActions.open') }}
    </button>

    <button type="button" class="quick-action" :disabled="busy" @click="emit('toggleFollow')">
      <BellOff v-if="entry.notificationsEnabled" class="h-4 w-4" />
      <Bell v-else class="h-4 w-4" />
      {{ entry.notificationsEnabled ? t('quickActions.unfollow') : t('quickActions.follow') }}
    </button>

    <div class="flex items-center justify-between gap-2 px-3 py-2">
      <span class="text-sm">{{ t('quickActions.rate') }}</span>
      <div class="flex items-center" role="radiogroup" :aria-label="t('quickActions.rate')">
        <button
          v-for="heart in HEARTS"
          :key="heart"
          type="button"
          role="radio"
          class="p-1 text-error transition-transform active:scale-90 disabled:opacity-50"
          :aria-checked="(entry.rating ?? 0) >= heart * 2"
          :aria-label="t('quickActions.rateValue', { count: heart }, heart)"
          :disabled="busy"
          @click="emit('rate', heart * 2)"
        >
          <Heart class="h-5 w-5" :class="heartFill(heart)" />
        </button>
      </div>
    </div>

    <div class="border-t border-base-200 mt-1 pt-1">
      <button
        v-if="!confirmingRemove"
        type="button"
        class="quick-action text-error"
        :disabled="busy"
        @click="confirmingRemove = true"
      >
        <Trash2 class="h-4 w-4" />
        {{ t('quickActions.remove') }}
      </button>
      <div v-else class="px-3 py-2 space-y-2">
        <p class="text-xs text-base-content/70">{{ t('quickActions.removeConfirm') }}</p>
        <div class="flex gap-2">
          <button type="button" class="btn btn-error btn-sm flex-1" :disabled="busy" @click="emit('remove')">
            {{ t('quickActions.removeYes') }}
          </button>
          <button type="button" class="btn btn-ghost btn-sm flex-1" @click="confirmingRemove = false">
            {{ t('common.cancel') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.quick-action {
  display: flex;
  width: 100%;
  align-items: center;
  gap: 0.75rem;
  border-radius: 0.75rem;
  padding: 0.6rem 0.75rem;
  text-align: left;
  font-size: 0.875rem;
  transition: background-color 150ms;
}

.quick-action:hover:not(:disabled) {
  background-color: color-mix(in oklab, var(--color-base-content) 7%, transparent);
}

.quick-action:disabled {
  opacity: 0.5;
}
</style>
