<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useScanSession } from '@/composables/useScanSession'

const emit = defineEmits<{
  isbn: [isbn: string]
}>()

const { t } = useI18n()

const canvasRef = ref<HTMLCanvasElement | null>(null)
const { pairingUrl, start } = useScanSession()
const isLoading = ref(false)

async function generateQr(url: string) {
  try {
    const QRCode = await import('qrcode')
    const canvas = canvasRef.value
    if (canvas) {
      await QRCode.default.toCanvas(canvas, url, { width: 200 })
    }
  } catch {
    // QR generation is best-effort
  }
}

onMounted(async () => {
  isLoading.value = true
  try {
    await start({
      onIsbn: (isbn) => {
        emit('isbn', isbn)
      },
    })

    if (pairingUrl.value) {
      await generateQr(pairingUrl.value)
    }
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <div class="flex flex-col items-center gap-3">
    <p class="text-sm text-base-content/70">{{ t('scan.scanWithPhone') }}</p>

    <div v-if="isLoading" class="loading loading-spinner" />

    <canvas v-show="!isLoading" ref="canvasRef" class="rounded-lg" />

    <p v-if="pairingUrl" class="text-xs text-base-content/50 break-all max-w-xs text-center">
      {{ pairingUrl }}
    </p>
  </div>
</template>
