<script setup lang="ts">
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import type { CollectionEntry, QuickActionRequest } from '@/types'
import BaseModal from '@/components/atoms/BaseModal.vue'
import QuickActionsList from '@/components/molecules/QuickActionsList.vue'
import { useIsMobile } from '@/composables/useMediaQuery'

/**
 * The quick actions of a collection card: a menu at the pointer after a right click,
 * a bottom sheet after a long press (or on a phone).
 */
const props = defineProps<{ request: QuickActionRequest | null; busy?: boolean }>()

const emit = defineEmits<{
  close: []
  open: [entry: CollectionEntry]
  toggleFollow: [entry: CollectionEntry]
  rate: [entry: CollectionEntry, rating: number]
  remove: [entry: CollectionEntry]
}>()

const isMobile = useIsMobile()
const asPopover = computed(() => props.request?.source === 'mouse' && !isMobile.value)

/** Gap kept between the menu and the window edges. */
const EDGE_GAP_PX = 8
const popover = ref<HTMLElement | null>(null)
const position = ref({ left: 0, top: 0 })

// Opened where the pointer is, moved back inside the window when it would overflow.
watch(() => props.request, async (request) => {
  if (!request || !asPopover.value) return
  position.value = { left: request.point.x, top: request.point.y }
  await nextTick()
  const element = popover.value
  if (!element) return
  position.value = {
    left: Math.max(EDGE_GAP_PX, Math.min(request.point.x, window.innerWidth - element.offsetWidth - EDGE_GAP_PX)),
    top: Math.max(EDGE_GAP_PX, Math.min(request.point.y, window.innerHeight - element.offsetHeight - EDGE_GAP_PX)),
  }
})

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') emit('close')
}

// The sheet closes on Escape by itself; the menu needs its own listener.
watch([() => props.request, asPopover], ([request, shown]) => {
  window.removeEventListener('keydown', onKeydown)
  if (request && shown) window.addEventListener('keydown', onKeydown)
}, { immediate: true })

onUnmounted(() => window.removeEventListener('keydown', onKeydown))

// Read from the prop at call time: template closures lose the `request` null check.
function onOpen(): void {
  if (props.request) emit('open', props.request.entry)
}

function onToggleFollow(): void {
  if (props.request) emit('toggleFollow', props.request.entry)
}

function onRate(rating: number): void {
  if (props.request) emit('rate', props.request.entry, rating)
}

function onRemove(): void {
  if (props.request) emit('remove', props.request.entry)
}
</script>

<template>
  <Teleport v-if="request && asPopover" to="body">
    <!-- Any click (or right click) outside closes the menu -->
    <div class="fixed inset-0 z-[80]" @click="emit('close')" @contextmenu.prevent="emit('close')" />
    <div
      ref="popover"
      class="fixed z-[81] w-64 rounded-2xl border border-base-300 bg-base-100 p-2 shadow-2xl"
      role="menu"
      :style="{ left: `${position.left}px`, top: `${position.top}px` }"
    >
      <QuickActionsList
        :entry="request.entry"
        :busy="busy"
        @open="onOpen"
        @toggle-follow="onToggleFollow"
        @rate="onRate"
        @remove="onRemove"
      />
    </div>
  </Teleport>

  <BaseModal
    v-else
    :open="request !== null"
    max-width-class="sm:max-w-sm"
    panel-class="p-3"
    z-class="z-[75]"
    @close="emit('close')"
  >
    <QuickActionsList
      v-if="request"
      :entry="request.entry"
      :busy="busy"
      @open="onOpen"
      @toggle-follow="onToggleFollow"
      @rate="onRate"
      @remove="onRemove"
    />
  </BaseModal>
</template>
