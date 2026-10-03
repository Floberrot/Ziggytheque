<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Info, Plus } from 'lucide-vue-next'
import BaseButton from '@/components/atoms/BaseButton.vue'

/**
 * "Add tomes": the user types the number of the last tome to create; the missing
 * tomes are created untracked. The page owns the typed number (`target`).
 */
const props = defineProps<{
  totalVolumes: number
  pending: boolean
}>()

/** '' while the field is empty (v-model on a number input yields '' then). */
const target = defineModel<number | ''>('target', { required: true })

const emit = defineEmits<{
  submit: []
  cancel: []
}>()

const { t } = useI18n()

const minimum = computed(() => props.totalVolumes + 1)
const isTargetValid = computed(() => target.value !== '' && Number(target.value) >= minimum.value)

function submit(): void {
  if (isTargetValid.value) emit('submit')
}
</script>

<template>
  <div class="rounded-xl bg-primary/5 border border-primary/20 p-3.5 space-y-2.5" @click.stop>
    <div class="flex items-start gap-2 text-xs text-base-content/65 leading-relaxed">
      <Info class="h-3.5 w-3.5 mt-0.5 shrink-0 text-primary" />
      <i18n-t keypath="manga.syncIntro" tag="p" scope="global">
        <template #count>
          <strong class="text-base-content">{{ t('manga.tomeCount', { count: totalVolumes }, totalVolumes) }}</strong>
        </template>
        <template #last>
          <strong>{{ t('manga.syncIntroLast') }}</strong>
        </template>
      </i18n-t>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <label for="sync-target" class="text-sm font-medium text-base-content/80 shrink-0">
        {{ t('manga.syncUpTo') }}
      </label>
      <input
        id="sync-target"
        v-model="target"
        type="number"
        :min="minimum"
        max="9999"
        class="input input-sm input-bordered w-24 tabular-nums"
        :placeholder="t('manga.syncPlaceholder', { number: totalVolumes + 5 })"
        @keydown.enter="submit"
      />
      <div
        class="tooltip tooltip-top"
        :data-tip="isTargetValid
          ? t('manga.syncCreateRange', { from: minimum, to: target })
          : t('manga.syncMinimum', { number: totalVolumes })"
      >
        <BaseButton
          class="btn btn-primary btn-sm gap-1.5"
          :loading="pending"
          :disabled="!isTargetValid"
          @click="emit('submit')"
        >
          <template #icon><Plus class="h-3.5 w-3.5" /></template>
          {{ t('manga.syncCreate') }}
        </BaseButton>
      </div>
      <button class="btn btn-ghost btn-sm" @click="emit('cancel')">
        {{ t('common.cancel') }}
      </button>
    </div>
  </div>
</template>
