<script setup lang="ts">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { Camera, Smartphone } from 'lucide-vue-next'
import BaseButton from '@/components/atoms/BaseButton.vue'
import BaseQrCode from '@/components/atoms/BaseQrCode.vue'

/**
 * "Scan" tab of the tome modal: the computer's camera, or the phone through a QR code.
 * The modal owns the scanner and the phone session (a phone scan still lands after
 * leaving the tab); it reaches the camera preview through the exposed `video`.
 */
defineProps<{
  isScanning: boolean
  /** i18n key of the camera error, or null. */
  cameraErrorKey: string | null
  openingPhoneSession: boolean
  /** The address the phone opens, shown as a QR code once the session exists. */
  phoneUrl: string
  /** Covers found above: a separator keeps the two blocks apart. */
  belowResults: boolean
}>()

const emit = defineEmits<{
  toggleCamera: []
  startPhone: []
}>()

const { t } = useI18n()

const video = ref<HTMLVideoElement | null>(null)

defineExpose({ video })
</script>

<template>
  <div class="flex flex-col gap-3" :class="belowResults ? 'mt-5 pt-5 border-t border-base-200' : ''">
    <button class="btn btn-sm btn-outline gap-2 w-full" :class="{ 'btn-active': isScanning }" :aria-pressed="isScanning" @click="emit('toggleCamera')">
      <Camera class="h-4 w-4" />
      {{ t('enrich.scanCamera') }}
    </button>
    <p v-if="cameraErrorKey" class="text-error text-xs">{{ t(cameraErrorKey) }}</p>
    <video v-show="isScanning" ref="video" class="w-full rounded-lg aspect-video object-cover bg-base-200" autoplay muted playsinline />
    <BaseButton class="btn btn-sm btn-outline gap-2 w-full" :loading="openingPhoneSession" @click="emit('startPhone')">
      <template #icon><Smartphone class="h-4 w-4" /></template>
      {{ t('enrich.scanPhone') }}
    </BaseButton>
    <div v-if="phoneUrl" class="flex flex-col items-center gap-2 pt-1">
      <BaseQrCode :value="phoneUrl" :size="180" />
      <a :href="phoneUrl" target="_blank" rel="noopener noreferrer" class="link link-primary text-xs">{{ t('enrich.scanLinkTitle') }}</a>
    </div>
  </div>
</template>
