<script setup lang="ts">
import { ref, computed } from 'vue'
import { useFloating, offset, flip, shift, autoUpdate } from '@floating-ui/vue'

interface Props {
  text: string
  side?: 'top' | 'right' | 'bottom' | 'left'
}

const props = withDefaults(defineProps<Props>(), {
  side: 'top',
})

const reference = ref()
const floating = ref()

const { x, y } = useFloating(reference, floating, {
  placement: props.side,
  middleware: [offset(8), flip(), shift({ padding: 8 })],
  whileElementsMounted: autoUpdate,
})

const isVisible = ref(false)
</script>

<template>
  <div class="relative inline-block">
    <div
      ref="reference"
      @mouseenter="isVisible = true"
      @mouseleave="isVisible = false"
      @focus="isVisible = true"
      @blur="isVisible = false"
    >
      <slot />
    </div>
    <transition name="fade">
      <div
        v-if="isVisible"
        ref="floating"
        :style="{ top: `${y}px`, left: `${x}px` }"
        class="absolute z-50 px-2 py-1 text-xs bg-base-900 text-white rounded pointer-events-none whitespace-nowrap"
      >
        {{ text }}
      </div>
    </transition>
  </div>
</template>
