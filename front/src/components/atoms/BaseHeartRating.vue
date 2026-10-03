<script setup lang="ts">
import { ref, computed, watch, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { Heart } from 'lucide-vue-next'
import './BaseHeartRating.css'

/**
 * A rating out of 5 hearts, by half heart (stored out of 10).
 *
 * Keyboard: the ten half hearts form a radio group — Tab reaches the current value,
 * the arrow keys (Home / End) move along it and preview the rating, Enter or Space
 * saves the focused one. Saving only on Enter keeps a stroll along the hearts from
 * firing one request per key.
 */
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

const { t } = useI18n()

const MIN_VALUE = 1
const MAX_VALUE = 10

const hovered = ref<number | null>(null)
const isTouchOnly = typeof window !== 'undefined'
  && typeof window.matchMedia === 'function'
  && window.matchMedia('(hover: none)').matches

function formatHearts(value: number): string {
  const hearts = value / 2
  return hearts === Math.floor(hearts) ? String(Math.floor(hearts)) : hearts.toFixed(1)
}

/** "Note : 3.5/5" for screen readers, "Non noté" without a rating. */
const ratingLabel = computed(() =>
  props.modelValue !== null
    ? t('rating.valueLabel', { value: formatHearts(props.modelValue) })
    : t('rating.unrated'),
)

// ── Bottom sheet (mobile) ──────────────────────────────────────────────────
const sheetOpen = ref(false)
const sheetValue = ref<number>(props.modelValue ?? 6)

watch(() => props.modelValue, (val) => {
  sheetValue.value = val ?? 6
})

const sheetDisplayValue = computed(() => formatHearts(sheetValue.value))

function onSheetKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') closeSheet()
}

function openSheet() {
  sheetValue.value = props.modelValue ?? 6
  sheetOpen.value = true
  window.addEventListener('keydown', onSheetKeydown)
}

function closeSheet() {
  sheetOpen.value = false
  window.removeEventListener('keydown', onSheetKeydown)
}

onUnmounted(() => window.removeEventListener('keydown', onSheetKeydown))

function confirmSheet() {
  emit('update:modelValue', sheetValue.value)
  closeSheet()
}

// ── Desktop hearts ─────────────────────────────────────────────────────────
const displayValue = computed<number>(() => hovered.value ?? props.modelValue ?? 0)

const labelText = computed<string>(() =>
  props.modelValue === null ? '' : `${formatHearts(props.modelValue)}/5`,
)

function halfValue(heartIndex: number, half: 'left' | 'right'): number {
  return half === 'left' ? heartIndex * 2 - 1 : heartIndex * 2
}

function heartState(heartIndex: number, half: 'left' | 'right'): 'full' | 'half' | 'empty' {
  const threshold = halfValue(heartIndex, half)
  if (displayValue.value >= threshold) return 'full'
  if (half === 'right' && displayValue.value === threshold - 1) return 'half'
  return 'empty'
}

function onHalfEnter(heartIndex: number, half: 'left' | 'right') {
  if (props.readonly || isTouchOnly) return
  hovered.value = halfValue(heartIndex, half)
}

function onLeave() {
  hovered.value = null
}

function select(value: number) {
  if (props.readonly) return
  if (isTouchOnly) {
    openSheet()
    return
  }
  emit('update:modelValue', value)
}

// ── Keyboard: a roving radio group over the ten half hearts ─────────────────
const group = ref<HTMLElement | null>(null)

/** The half heart Tab lands on: the current rating, else the first one. */
const tabStop = computed(() => props.modelValue ?? MIN_VALUE)

function focusValue(value: number): void {
  const next = Math.min(MAX_VALUE, Math.max(MIN_VALUE, value))
  hovered.value = next
  group.value?.querySelector<HTMLElement>(`[data-rating-value="${next}"]`)?.focus()
}

function onKeydown(event: KeyboardEvent, value: number): void {
  switch (event.key) {
    case 'ArrowRight':
    case 'ArrowUp':
      focusValue(value + 1)
      break
    case 'ArrowLeft':
    case 'ArrowDown':
      focusValue(value - 1)
      break
    case 'Home':
      focusValue(MIN_VALUE)
      break
    case 'End':
      focusValue(MAX_VALUE)
      break
    case 'Enter':
    case ' ':
      select(value)
      break
    default:
      return
  }
  event.preventDefault()
}

/** Leaving the hearts with the keyboard drops the preview. */
function onFocusOut(event: FocusEvent): void {
  const next = event.relatedTarget
  if (!(next instanceof Node) || !group.value?.contains(next)) hovered.value = null
}
</script>

