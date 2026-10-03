<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import BaseCover from '@/components/atoms/BaseCover.vue'
import BaseButton from '@/components/atoms/BaseButton.vue'

/** Footer of the tome modal: paste the address of a cover and apply it as is. */
defineProps<{ applying: boolean }>()

/** The modal owns the typed address, kept when switching tabs. */
const url = defineModel<string>('url', { required: true })

const emit = defineEmits<{ apply: [coverUrl: string] }>()

const { t } = useI18n()

function apply(): void {
  const trimmed = url.value.trim()
  if (trimmed) emit('apply', trimmed)
}
</script>

<template>
  <div class="shrink-0 px-4 sm:px-5 pb-4 pt-3 border-t border-base-200">
    <p class="text-[11px] text-base-content/40 mb-1.5 font-semibold uppercase tracking-wide">{{ t('enrich.pasteUrl') }}</p>
    <div class="flex gap-2 items-center">
      <input
        v-model="url"
        type="url"
        class="input input-bordered input-xs flex-1 min-w-0"
        placeholder="https://…"
        :aria-label="t('enrich.pasteUrl')"
      />
      <BaseButton
        class="btn btn-primary btn-xs shrink-0"
        :loading="applying"
        :disabled="!url.trim()"
        @click="apply"
      >
        {{ t('enrich.applyUrl') }}
      </BaseButton>
      <div v-if="url.trim()" class="w-9 aspect-[2/3] rounded overflow-hidden bg-base-200 ring-1 ring-base-300 shrink-0">
        <BaseCover :src="url.trim()" class="w-full h-full" icon-class="h-4 w-4" />
      </div>
    </div>
  </div>
</template>
