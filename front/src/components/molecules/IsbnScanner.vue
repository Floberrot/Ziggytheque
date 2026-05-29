<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useBarcodeScanner } from '@/composables/useBarcodeScanner'

const emit = defineEmits<{
  detected: [isbn: string]
}>()

const { t } = useI18n()

const videoRef = ref<HTMLVideoElement | null>(null)
const { isSupported, isScanning, error, start, stop, onDetected } = useBarcodeScanner(videoRef)

onDetected((isbn) => {
  emit('detected', isbn)
})

async function handleStart() {
  await start()
}
</script>

<template>
  <div class="flex flex-col items-center gap-2">
    <div v-if="error && !isSupported" class="alert alert-warning text-sm">
      {{ t('scan.cameraUnavailable') }}
    </div>

    <video
      v-show="isScanning"
      ref="videoRef"
      class="rounded-lg w-full max-w-xs"
      autoplay
      playsinline
      muted
    />

    <div class="flex gap-2">
      <button v-if="!isScanning" class="btn btn-primary btn-sm" @click="handleStart">
        {{ t('scan.title') }}
      </button>
      <button v-else class="btn btn-ghost btn-sm" @click="stop">
        {{ t('common.cancel', 'Annuler') }}
      </button>
    </div>
  </div>
</template>
