<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Pencil } from 'lucide-vue-next'
import BaseCover from '@/components/atoms/BaseCover.vue'
import BaseModal from '@/components/atoms/BaseModal.vue'
import { useIsMobile } from '@/composables/useMediaQuery'

/**
 * The series cover, editable by URL: an anchored popover on desktop, a bottom sheet
 * on a phone (the cover is centred there, a popover would overflow the screen).
 * The page owns `editing`, so a click anywhere else on it closes the editor.
 */
const props = defineProps<{
  coverUrl: string | null
  title: string
  editing: boolean
}>()

const emit = defineEmits<{
  edit: []
  save: [coverUrl: string]
  cancel: []
}>()

const { t } = useI18n()
const isMobile = useIsMobile()

const draft = ref('')

// Each edit starts from the current cover.
watch(() => props.editing, (editing) => {
  if (editing) draft.value = props.coverUrl ?? ''
})
</script>

<template>
  <div class="shrink-0 group/cover relative flex justify-center sm:block">
    <div
      class="tooltip tooltip-right w-40 sm:w-28 md:w-36 aspect-[2/3] rounded-2xl overflow-hidden shadow-2xl ring-2 ring-base-content/10 cursor-pointer focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
      :data-tip="t('manga.editCoverTooltip')"
      role="button"
      tabindex="0"
      :aria-label="t('manga.editCoverTooltip')"
      @click.stop="emit('edit')"
      @keydown.enter.prevent.stop="emit('edit')"
      @keydown.space.prevent.stop="emit('edit')"
    >
      <BaseCover :src="coverUrl" :alt="title" class="w-full h-full" eager icon-class="h-10 w-10" />
      <!-- Edit overlay -->
      <div class="absolute inset-0 bg-black/50 opacity-0 group-hover/cover:opacity-100 transition-opacity flex items-center justify-center rounded-2xl pointer-events-none">
        <Pencil class="h-7 w-7 text-white" />
      </div>
    </div>
    <!-- Cover URL edit — anchored popover on desktop only -->
    <div
      v-if="editing && !isMobile"
      class="absolute top-full left-0 mt-2 z-30 bg-base-100 border border-base-300 rounded-xl shadow-2xl p-3 w-[min(16rem,calc(100vw-2rem))]"
      @click.stop
    >
      <p class="text-xs text-base-content/50 mb-1.5 font-medium">{{ t('manga.coverUrlTitle') }}</p>
      <input
        v-model="draft"
        type="url"
        inputmode="url"
        class="input input-bordered input-xs w-full font-mono text-[11px]"
        placeholder="https://..."
        :aria-label="t('manga.coverUrlTitle')"
        autofocus
        @keydown.enter="emit('save', draft)"
        @keydown.escape="emit('cancel')"
      />
      <div class="flex gap-1.5 mt-2">
        <button class="btn btn-primary btn-xs flex-1" @click="emit('save', draft)">{{ t('common.save') }}</button>
        <button class="btn btn-ghost btn-xs" @click="emit('cancel')">{{ t('common.cancel') }}</button>
      </div>
    </div>

    <!-- Cover URL edit — bottom sheet on mobile (the cover is centered,
         an anchored popover would overflow the viewport) -->
    <BaseModal
      :open="editing && isMobile"
      max-width-class="sm:max-w-sm"
      z-class="z-[80]"
      @close="emit('cancel')"
    >
      <div class="p-5" @click.stop>
        <p class="text-sm font-semibold mb-2">{{ t('manga.coverUrlTitle') }}</p>
        <input
          v-model="draft"
          type="url"
          inputmode="url"
          class="input input-bordered w-full font-mono text-xs"
          placeholder="https://..."
          :aria-label="t('manga.coverUrlTitle')"
          @keydown.enter="emit('save', draft)"
        />
        <div class="flex gap-2 mt-3">
          <button class="btn btn-primary flex-1" @click="emit('save', draft)">{{ t('common.save') }}</button>
          <button class="btn btn-ghost" @click="emit('cancel')">{{ t('common.cancel') }}</button>
        </div>
      </div>
    </BaseModal>
  </div>
</template>
