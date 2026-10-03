<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { ManualSeriesDraft } from '@/types'
import BaseButton from '@/components/atoms/BaseButton.vue'
import BaseEditionSelector from '@/components/atoms/BaseEditionSelector.vue'

/** A series no catalogue knows, typed by hand. */
defineProps<{ submitting: boolean }>()

/** The page owns the draft, kept when switching tabs. */
const draft = defineModel<ManualSeriesDraft>({ required: true })

const emit = defineEmits<{ submit: [] }>()

const { t } = useI18n()

function update<Field extends keyof ManualSeriesDraft>(field: Field, value: ManualSeriesDraft[Field]): void {
  draft.value = { ...draft.value, [field]: value }
}

function inputValue(event: Event): string {
  return (event.target as HTMLInputElement).value
}

/** Like v-model on a number input: a number when it parses, the raw text otherwise. */
function numberValue(event: Event): string | number {
  const raw = inputValue(event)
  const parsed = Number.parseFloat(raw)
  return Number.isNaN(parsed) ? raw : parsed
}
</script>

<template>
  <section class="space-y-4">
    <p class="text-sm text-base-content/60">{{ t('add.manualIntro') }}</p>
    <form class="space-y-3" @submit.prevent="emit('submit')">
      <label class="flex flex-col gap-1">
        <span class="text-xs font-semibold text-base-content/60">{{ t('manga.title') }} *</span>
        <input
          :value="draft.title"
          type="text"
          class="input input-bordered w-full"
          maxlength="255"
          required
          @input="update('title', inputValue($event))"
        />
      </label>
      <div class="grid gap-3 sm:grid-cols-2">
        <div class="flex flex-col gap-1">
          <span class="text-xs font-semibold text-base-content/60">{{ t('catalogue.publisher') }}</span>
          <BaseEditionSelector
            :model-value="draft.publisher || null"
            input-class="input input-bordered w-full"
            @update:model-value="update('publisher', $event ?? '')"
          />
        </div>
        <label class="flex flex-col gap-1">
          <span class="text-xs font-semibold text-base-content/60">{{ t('catalogue.specialEdition') }}</span>
          <input
            :value="draft.specialEdition"
            type="text"
            class="input input-bordered w-full"
            maxlength="150"
            :placeholder="t('catalogue.standardEdition')"
            @input="update('specialEdition', inputValue($event))"
          />
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-xs font-semibold text-base-content/60">{{ t('manga.author') }}</span>
          <input
            :value="draft.author"
            type="text"
            class="input input-bordered w-full"
            @input="update('author', inputValue($event))"
          />
        </label>
        <label class="flex flex-col gap-1">
          <span class="text-xs font-semibold text-base-content/60">{{ t('manga.totalVolumes') }}</span>
          <input
            :value="draft.totalVolumes"
            type="number"
            min="0"
            max="500"
            class="input input-bordered w-full"
            @input="update('totalVolumes', numberValue($event))"
          />
        </label>
      </div>
      <label class="flex flex-col gap-1">
        <span class="text-xs font-semibold text-base-content/60">{{ t('manga.coverUrl') }}</span>
        <input
          :value="draft.coverUrl"
          type="url"
          class="input input-bordered w-full"
          placeholder="https://…"
          @input="update('coverUrl', inputValue($event))"
        />
      </label>
      <BaseButton type="submit" class="btn btn-primary w-full" :loading="submitting" :disabled="!draft.title.trim()">
        {{ t('add.createManually') }}
      </BaseButton>
    </form>
  </section>
</template>
