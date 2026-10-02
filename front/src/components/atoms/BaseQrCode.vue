<script setup lang="ts">
import { ref, watchEffect } from 'vue'

const props = withDefaults(
  defineProps<{
    value: string
    size?: number
  }>(),
  { size: 220 },
)

const dataUrl = ref<string>('')

watchEffect(async () => {
  // Read before the first await: only what is read synchronously is tracked.
  const value = props.value
  const width = props.size
  if (!value) {
    dataUrl.value = ''
    return
  }
  try {
    // The QR code library is fetched with the first code shown, not with the page.
    const { toDataURL } = await import('qrcode')
    dataUrl.value = await toDataURL(value, { width })
  } catch {
    dataUrl.value = ''
  }
})
</script>

<template>
  <img v-if="dataUrl" :src="dataUrl" :width="size" :height="size" alt="QR Code" class="rounded-lg" />
</template>
