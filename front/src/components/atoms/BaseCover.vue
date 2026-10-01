<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Book } from 'lucide-vue-next'
import { coverUrl, isPlaceholderImage } from '@/utils/coverUrl'

/**
 * A cover that never shows a broken image: a missing, failing or placeholder cover
 * (the few-pixel images some catalogues answer with) falls back to a book icon —
 * or to the `fallback` slot. Size and shape come from the classes the parent sets.
 */
const props = withDefaults(
  defineProps<{
    src: string | null | undefined
    alt?: string
    iconClass?: string
    eager?: boolean
  }>(),
  { alt: '', iconClass: 'h-1/3 w-1/3 max-h-10 max-w-10', eager: false },
)

const emit = defineEmits<{ missing: [] }>()

const failed = ref(false)
watch(() => props.src, () => { failed.value = false })

const resolvedSrc = computed(() => (failed.value ? null : coverUrl(props.src)))

function markMissing(): void {
  failed.value = true
  emit('missing')
}

function onLoad(event: Event): void {
  if (isPlaceholderImage(event.target as HTMLImageElement)) markMissing()
}
</script>

<template>
  <img
    v-if="resolvedSrc"
    :src="resolvedSrc"
    :alt="alt"
    class="object-cover"
    :loading="eager ? 'eager' : 'lazy'"
    decoding="async"
    @load="onLoad"
    @error="markMissing"
  />
  <div v-else class="flex items-center justify-center bg-base-200 text-base-content/25" role="img" :aria-label="alt">
    <slot name="fallback">
      <Book :class="iconClass" stroke-width="1.5" />
    </slot>
  </div>
</template>
