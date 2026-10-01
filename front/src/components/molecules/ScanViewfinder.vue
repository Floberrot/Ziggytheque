<script setup lang="ts">
import { ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { Flashlight, FlashlightOff } from 'lucide-vue-next'

/**
 * The camera preview of a scanner: a framing guide tells where to hold the barcode,
 * the frame flashes (and the phone vibrates) on every code read, and the camera's
 * light can be switched on for dim shelves.
 */
const props = defineProps<{
  /** Bumped by the parent on every code read. */
  readCount: number
  torchAvailable: boolean
  torchOn: boolean
}>()

const emit = defineEmits<{ toggleTorch: [] }>()
const { t } = useI18n()

const video = ref<HTMLVideoElement | null>(null)
const flashing = ref(false)
let flashTimer: ReturnType<typeof setTimeout> | null = null

watch(() => props.readCount, () => {
  navigator.vibrate?.(60)
  flashing.value = true
  if (flashTimer) clearTimeout(flashTimer)
  flashTimer = setTimeout(() => { flashing.value = false }, 450)
})

defineExpose({ video })
</script>

<template>
  <div class="relative w-full overflow-hidden rounded-2xl bg-black aspect-[4/3] sm:aspect-video">
    <video ref="video" class="absolute inset-0 h-full w-full object-cover" autoplay muted playsinline />

    <!-- Framing guide: a book barcode is wide and short -->
    <div class="pointer-events-none absolute inset-0 flex items-center justify-center">
      <div
        class="relative h-1/3 w-4/5 max-w-sm rounded-xl border-2 transition-colors duration-200 shadow-[0_0_0_9999px_rgba(0,0,0,0.35)]"
        :class="flashing ? 'border-success bg-success/20' : 'border-white/80'"
      >
        <span class="scan-line absolute inset-x-3 h-0.5 rounded-full bg-primary/90 shadow-[0_0_8px] shadow-primary" />
      </div>
    </div>

    <button
      v-if="torchAvailable"
      type="button"
      class="btn btn-circle btn-sm absolute right-3 top-3 border-0 bg-black/50 text-white hover:bg-black/70"
      :aria-label="torchOn ? t('scanBatch.torchOff') : t('scanBatch.torchOn')"
      :aria-pressed="torchOn"
      @click="emit('toggleTorch')"
    >
      <FlashlightOff v-if="torchOn" class="h-4 w-4" />
      <Flashlight v-else class="h-4 w-4" />
    </button>
  </div>
</template>

<style scoped>
.scan-line {
  animation: scanSweep 1.8s ease-in-out infinite alternate;
}

@keyframes scanSweep {
  from { top: 12%; }
  to { top: 88%; }
}

@media (prefers-reduced-motion: reduce) {
  .scan-line {
    animation: none;
    top: 50%;
  }
}
</style>
