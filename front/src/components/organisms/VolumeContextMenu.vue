<script setup lang="ts">
import { nextTick, onUnmounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { BookOpen, Info, Megaphone, Package, Star } from 'lucide-vue-next'
import type { VolumeEntry, VolumeToggleField } from '@/types'
import type { ScreenPoint } from '@/utils/pointer'

/**
 * The quick actions of a tome after a right click (or the Menu key) on desktop:
 * opened at the pointer, moved back inside the window when it would overflow.
 */
const props = defineProps<{
  request: { volume: VolumeEntry; point: ScreenPoint } | null
  pending: boolean
}>()

const emit = defineEmits<{
  toggle: [field: VolumeToggleField]
  details: []
  close: []
}>()

const { t } = useI18n()

/** Gap kept between the menu and the window edges. */
const EDGE_GAP_PX = 8
const menu = ref<HTMLElement | null>(null)
const position = ref({ left: 0, top: 0 })

// Clamp against the real rendered size instead of magic constants; the first item
// takes the focus, so the keyboard can go on from there.
watch(() => props.request, async (request) => {
  window.removeEventListener('keydown', onKeydown)
  if (!request) return
  window.addEventListener('keydown', onKeydown)
  position.value = { left: request.point.x, top: request.point.y }
  await nextTick()
  const element = menu.value
  if (!element) return
  const rect = element.getBoundingClientRect()
  position.value = {
    left: Math.max(EDGE_GAP_PX, Math.min(request.point.x, window.innerWidth - rect.width - EDGE_GAP_PX)),
    top: Math.max(EDGE_GAP_PX, Math.min(request.point.y, window.innerHeight - rect.height - EDGE_GAP_PX)),
  }
  element.querySelector<HTMLElement>('button:not([disabled])')?.focus()
})

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') emit('close')
}

onUnmounted(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <div v-if="request" class="fixed inset-0 z-[90]" @click="emit('close')">
      <div
        ref="menu"
        class="absolute bg-base-100 rounded-xl shadow-2xl border border-base-300 overflow-hidden w-48 py-1"
        :style="{ top: `${position.top}px`, left: `${position.left}px` }"
        @click.stop
      >
        <div class="px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-base-content/40 border-b border-base-200">
          {{ t('catalogue.tome', { number: request.volume.number }) }}
        </div>
        <ul class="menu menu-xs p-1 gap-0.5" role="menu">
          <!-- Announced (only when not owned) -->
          <li v-if="!request.volume.isOwned" role="none">
            <button
              type="button"
              role="menuitemcheckbox"
              :aria-checked="request.volume.isAnnounced"
              class="gap-2 text-sm"
              :class="[
                { 'pointer-events-none opacity-50': pending },
                request.volume.isAnnounced ? 'text-base-content font-semibold' : '',
              ]"
              :disabled="pending"
              @click="emit('toggle', 'isAnnounced')"
            >
              <Megaphone class="h-4 w-4" :class="request.volume.isAnnounced ? 'text-base-content' : 'text-base-content/50'" />
              {{ t('enrich.statusAnnouncedLabel') }}
              <span v-if="request.volume.isAnnounced" class="ml-auto badge badge-neutral badge-xs" aria-hidden="true">●</span>
            </button>
          </li>
          <!-- Owned (toggles both ways) -->
          <li role="none">
            <button
              type="button"
              role="menuitemcheckbox"
              :aria-checked="request.volume.isOwned"
              class="gap-2 text-sm"
              :class="[
                { 'pointer-events-none opacity-50': pending },
                request.volume.isOwned ? 'text-success font-semibold' : '',
              ]"
              :disabled="pending"
              @click="emit('toggle', 'isOwned')"
            >
              <Package class="h-4 w-4" :class="request.volume.isOwned ? 'text-success' : 'text-base-content/50'" />
              {{ t('enrich.statusOwnedLabel') }}
              <span v-if="request.volume.isOwned" class="ml-auto badge badge-success badge-xs" aria-hidden="true">●</span>
            </button>
          </li>
          <!-- Read (only when owned) -->
          <li v-if="request.volume.isOwned" role="none">
            <button
              type="button"
              role="menuitemcheckbox"
              :aria-checked="request.volume.isRead"
              class="gap-2 text-sm"
              :class="[
                { 'pointer-events-none opacity-50': pending },
                request.volume.isRead ? 'text-info font-semibold' : '',
              ]"
              :disabled="pending"
              @click="emit('toggle', 'isRead')"
            >
              <BookOpen class="h-4 w-4" :class="request.volume.isRead ? 'text-info' : 'text-base-content/50'" />
              {{ t('enrich.statusReadLabel') }}
              <span v-if="request.volume.isRead" class="ml-auto badge badge-info badge-xs" aria-hidden="true">●</span>
            </button>
          </li>
          <!-- Wishlist (only when not owned) -->
          <li v-if="!request.volume.isOwned" role="none">
            <button
              type="button"
              role="menuitemcheckbox"
              :aria-checked="request.volume.isWished"
              class="gap-2 text-sm"
              :class="[
                { 'pointer-events-none opacity-50': pending },
                request.volume.isWished ? 'text-warning font-semibold' : '',
              ]"
              :disabled="pending"
              @click="emit('toggle', 'isWished')"
            >
              <Star
                class="h-4 w-4"
                :class="request.volume.isWished ? 'text-warning' : 'text-base-content/50'"
                :fill="request.volume.isWished ? 'currentColor' : 'none'"
              />
              {{ t('volume.wishlist') }}
              <span v-if="request.volume.isWished" class="ml-auto badge badge-warning badge-xs" aria-hidden="true">●</span>
            </button>
          </li>
          <div class="h-px bg-base-200 my-0.5 mx-2" role="separator" />
          <li role="none">
            <button type="button" role="menuitem" class="gap-2 text-sm" @click="emit('details')">
              <Info class="h-4 w-4" />
              {{ t('manga.details') }}
            </button>
          </li>
        </ul>
      </div>
    </div>
  </Teleport>
</template>
