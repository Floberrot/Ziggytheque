<script setup lang="ts">
/**
 * BaseHeartRating
 *
 * Props:
 *   modelValue  — valeur courante 0-10 (null = non noté)
 *   readonly    — désactive l'interaction (default: false)
 *
 * Emits:
 *   update:modelValue — entier 0-10
 *
 * Internally works in half-steps (0-10).
 * Display: 5 hearts, each split in left half (value n*2-1) and right half (value n*2).
 * Text label: "(x.x/5)" computed from modelValue / 2.
 */

import { ref, computed } from 'vue'
import './BaseHeartRating.css'

const props = withDefaults(defineProps<{
  modelValue: number | null
  readonly?: boolean
  compact?: boolean
}>(), {
  readonly: false,
  compact: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: number]
}>()

const hovered = ref<number | null>(null)

// Displayed value: hovered preview takes priority over stored value
const displayValue = computed<number>(() => hovered.value ?? props.modelValue ?? 0)

// Label text: stored value only (not hovered), e.g. "3.5/5"
const labelText = computed<string>(() => {
  if (props.modelValue === null) return ''
  const rating = props.modelValue / 2
  return `${rating === Math.floor(rating) ? Math.floor(rating) : rating.toFixed(1)}/5`
})

function heartState(heartIndex: number, half: 'left' | 'right'): 'full' | 'half' | 'empty' {
  // heartIndex: 1-5. left half = value (heartIndex*2 - 1), right = heartIndex*2
  const threshold = half === 'left' ? heartIndex * 2 - 1 : heartIndex * 2
  if (displayValue.value >= threshold) return 'full'
  if (half === 'right' && displayValue.value === threshold - 1) return 'half'
  return 'empty'
}

function onHalfEnter(heartIndex: number, half: 'left' | 'right') {
  if (props.readonly) return
  hovered.value = half === 'left' ? heartIndex * 2 - 1 : heartIndex * 2
}

function onLeave() {
  hovered.value = null
}

function onClick(heartIndex: number, half: 'left' | 'right') {
  if (props.readonly) return
  const value = half === 'left' ? heartIndex * 2 - 1 : heartIndex * 2
  emit('update:modelValue', value)
}
</script>

<template>
  <!-- Compact mode: chip with heart + number -->
  <div
    v-if="compact"
    role="group"
    :aria-label="modelValue !== null ? `Note : ${labelText}` : 'Non noté'"
  >
    <!-- Chip badge -->
    <div
      v-if="modelValue !== null"
      class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-rose-500/15 border border-rose-500/30 backdrop-blur-sm"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        class="w-3.5 h-3.5 text-rose-500"
        fill="currentColor"
      >
        <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
      </svg>
      <span class="text-xs font-semibold text-rose-600 tabular-nums">
        {{ (modelValue / 2).toFixed(1).replace('.0', '') }}
      </span>
    </div>
  </div>

  <!-- Full mode: 5 hearts with interactivity -->
  <div
    v-else
    role="group"
    :aria-label="modelValue !== null ? `Note : ${labelText}` : 'Non noté'"
    :class="[
      'inline-flex items-center gap-1',
      readonly ? '' : 'cursor-pointer',
      modelValue === null && !readonly ? 'heart-pulse-container unrated' : 'heart-pulse-container',
    ]"
    @mouseleave="onLeave"
  >
    <!-- 5 hearts -->
    <div
      v-for="i in 5"
      :key="i"
      class="relative w-5 h-5"
    >
      <!-- Left half (= half-point) -->
      <div
        class="absolute inset-0 w-1/2 overflow-hidden z-10"
        @mouseenter="onHalfEnter(i, 'left')"
        @click="onClick(i, 'left')"
      />
      <!-- Right half (= full point) -->
      <div
        class="absolute inset-0 left-1/2 w-1/2 overflow-hidden z-10"
        @mouseenter="onHalfEnter(i, 'right')"
        @click="onClick(i, 'right')"
      />

      <!-- SVG heart: full -->
      <svg
        v-if="heartState(i, 'right') === 'full'"
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        class="w-5 h-5 text-rose-500 transition-transform duration-100"
        :class="!readonly && hovered !== null ? 'scale-110' : ''"
        fill="currentColor"
      >
        <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
      </svg>

      <!-- SVG heart: half -->
      <svg
        v-else-if="heartState(i, 'left') === 'full' && heartState(i, 'right') !== 'full'"
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        class="w-5 h-5 transition-transform duration-100"
        :class="!readonly && hovered !== null ? 'scale-110' : ''"
      >
        <!-- Left half filled (rose), right half outline (muted) -->
        <defs>
          <clipPath :id="`left-${i}`">
            <rect x="0" y="0" width="12" height="24"/>
          </clipPath>
          <clipPath :id="`right-${i}`">
            <rect x="12" y="0" width="12" height="24"/>
          </clipPath>
        </defs>
        <path
          d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"
          fill="currentColor"
          class="text-rose-500"
          :clip-path="`url(#left-${i})`"
        />
        <path
          d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"
          fill="none"
          stroke="currentColor"
          stroke-width="1.5"
          class="text-base-content/20"
          :clip-path="`url(#right-${i})`"
        />
      </svg>

      <!-- SVG heart: empty -->
      <svg
        v-else
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        class="w-5 h-5 text-base-content/20 transition-transform duration-100"
        fill="none"
        stroke="currentColor"
        stroke-width="1.5"
      >
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
      </svg>
    </div>

    <!-- Numeric label when rated -->
    <span
      v-if="modelValue !== null"
      class="text-xs text-base-content/50 font-medium tabular-nums ml-0.5"
    >
      ({{ labelText }})
    </span>

    <!-- CTA when not yet rated and not readonly -->
    <span
      v-else-if="!readonly"
      class="text-xs text-base-content/30 font-medium ml-1 italic"
    >
      {{ $t('rating.notRated') }}
    </span>
  </div>
</template>
