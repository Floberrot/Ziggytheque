<script setup lang="ts">
import { ExternalLink } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import type { PriceOffer } from '@/api/manga'
import BaseMerchantLogo from '@/components/atoms/BaseMerchantLogo.vue'

defineProps<{ offer: PriceOffer }>()

const { t } = useI18n()

const CURRENCY_SYMBOLS: Record<string, string> = {
  EUR: '€',
  USD: '$',
  GBP: '£',
  JPY: '¥',
}

function formatPrice(amount: number, currency: string): string {
  const symbol = CURRENCY_SYMBOLS[currency] ?? currency
  return amount.toFixed(2) + ' ' + symbol
}
</script>

<template>
  <div class="flex items-center gap-3 p-3 rounded-xl border border-base-300/70 bg-base-100">
    <!-- Merchant logo -->
    <div class="shrink-0 w-10 h-6 flex items-center justify-center">
      <BaseMerchantLogo :logo="offer.merchantLogo" class="h-full w-full" />
    </div>

    <!-- Info -->
    <div class="flex-1 min-w-0">
      <p class="text-sm font-semibold leading-tight">{{ offer.merchant }}</p>
      <p class="text-[11px] text-base-content/50 capitalize">
        {{ offer.kind === 'merchant_live' ? t('prices.merchantLive') : t('prices.publisherReference') }}
      </p>
    </div>

    <!-- Price + link -->
    <div class="flex items-center gap-2 shrink-0">
      <span class="text-base font-bold tabular-nums">
        {{ formatPrice(offer.amount, offer.currency) }}
      </span>
      <a
        v-if="offer.url"
        :href="offer.url"
        target="_blank"
        rel="noopener noreferrer"
        class="btn btn-ghost btn-xs btn-circle"
        :aria-label="`Voir sur ${offer.merchant}`"
      >
        <ExternalLink class="h-3.5 w-3.5" />
      </a>
    </div>
  </div>
</template>
