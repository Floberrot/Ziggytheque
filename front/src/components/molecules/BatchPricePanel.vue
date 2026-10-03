<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { Check, HelpCircle, Tag, X } from 'lucide-vue-next'
import BaseButton from '@/components/atoms/BaseButton.vue'

/** One unit price applied to every tome of the series at once. */
defineProps<{
  totalVolumes: number
  pending: boolean
}>()

/**
 * v-model.number yields the raw string ('') when the input is empty or not a number,
 * so the model holds number | string | null; `priceValue` keeps the usable number.
 * The page owns it, to clear it once the price is applied.
 */
const price = defineModel<number | string | null>('price', { required: true })

const emit = defineEmits<{
  apply: [price: number]
  close: []
}>()

const { t } = useI18n()

const priceValue = computed<number | null>(() =>
  typeof price.value === 'number' && !Number.isNaN(price.value) ? price.value : null,
)

function apply(): void {
  if (priceValue.value !== null) emit('apply', priceValue.value)
}
</script>

<template>
  <div class="rounded-xl bg-base-200/40 border border-base-content/8 p-3 flex flex-wrap items-center gap-3">
    <div class="flex items-center gap-2.5 min-w-0">
      <div class="w-8 h-8 rounded-lg bg-secondary/15 text-secondary flex items-center justify-center shrink-0">
        <Tag class="h-4 w-4" />
      </div>
      <div class="min-w-0">
        <div class="text-sm font-semibold leading-tight flex items-center gap-1.5">
          {{ t('manga.batchPriceTitle') }}
          <div class="tooltip tooltip-top" :data-tip="t('manga.batchPriceHelp')">
            <HelpCircle class="h-3.5 w-3.5 text-base-content/35 cursor-help" />
          </div>
        </div>
        <div class="text-[11px] text-base-content/50 leading-tight mt-0.5">
          {{ t('manga.batchPriceScope', { count: totalVolumes }) }}
        </div>
      </div>
    </div>
    <div class="flex items-center gap-2 ml-auto">
      <div class="relative">
        <input
          v-model.number="price"
          type="number"
          step="0.01"
          min="0"
          class="input input-sm input-bordered w-28 pr-7 tabular-nums font-mono [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none"
          placeholder="0.00"
          :aria-label="t('manga.batchPriceTitle')"
          @keydown.enter="apply"
        />
        <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-base-content/40 pointer-events-none font-medium">€</span>
      </div>
      <div
        class="tooltip tooltip-top tooltip-secondary"
        :data-tip="priceValue === null
          ? t('manga.batchPriceMissing')
          : t('manga.batchPriceApplyEach', { price: priceValue.toFixed(2) })"
      >
        <BaseButton
          class="btn btn-secondary btn-sm gap-1.5"
          :loading="pending"
          :disabled="priceValue === null"
          @click="apply"
        >
          <template #icon><Check class="h-3.5 w-3.5" stroke-width="3" /></template>
          {{ t('collection.batchSetPrice') }}
        </BaseButton>
      </div>
      <button class="btn btn-ghost btn-sm btn-circle" :aria-label="t('common.close')" @click="emit('close')">
        <X class="h-4 w-4" />
      </button>
    </div>
  </div>
</template>
