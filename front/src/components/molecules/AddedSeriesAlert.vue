<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { CheckCircle2, Star, X } from 'lucide-vue-next'
import type { CatalogueRegistration } from '@/api/catalogue'
import BaseButton from '@/components/atoms/BaseButton.vue'
import EditionBadge from '@/components/molecules/EditionBadge.vue'

/** What the last addition did, with the next steps: open the series, wish the rest. */
defineProps<{
  summary: {
    registration: CatalogueRegistration
    workTitle: string
    publisher: string | null
    specialEdition: string | null
  }
  sendingToWishlist: boolean
}>()

const emit = defineEmits<{
  openSeries: []
  sendToWishlist: []
  dismiss: []
}>()

const { t } = useI18n()
</script>

<template>
  <div class="alert alert-success items-start" role="status">
    <CheckCircle2 class="h-6 w-6 shrink-0" />
    <div class="flex-1 min-w-0 space-y-1">
      <p class="font-semibold">{{ summary.workTitle }}</p>
      <EditionBadge :publisher="summary.publisher" :special-edition="summary.specialEdition" />
      <p class="text-sm">
        <template v-if="summary.registration.seriesCreated">
          {{ t('add.seriesCreated', { count: summary.registration.totalVolumes }) }}
        </template>
        <template v-if="summary.registration.addedNumbers.length">
          {{ t('add.tomesAdded', { list: summary.registration.addedNumbers.join(', ') }) }}
        </template>
        <template v-else>{{ t('add.seriesFollowed') }}</template>
      </p>
      <div class="flex flex-wrap gap-2 pt-1">
        <button class="btn btn-sm" @click="emit('openSeries')">
          {{ t('add.openSeries') }}
        </button>
        <BaseButton
          class="btn btn-sm btn-ghost gap-1"
          :loading="sendingToWishlist"
          @click="emit('sendToWishlist')"
        >
          <template #icon><Star class="h-4 w-4" /></template>
          {{ t('add.missingToWishlist') }}
        </BaseButton>
      </div>
    </div>
    <button class="btn btn-ghost btn-xs btn-circle" :aria-label="t('common.close')" @click="emit('dismiss')">
      <X class="h-4 w-4" />
    </button>
  </div>
</template>