<template>
  <!-- ── Compact mode: chip with heart + number ───────────────────────── -->
  <div
    v-if="compact"
    role="img"
    :aria-label="ratingLabel"
  >
    <div
      v-if="modelValue !== null"
      class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-rose-500/15 border border-rose-500/30 backdrop-blur-sm"
    >
      <Heart class="w-3.5 h-3.5 text-rose-500" fill="currentColor" />
      <span class="text-xs font-semibold text-rose-600 tabular-nums">
        {{ (modelValue / 2).toFixed(1).replace('.0', '') }}
      </span>
    </div>
  </div>

  <!-- ── Full mode: 5 hearts ──────────────────────────────────────────── -->
  <div
    v-else
    ref="group"
    :role="readonly ? 'img' : 'radiogroup'"
    :aria-label="readonly ? ratingLabel : t('rating.title')"
    :class="[
      'inline-flex items-center gap-1',
      readonly ? '' : 'cursor-pointer',
      modelValue === null && !readonly ? 'heart-pulse-container unrated' : 'heart-pulse-container',
    ]"
    @mouseleave="onLeave"
    @focusout="onFocusOut"
  >
    <div v-for="i in 5" :key="i" class="relative w-5 h-5">
      <template v-for="half in (['left', 'right'] as const)" :key="half">
        <div
          v-if="readonly"
          class="absolute inset-0 w-1/2 overflow-hidden z-10"
          :class="half === 'right' ? 'left-1/2' : ''"
        />
        <div
          v-else
          class="absolute inset-0 w-1/2 overflow-hidden z-10 rounded-sm focus-visible:outline-2 focus-visible:outline-primary"
          :class="half === 'right' ? 'left-1/2' : ''"
          role="radio"
          :data-rating-value="halfValue(i, half)"
          :aria-checked="modelValue === halfValue(i, half)"
          :aria-label="t('rating.option', { value: formatHearts(halfValue(i, half)) })"
          :tabindex="halfValue(i, half) === tabStop ? 0 : -1"
          @mouseenter="onHalfEnter(i, half)"
          @click="select(halfValue(i, half))"
          @keydown="onKeydown($event, halfValue(i, half))"
        />
      </template>

      <Heart v-if="heartState(i, 'right') === 'full'" class="w-5 h-5 text-rose-500 transition-transform duration-100" :class="!readonly && hovered !== null ? 'scale-110' : ''" fill="currentColor" aria-hidden="true" />

      <svg v-else-if="heartState(i, 'left') === 'full' && heartState(i, 'right') !== 'full'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="w-5 h-5 transition-transform duration-100" :class="!readonly && hovered !== null ? 'scale-110' : ''" aria-hidden="true">
        <defs>
          <clipPath :id="`left-${i}`"><rect x="0" y="0" width="12" height="24"/></clipPath>
          <clipPath :id="`right-${i}`"><rect x="12" y="0" width="12" height="24"/></clipPath>
        </defs>
        <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" fill="currentColor" class="text-rose-500" :clip-path="`url(#left-${i})`"/>
        <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" fill="none" stroke="currentColor" stroke-width="1.5" class="text-base-content/20" :clip-path="`url(#right-${i})`"/>
      </svg>

      <Heart v-else class="w-5 h-5 text-base-content/20 transition-transform duration-100" :class="!readonly && hovered !== null ? 'scale-110' : ''" aria-hidden="true" />
    </div>

    <span v-if="modelValue !== null" class="text-xs text-base-content/50 font-medium tabular-nums ml-0.5" aria-hidden="true">({{ labelText }})</span>
    <span v-else-if="!readonly" class="text-xs text-base-content/30 font-medium ml-1 italic">{{ t('rating.notRated') }}</span>
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
          <div
            v-if="sheetOpen"
            class="relative bg-base-100 rounded-t-2xl shadow-xl pb-safe-sheet"
            role="dialog"
            aria-modal="true"
            aria-labelledby="heart-rating-sheet-title"
          >
            <!-- Handle -->
            <div class="w-10 h-1 bg-base-300 rounded-full mx-auto mt-3 mb-2" />

            <div class="px-6 py-4 text-center">
              <p id="heart-rating-sheet-title" class="text-xs font-semibold text-base-content/40 uppercase tracking-widest mb-4">{{ t('rating.title') }}</p>

              <!-- Big value display -->
              <div class="flex items-end justify-center gap-1 mb-1" aria-hidden="true">
                <span class="text-7xl font-bold tabular-nums leading-none" :class="sheetValue > 0 ? 'text-rose-500' : 'text-base-content/20'">
                  {{ sheetDisplayValue }}
                </span>
                <span class="text-xl text-base-content/30 mb-2">/ 5</span>
              </div>

              <!-- Mini hearts preview -->
              <div class="flex justify-center gap-1 mt-3 mb-6" aria-hidden="true">
                <Heart
                  v-for="i in 5"
                  :key="i"
                  class="w-6 h-6 transition-colors duration-100"
                  :class="sheetValue >= i * 2 ? 'text-rose-500' : sheetValue >= i * 2 - 1 ? 'text-rose-300' : 'text-base-content/15'"
                  fill="currentColor"
                />
              </div>

              <!-- Slider -->
              <input
                type="range"
                min="1"
                max="10"
                step="1"
                :value="sheetValue"
                class="range range-primary w-full"
                :aria-label="t('rating.title')"
                :aria-valuetext="t('rating.option', { value: sheetDisplayValue })"
                @input="sheetValue = Number(($event.target as HTMLInputElement).value)"
              />
              <div class="flex justify-between text-xs text-base-content/30 mt-1 px-0.5" aria-hidden="true">
                <span>0.5</span>
                <span>5</span>
              </div>
            </div>

            <div class="px-6 mt-2">
              <button class="btn btn-primary w-full" @click="confirmSheet">
                {{ t('rating.confirm') }}
              </button>
            </div>
          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>
