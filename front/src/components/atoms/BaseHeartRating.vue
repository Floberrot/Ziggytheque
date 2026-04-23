<script setup lang="ts">
import { ref, computed, watch } from 'vue'
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
const isTouchOnly = typeof window !== 'undefined' && window.matchMedia('(hover: none)').matches

// ── Bottom sheet (mobile) ──────────────────────────────────────────────────
const sheetOpen = ref(false)
const sheetValue = ref<number>(props.modelValue ?? 6)

watch(() => props.modelValue, (val) => {
  sheetValue.value = val ?? 6
})

const sheetDisplayValue = computed(() => {
  const v = sheetValue.value / 2
  return v === Math.floor(v) ? String(Math.floor(v)) : v.toFixed(1)
})

function openSheet() {
  sheetValue.value = props.modelValue ?? 6
  sheetOpen.value = true
}

function closeSheet() {
  sheetOpen.value = false
}

function confirmSheet() {
  emit('update:modelValue', sheetValue.value)
  closeSheet()
}

// ── Desktop hearts ─────────────────────────────────────────────────────────
const displayValue = computed<number>(() => hovered.value ?? props.modelValue ?? 0)

const labelText = computed<string>(() => {
  if (props.modelValue === null) return ''
  const rating = props.modelValue / 2
  return `${rating === Math.floor(rating) ? Math.floor(rating) : rating.toFixed(1)}/5`
})

function heartState(heartIndex: number, half: 'left' | 'right'): 'full' | 'half' | 'empty' {
  const threshold = half === 'left' ? heartIndex * 2 - 1 : heartIndex * 2
  if (displayValue.value >= threshold) return 'full'
  if (half === 'right' && displayValue.value === threshold - 1) return 'half'
  return 'empty'
}

function onHalfEnter(heartIndex: number, half: 'left' | 'right') {
  if (props.readonly || isTouchOnly) return
  hovered.value = half === 'left' ? heartIndex * 2 - 1 : heartIndex * 2
}

function onLeave() {
  hovered.value = null
}

function onClick(heartIndex: number, half: 'left' | 'right') {
  if (props.readonly) return
  if (isTouchOnly) {
    openSheet()
    return
  }
  const value = half === 'left' ? heartIndex * 2 - 1 : heartIndex * 2
  emit('update:modelValue', value)
}
</script>

<template>
  <!-- ── Compact mode: chip with heart + number ───────────────────────── -->
  <div
    v-if="compact"
    role="group"
    :aria-label="modelValue !== null ? `Note : ${labelText}` : 'Non noté'"
  >
    <div
      v-if="modelValue !== null"
      class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-rose-500/15 border border-rose-500/30 backdrop-blur-sm"
    >
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-3.5 h-3.5 text-rose-500" fill="currentColor">
        <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
      </svg>
      <span class="text-xs font-semibold text-rose-600 tabular-nums">
        {{ (modelValue / 2).toFixed(1).replace('.0', '') }}
      </span>
    </div>
  </div>

  <!-- ── Full mode: 5 hearts ──────────────────────────────────────────── -->
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
    <div v-for="i in 5" :key="i" class="relative w-5 h-5">
      <div class="absolute inset-0 w-1/2 overflow-hidden z-10" @mouseenter="onHalfEnter(i, 'left')" @click="onClick(i, 'left')" />
      <div class="absolute inset-0 left-1/2 w-1/2 overflow-hidden z-10" @mouseenter="onHalfEnter(i, 'right')" @click="onClick(i, 'right')" />

      <svg v-if="heartState(i, 'right') === 'full'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-5 h-5 text-rose-500 transition-transform duration-100" :class="!readonly && hovered !== null ? 'scale-110' : ''" fill="currentColor">
        <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
      </svg>

      <svg v-else-if="heartState(i, 'left') === 'full' && heartState(i, 'right') !== 'full'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-5 h-5 transition-transform duration-100" :class="!readonly && hovered !== null ? 'scale-110' : ''">
        <defs>
          <clipPath :id="`left-${i}`"><rect x="0" y="0" width="12" height="24"/></clipPath>
          <clipPath :id="`right-${i}`"><rect x="12" y="0" width="12" height="24"/></clipPath>
        </defs>
        <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" fill="currentColor" class="text-rose-500" :clip-path="`url(#left-${i})`"/>
        <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" fill="none" stroke="currentColor" stroke-width="1.5" class="text-base-content/20" :clip-path="`url(#right-${i})`"/>
      </svg>

      <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-5 h-5 text-base-content/20 transition-transform duration-100" fill="none" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
      </svg>
    </div>

    <span v-if="modelValue !== null" class="text-xs text-base-content/50 font-medium tabular-nums ml-0.5">({{ labelText }})</span>
    <span v-else-if="!readonly" class="text-xs text-base-content/30 font-medium ml-1 italic">{{ $t('rating.notRated') }}</span>
  </div>

  <!-- ── Mobile bottom sheet ──────────────────────────────────────────── -->
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-200"
      leave-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
    >
      <div v-if="sheetOpen" class="fixed inset-0 z-50 flex flex-col justify-end">
        <div class="absolute inset-0 bg-black/40" @click="closeSheet" />

        <Transition
          enter-active-class="transition-transform duration-300 ease-out"
          leave-active-class="transition-transform duration-300 ease-in"
          enter-from-class="translate-y-full"
          leave-to-class="translate-y-full"
        >
          <div v-if="sheetOpen" class="relative bg-base-100 rounded-t-2xl shadow-xl pb-10">
            <!-- Handle -->
            <div class="w-10 h-1 bg-base-300 rounded-full mx-auto mt-3 mb-2" />

            <div class="px-6 py-4 text-center">
              <p class="text-xs font-semibold text-base-content/40 uppercase tracking-widest mb-4">{{ $t('rating.title') }}</p>

              <!-- Big value display -->
              <div class="flex items-end justify-center gap-1 mb-1">
                <span class="text-7xl font-bold tabular-nums leading-none" :class="sheetValue > 0 ? 'text-rose-500' : 'text-base-content/20'">
                  {{ sheetDisplayValue }}
                </span>
                <span class="text-xl text-base-content/30 mb-2">/ 5</span>
              </div>

              <!-- Mini hearts preview -->
              <div class="flex justify-center gap-1 mt-3 mb-6">
                <svg
                  v-for="i in 5"
                  :key="i"
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24"
                  class="w-6 h-6 transition-colors duration-100"
                  :class="sheetValue >= i * 2 ? 'text-rose-500' : sheetValue >= i * 2 - 1 ? 'text-rose-300' : 'text-base-content/15'"
                  fill="currentColor"
                >
                  <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                </svg>
              </div>

              <!-- Slider -->
              <input
                type="range"
                min="1"
                max="10"
                step="1"
                :value="sheetValue"
                class="range range-primary w-full"
                @input="sheetValue = Number(($event.target as HTMLInputElement).value)"
              />
              <div class="flex justify-between text-xs text-base-content/30 mt-1 px-0.5">
                <span>0.5</span>
                <span>5</span>
              </div>
            </div>

            <div class="px-6 mt-2">
              <button class="btn btn-primary w-full" @click="confirmSheet">
                {{ $t('rating.confirm') }}
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
