<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'

const props = withDefaults(defineProps<{
  text: string
  speed?: number
}>(), { speed: 80 })

const emit = defineEmits<{ complete: [] }>()

const visibleCount = ref(0)
let timerId: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  if (!props.text.length) { emit('complete'); return }
  timerId = setInterval(() => {
    visibleCount.value++
    if (visibleCount.value >= props.text.length) {
      clearInterval(timerId!)
      timerId = null
      emit('complete')
    }
  }, props.speed)
})

onBeforeUnmount(() => {
  if (timerId) clearInterval(timerId)
})
</script>

<template>
  <span :aria-label="text" role="text">
    <span
      v-for="(char, index) in text.split('')"
      :key="index"
      class="inline-block transition-all duration-150 ease-out"
      :class="index < visibleCount ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-2'"
    >{{ char === ' ' ? '\u00A0' : char }}</span>
    <span
      v-if="visibleCount < text.length"
      class="inline-block w-px h-[0.8em] bg-current ml-0.5 align-middle cursor-blink"
    />
  </span>
</template>

<style scoped>
.cursor-blink {
  animation: blink 0.9s step-end infinite;
}
@keyframes blink {
  0%, 100% { opacity: 1; }
  50% { opacity: 0; }
}
</style>
