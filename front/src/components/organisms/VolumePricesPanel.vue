<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { QrCode } from 'lucide-vue-next'
import type { PriceOffer, RetailerOffer } from '@/api/manga'
import BaseLoader from '@/components/atoms/BaseLoader.vue'
import PriceOfferCard from '@/components/molecules/PriceOfferCard.vue'
import RetailerPriceCard from '@/components/molecules/RetailerPriceCard.vue'

/** "Prices" tab of the tome modal: the target shops first, then every other offer. */
const props = defineProps<{
  loading: boolean
  /** i18n key of the failure, or null. */
  errorKey: string | null
  loaded: boolean
  hasIsbn: boolean
  retailers: RetailerOffer[]
  offers: PriceOffer[]
}>()

const emit = defineEmits<{ enterIsbn: [] }>()

const { t } = useI18n()

// Offers not already surfaced as a target shop's best offer (shown below the 3 cards).
const otherOffers = computed(() => {
  const bestOffers = props.retailers
    .map((retailerBlock) => retailerBlock.bestOffer)
    .filter((offer) => offer !== null)
  return props.offers.filter(
    (offer) => !bestOffers.some(
      (best) => best.merchant === offer.merchant && best.amount === offer.amount && best.url === offer.url,
    ),
  )
})
</script>

<template>
  <div class="flex flex-col gap-3">
    <BaseLoader v-if="loading" variant="section" />
    <p v-else-if="errorKey" class="text-sm text-error">{{ t(errorKey) }}</p>
    <template v-else-if="loaded">
      <!-- No ISBN: explain + shortcut to the ISBN tab (no dead end) -->
      <div v-if="!hasIsbn" class="flex flex-col items-center gap-3 py-4">
        <p class="text-sm text-base-content/50 text-center">
          {{ t('prices.noIsbn') }}
        </p>
        <button class="btn btn-sm btn-primary gap-2" @click="emit('enterIsbn')">
          <QrCode class="h-4 w-4" />
          {{ t('prices.enterIsbn') }}
        </button>
      </div>
      <template v-else>
        <!-- The 3 target shops — always shown, found or honestly "not found" -->
        <p class="text-[11px] font-bold uppercase tracking-wide text-base-content/45">
          {{ t('prices.retailersTitle') }}
        </p>
        <div class="grid grid-cols-3 gap-2 sm:gap-3">
          <RetailerPriceCard
            v-for="retailerBlock in retailers"
            :key="retailerBlock.retailer"
            :retailer="retailerBlock"
          />
        </div>

        <!-- Remaining offers (other merchants, publisher references) -->
        <template v-if="otherOffers.length">
          <p class="text-[11px] font-bold uppercase tracking-wide text-base-content/45 mt-2">
            {{ t('prices.otherOffers') }}
          </p>
          <PriceOfferCard
            v-for="(offer, index) in otherOffers"
            :key="`${offer.source}-${index}`"
            :offer="offer"
          />
        </template>
        <p v-else-if="!offers.length" class="text-sm text-base-content/40 py-2 text-center">
          {{ t('prices.empty') }}
        </p>
      </template>
    </template>
    <p v-else class="text-sm text-base-content/40 py-4 text-center">
      {{ t('prices.loading') }}
    </p>
  </div>
</template>
