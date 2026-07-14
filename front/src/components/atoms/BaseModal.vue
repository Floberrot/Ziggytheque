<script setup lang="ts">
import { ref, watch, nextTick, onUnmounted } from 'vue'

/**
 * Shared modal shell — single source of truth for every dialog in the app.
 *
 * Behaviour:
 * - Teleported to <body> with a blurred backdrop and a springy transition.
 * - `sheet` variant (default): bottom sheet on mobile (slides from the bottom,
 *   safe-area padding for the iOS home indicator), centered dialog from `sm:`.
 * - `center` variant: always a centered dialog (confirmations, small forms).
 * - Closes on Escape and backdrop click (both opt-out), never taller than the
 *   dynamic viewport, and keeps Tab focus inside while open.
 */
const props = withDefaults(defineProps<{
  open: boolean
  /** 'sheet' = bottom sheet mobile / centered desktop · 'center' = always centered */
  variant?: 'sheet' | 'center'
  /** Desktop max-width utility class applied to the panel */
  maxWidthClass?: string
  /** Extra classes on the panel (fixed heights, paddings…) */
  panelClass?: string
  /** Stacking utility class for the overlay */
  zClass?: string
  closeOnBackdrop?: boolean
  closeOnEscape?: boolean
}>(), {
  variant: 'sheet',
  maxWidthClass: 'sm:max-w-md',
  panelClass: '',
  zClass: 'z-[70]',
  closeOnBackdrop: true,
  closeOnEscape: true,
})

const emit = defineEmits<{ close: [] }>()

const panelRef = ref<HTMLElement | null>(null)

function onBackdropClick(): void {
  if (props.closeOnBackdrop) emit('close')
}

const FOCUSABLE_SELECTOR =
  'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape' && props.closeOnEscape) {
    emit('close')
    return
  }
  if (event.key !== 'Tab' || !panelRef.value) return
  // Minimal focus trap: wrap Tab / Shift+Tab at the panel boundaries.
  const focusables = panelRef.value.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR)
  if (focusables.length === 0) {
    event.preventDefault()
    panelRef.value.focus()
    return
  }
  const first = focusables[0]
  const last = focusables[focusables.length - 1]
  const active = document.activeElement
  if (event.shiftKey && (active === first || active === panelRef.value)) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && active === last) {
    event.preventDefault()
    first.focus()
  }
}

watch(() => props.open, async (open) => {
  if (open) {
    window.addEventListener('keydown', onKeydown)
    await nextTick()
    panelRef.value?.focus()
  } else {
    window.removeEventListener('keydown', onKeydown)
  }
}, { immediate: true })

onUnmounted(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <Transition name="base-modal">
      <div
        v-if="open"
        class="fixed inset-0 flex justify-center"
        :class="[
          zClass,
          variant === 'sheet' ? 'items-end sm:items-center p-0 sm:p-4' : 'items-center p-4',
        ]"
        role="dialog"
        aria-modal="true"
      >
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="onBackdropClick" />

        <div
          ref="panelRef"
          tabindex="-1"
          class="base-modal-panel relative z-10 w-full bg-base-100 shadow-2xl overflow-hidden flex flex-col max-h-[92dvh] outline-none"
          :class="[
            variant === 'sheet'
              ? 'rounded-t-3xl sm:rounded-2xl safe-bottom sm:pb-0'
              : 'rounded-2xl',
            maxWidthClass,
            panelClass,
          ]"
        >
          <slot />
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.base-modal-enter-active,
.base-modal-leave-active {
  transition: opacity 0.2s ease;
}
.base-modal-enter-active .base-modal-panel,
.base-modal-leave-active .base-modal-panel {
  transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.base-modal-enter-from,
.base-modal-leave-to {
  opacity: 0;
}
.base-modal-enter-from .base-modal-panel {
  transform: translateY(40px) scale(0.97);
}
</style>
