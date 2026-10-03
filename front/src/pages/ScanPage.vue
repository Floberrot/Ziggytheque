<script setup lang="ts">
import { ref, nextTick, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useBarcodeScanner } from '@/composables/useBarcodeScanner'
import { submitScan } from '@/api/manga'
import BaseLoader from '@/components/atoms/BaseLoader.vue'
import ScanViewfinder from '@/components/molecules/ScanViewfinder.vue'

const { t } = useI18n()
const route = useRoute()
const token = route.params.token as string
// Opened from the "add a manga" page: scan a whole shelf without tapping in between.
const isBatch = route.query.batch === '1'

const viewfinder = ref<InstanceType<typeof ScanViewfinder> | null>(null)
const readCount = ref(0)
const {
  isScanning, errorKey: cameraErrorKey, torchAvailable, torchOn, start: startScanner, startContinuous, toggleTorch,
} = useBarcodeScanner()

const successIsbn = ref<string | null>(null)
const scanError = ref<string | null>(null)
const isSubmitting = ref(false)
const sentIsbns = ref<string[]>([])

async function onScan(isbn: string): Promise<void> {
  // One-shot mode waits for the answer; a shelf scan never drops the next book.
  if (isSubmitting.value && !isBatch) return
  isSubmitting.value = true
  scanError.value = null
  readCount.value++

  try {
    await submitScan({ scanToken: token, isbn })
    if (isBatch) {
      sentIsbns.value = [isbn, ...sentIsbns.value]
    } else {
      successIsbn.value = isbn
    }
  } catch (err: unknown) {
    const status = (err as { response?: { status?: number } })?.response?.status
    if (status === 410) {
      scanError.value = t('scan.expired')
    } else if (status === 422) {
      scanError.value = t('scan.invalidCode')
    } else {
      scanError.value = t('scan.invalidCode')
    }
    // One-shot mode stopped the camera on that read: give the user another try.
    if (!isBatch && status !== 410) {
      void openCamera()
    }
  } finally {
    isSubmitting.value = false
  }
}

async function openCamera(): Promise<void> {
  // The preview is (re)rendered by the state change that led here — wait for it.
  await nextTick()
  const video = viewfinder.value?.video
  if (!video) return
  if (isBatch) {
    await startContinuous(video, onScan)
  } else {
    await startScanner(video, onScan)
  }
}

function scanAnother(): void {
  successIsbn.value = null
  scanError.value = null
  void openCamera()
}

onMounted(() => {
  void openCamera()
})
</script>

<template>
  <div class="min-h-screen bg-base-200 flex items-center justify-center p-4">
    <div class="card bg-base-100 shadow-xl w-full max-w-sm">
      <div class="card-body gap-4">
        <h1 class="card-title text-xl justify-center">{{ t('scan.title') }}</h1>

        <!-- Success state -->
        <div v-if="successIsbn" class="flex flex-col items-center gap-4 py-4">
          <div class="text-6xl" aria-hidden="true">✓</div>
          <p class="text-success font-semibold text-center">
            {{ t('scan.success', { isbn: successIsbn }) }}
          </p>
          <button class="btn btn-outline btn-sm" @click="scanAnother()">
            {{ t('scan.scanAnother') }}
          </button>
        </div>

        <!-- Scan state -->
        <template v-else>
          <p class="text-sm text-base-content/60 text-center">{{ t('scan.instructions') }}</p>

          <ScanViewfinder
            ref="viewfinder"
            :read-count="readCount"
            :torch-available="torchAvailable"
            :torch-on="torchOn"
            @toggle-torch="toggleTorch()"
          />

          <div v-if="cameraErrorKey" class="alert alert-error alert-sm text-sm">
            {{ t(cameraErrorKey) }}
          </div>

          <div v-if="scanError" class="alert alert-warning alert-sm text-sm">
            {{ scanError }}
          </div>

          <div v-if="isScanning && !cameraErrorKey" class="flex items-center gap-2 text-sm text-base-content/50 justify-center">
            <BaseLoader size="xs" />
            {{ t('scan.searching') }}
          </div>

          <div v-if="isBatch && sentIsbns.length" class="space-y-1 text-center">
            <p class="text-success font-semibold text-sm">{{ t('scan.batchSent', { count: sentIsbns.length }, sentIsbns.length) }}</p>
            <p class="text-xs text-base-content/50 font-mono">{{ sentIsbns[0] }}</p>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>
