<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useBarcodeScanner } from '@/composables/useBarcodeScanner'
import { submitScan } from '@/api/manga'

const { t } = useI18n()
const route = useRoute()
const token = route.params.token as string

const videoRef = ref<HTMLVideoElement | null>(null)
const { isScanning, errorMessage: cameraError, start: startScanner } = useBarcodeScanner()

const successIsbn = ref<string | null>(null)
const scanError = ref<string | null>(null)
const isSubmitting = ref(false)

async function onScan(isbn: string): Promise<void> {
  if (isSubmitting.value) return
  isSubmitting.value = true
  scanError.value = null

  try {
    await submitScan({ scanToken: token, isbn })
    successIsbn.value = isbn
  } catch (err: unknown) {
    const status = (err as { response?: { status?: number } })?.response?.status
    if (status === 410) {
      scanError.value = t('scan.expired')
    } else if (status === 422) {
      scanError.value = t('scan.invalidCode')
    } else {
      scanError.value = t('scan.invalidCode')
    }
  } finally {
    isSubmitting.value = false
  }
}

function scanAnother(): void {
  successIsbn.value = null
  scanError.value = null
  if (videoRef.value) {
    startScanner(videoRef.value, onScan)
  }
}

onMounted(() => {
  if (videoRef.value) {
    startScanner(videoRef.value, onScan)
  }
})
</script>

<template>
  <div class="min-h-screen bg-base-200 flex items-center justify-center p-4">
    <div class="card bg-base-100 shadow-xl w-full max-w-sm">
      <div class="card-body gap-4">
        <h1 class="card-title text-xl justify-center">{{ t('scan.title') }}</h1>

        <!-- Success state -->
        <div v-if="successIsbn" class="flex flex-col items-center gap-4 py-4">
          <div class="text-6xl">✓</div>
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

          <video
            ref="videoRef"
            class="w-full rounded-xl aspect-video object-cover bg-base-300"
            autoplay
            muted
            playsinline
          />

          <div v-if="cameraError" class="alert alert-error alert-sm text-sm">
            {{ cameraError || t('scan.cameraError') }}
          </div>

          <div v-if="scanError" class="alert alert-warning alert-sm text-sm">
            {{ scanError }}
          </div>

          <div v-if="isScanning && !cameraError" class="flex items-center gap-2 text-sm text-base-content/50 justify-center">
            <span class="loading loading-spinner loading-xs" />
            Recherche du code-barres…
          </div>
        </template>
      </div>
    </div>
  </div>
</template>
