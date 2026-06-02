<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'

const props = withDefaults(
  defineProps<{
    src: string
    alt?: string
    /** Distance from the viewport at which loading begins (IntersectionObserver rootMargin). */
    rootMargin?: string
  }>(),
  {
    alt: '',
    rootMargin: '200px',
  },
)

const root = ref<HTMLElement | null>(null)

// The <img> src is only set once the tile approaches the viewport, so a series
// with 100+ volumes never fires 100+ cover requests up front — off-screen tiles
// stay as a cheap placeholder until the user scrolls them into view.
const shouldLoad = ref(false)
const loaded = ref(false)
const errored = ref(false)

let observer: IntersectionObserver | null = null

function reveal(): void {
  shouldLoad.value = true
  // Load once: stop observing as soon as the tile has entered the viewport.
  observer?.disconnect()
  observer = null
}

onMounted(() => {
  // Graceful degradation: without IntersectionObserver, load straight away.
  if (typeof IntersectionObserver === 'undefined') {
    reveal()
    return
  }

  observer = new IntersectionObserver(
    (entries) => {
      if (entries.some((entry) => entry.isIntersecting)) {
        reveal()
      }
    },
    { rootMargin: props.rootMargin },
  )

  if (root.value) {
    observer.observe(root.value)
  }
})

onBeforeUnmount(() => {
  observer?.disconnect()
  observer = null
})

function onLoad(): void {
  loaded.value = true
}

function onError(): void {
  errored.value = true
}
</script>

<template>
  <div ref="root" class="relative h-full w-full">
    <!-- Placeholder: static while off-screen, pulsing only while the image decodes. -->
    <div
      v-if="!loaded && !errored"
      class="absolute inset-0 bg-base-200"
      :class="{ 'animate-pulse': shouldLoad }"
    />

    <img
      v-if="shouldLoad && !errored"
      :src="src"
      :alt="alt"
      class="h-full w-full object-cover transition-opacity duration-300"
      :style="{ opacity: loaded ? 1 : 0 }"
      decoding="async"
      loading="lazy"
      @load="onLoad"
      @error="onError"
    />

    <slot v-if="errored" name="fallback" />
  </div>
</template>
