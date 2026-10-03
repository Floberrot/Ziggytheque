<script setup lang="ts">
import { nextTick, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Camera, Smartphone, X } from 'lucide-vue-next'
import type { ScanFeedItem } from '@/types'
import { useBarcodeScanner } from '@/composables/useBarcodeScanner'
import { useScanSession } from '@/composables/useScanSession'
import { useUiStore } from '@/stores/useUiStore'
import BaseButton from '@/components/atoms/BaseButton.vue'
import BaseQrCode from '@/components/atoms/BaseQrCode.vue'
import ScanViewfinder from '@/components/molecules/ScanViewfinder.vue'
import ScanFeed from '@/components/organisms/ScanFeed.vue'

/**
 * "Scan" tab of the add page: scan a shelf with the computer's camera or a phone.
 * Every code read is emitted — the page adds the tome (and its series) and lists it in
 * the feed. Leaving the tab unmounts this panel, which stops the camera and the phone.
 */
defineProps<{
  feed: ScanFeedItem[]
  /** Bumped by the page on every code it accepts: the viewfinder flashes. */
  readCount: number
  scannedCount: number
}>()

const emit = defineEmits<{
  barcode: [code: string]
  undo: [item: ScanFeedItem]
  openSeries: [item: ScanFeedItem]
  searchManually: [item: ScanFeedItem]
  clearFeed: []
}>()

const { t } = useI18n()
const ui = useUiStore()

const viewfinder = ref<InstanceType<typeof ScanViewfinder> | null>(null)
const cameraOn = ref(false)
const scanner = useBarcodeScanner()
const { errorKey: cameraErrorKey, torchAvailable, torchOn } = scanner
const phoneSession = useScanSession()
const phoneUrl = ref<string | null>(null)
const isOpeningPhoneSession = ref(false)

function onBarcode(code: string): void {
  emit('barcode', code)
}

async function startCamera(): Promise<void> {
  cameraOn.value = true
  // The <video> is rendered once cameraOn flips — wait for it.
  await nextTick()
  const video = viewfinder.value?.video
  if (video) {
    await scanner.startContinuous(video, onBarcode)
  }
}

function stopCamera(): void {
  scanner.stop()
  cameraOn.value = false
}

async function startPhoneScan(): Promise<void> {
  isOpeningPhoneSession.value = true
  try {
    const session = await phoneSession.open(null, { onResult: onBarcode })
    phoneUrl.value = `${window.location.origin}/scan/${session.scanToken}?batch=1`
  } catch {
    ui.addToast(t('scanBatch.phoneError'), 'error')
  } finally {
    isOpeningPhoneSession.value = false
  }
}

function stopPhoneScan(): void {
  phoneSession.close()
  phoneUrl.value = null
}
</script>

<template>
  <section class="space-y-4">
    <p class="text-sm text-base-content/60">{{ t('scanBatch.intro') }}</p>

    <div class="grid gap-2 sm:grid-cols-2">
      <button v-if="!cameraOn" class="btn btn-primary gap-2" @click="startCamera">
        <Camera class="h-5 w-5" />
        {{ t('scanBatch.startCamera') }}
      </button>
      <button v-else class="btn btn-outline gap-2" @click="stopCamera">
        <X class="h-5 w-5" />
        {{ t('scanBatch.stopCamera') }}
      </button>

      <BaseButton v-if="!phoneUrl" class="btn btn-outline gap-2" :loading="isOpeningPhoneSession" @click="startPhoneScan">
        <template #icon><Smartphone class="h-5 w-5" /></template>
        {{ t('scanBatch.usePhone') }}
      </BaseButton>
      <button v-else class="btn btn-outline gap-2" @click="stopPhoneScan">
        <X class="h-5 w-5" />
        {{ t('scanBatch.stopPhone') }}
      </button>
    </div>

    <div v-if="cameraOn" class="space-y-2">
      <ScanViewfinder
        ref="viewfinder"
        :read-count="readCount"
        :torch-available="torchAvailable"
        :torch-on="torchOn"
        @toggle-torch="scanner.toggleTorch()"
      />
      <p v-if="cameraErrorKey" class="alert alert-error text-sm py-2">{{ t(cameraErrorKey) }}</p>
      <p v-else class="text-xs text-center text-base-content/50">{{ t('scanBatch.cameraHint') }}</p>
    </div>

    <div v-if="phoneUrl" class="flex flex-col items-center gap-2 p-4 bg-base-100 rounded-2xl border border-base-200">
      <BaseQrCode :value="phoneUrl" :size="200" />
      <p class="text-xs text-center text-base-content/60 max-w-xs">{{ t('scanBatch.phoneHint') }}</p>
    </div>

    <div v-if="feed.length" class="space-y-2">
      <div class="flex items-center justify-between">
        <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">
          {{ t('scanBatch.summary', { count: scannedCount }, scannedCount) }}
        </p>
        <button class="btn btn-ghost btn-xs" @click="emit('clearFeed')">{{ t('scanBatch.clear') }}</button>
      </div>
      <ScanFeed
        :items="feed"
        @undo="emit('undo', $event)"
        @open-series="emit('openSeries', $event)"
        @search-manually="emit('searchManually', $event)"
      />
    </div>
  </section>
</template>
