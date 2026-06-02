<script setup lang="ts">
import { ref, watchEffect } from 'vue'
import QRCode from 'qrcode'

const props = withDefaults(
  defineProps<{
    value: string
    size?: number
  }>(),
  { size: 220 },
)

const dataUrl = ref<string>('')

watchEffect(async () => {
  if (!props.value) {
    dataUrl.value = ''
    return
  }
  try {
    dataUrl.value = await QRCode.toDataURL(props.value, { width: props.size })
  } catch {
    dataUrl.value = ''
  }
})
</script>

<template>
  <img v-if="dataUrl" :src="dataUrl" :width="size" :height="size" alt="QR Code" class="rounded-lg" />
</template>
