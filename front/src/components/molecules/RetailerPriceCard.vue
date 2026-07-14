<script setup lang="ts">
import { computed } from 'vue'
import { ExternalLink, SearchX } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import type { RetailerOffer } from '@/api/manga'
import BaseMerchantLogo from '@/components/atoms/BaseMerchantLogo.vue'
import { formatPrice } from '@/utils/price'

const props = defineProps<{ retailer: RetailerOffer }>()

const { t } = useI18n()

const found = computed(() => props.retailer.status === 'found' && props.retailer.bestOffer !== null)
</script>

<template>
  <component
    :is="found && retailer.bestOffer?.url ? 'a' : 'div'"
    :href="found ? (retailer.bestOffer?.url ?? undefined) : undefined"
    :target="found && retailer.bestOffer?.url ? '_blank' : undefined"
    :rel="found && retailer.bestOffer?.url ? 'noopener noreferrer' : undefined"
    class="flex flex-col items-center gap-2 p-3 rounded-xl border text-center transition-all duration-150"
    :class="found
      ? 'border-base-300/70 bg-base-100 hover:border-primary/50 hover:shadow-md'
      : 'border-base-300/40 bg-base-200/40 opacity-60'"
    :aria-label="found ? t('prices.viewOn', { merchant: retailer.label }) : `${retailer.label} — ${t('prices.notFound')}`"
  >
    <div class="h-6 w-16 flex items-center justify-center" :class="found ? '' : 'grayscale'">
      <BaseMerchantLogo :logo="retailer.retailer" class="h-full w-full" />
    </div>

    <template v-if="found && retailer.bestOffer">
      <span class="text-base font-bold tabular-nums leading-none">
        {{ formatPrice(retailer.bestOffer.amount, retailer.bestOffer.currency) }}
      </span>
      <span v-if="retailer.bestOffer.url" class="inline-flex items-center gap-1 text-[11px] text-primary/80 font-medium">
        <ExternalLink class="h-3 w-3" />
        {{ t('prices.viewOn', { merchant: retailer.label }) }}
      </span>
    </template>

    <template v-else>
      <span class="inline-flex items-center gap-1 text-xs text-base-content/40 font-medium">
        <SearchX class="h-3.5 w-3.5" />
        {{ t('prices.notFound') }}
      </span>
    </template>
  </component>
</template>
