<script setup lang="ts">
import { ref, computed } from 'vue'
import AIcon from '../atoms/AIcon.vue'

interface Props {
  src?: string
  alt: string
  loading?: boolean
  aspect?: string
  class?: string
}

const props = withDefaults(defineProps<Props>(), {
  aspect: '2/3',
})

const imageError = ref(false)
const imageLoading = ref(false)

const showFallback = computed(() => !props.src || imageError.value)
</script>

<template>
  <div
    :style="{ aspectRatio: aspect }"
    :class="[
      'relative bg-base-200 rounded-lg overflow-hidden',
      class,
    ]"
  >
    <img
      v-show="!showFallback"
      :src="src"
      :alt="alt"
      class="w-full h-full object-cover"
      @error="imageError = true"
      @load="imageLoading = false"
      @loadstart="imageLoading = true"
    />
    <div v-if="showFallback" class="flex items-center justify-center w-full h-full bg-base-300">
      <AIcon name="lucide:image-off" size="lg" class="text-base-content/40" />
    </div>
  </div>
</template>
