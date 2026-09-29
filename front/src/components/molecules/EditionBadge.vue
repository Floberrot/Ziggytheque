<script setup lang="ts">
import { Sparkles } from 'lucide-vue-next'

/**
 * How a series' edition reads everywhere: the publisher, then — when there is one —
 * the special edition as a highlighted badge ("Glénat  ✦ Prestige").
 */
withDefaults(
  defineProps<{
    publisher: string | null
    specialEdition: string | null
    /** 'overlay' = white text for covers with a dark gradient */
    tone?: 'default' | 'overlay'
    size?: 'xs' | 'sm'
  }>(),
  { tone: 'default', size: 'sm' },
)
</script>

<template>
  <span v-if="publisher || specialEdition" class="inline-flex items-center gap-1.5 min-w-0 max-w-full">
    <span
      v-if="publisher"
      class="truncate"
      :class="[
        size === 'xs' ? 'text-[10px]' : 'text-xs',
        tone === 'overlay' ? 'text-white/70' : 'text-base-content/60',
      ]"
    >
      {{ publisher }}
    </span>
    <span
      v-if="specialEdition"
      class="inline-flex items-center gap-1 rounded-full font-semibold min-w-0 border"
      :class="[
        size === 'xs' ? 'px-1.5 py-px text-[9px]' : 'px-2 py-0.5 text-[11px]',
        tone === 'overlay'
          ? 'bg-warning/90 text-warning-content border-warning'
          : 'bg-warning/15 text-warning border-warning/30',
      ]"
      :title="specialEdition"
    >
      <Sparkles :class="size === 'xs' ? 'h-2.5 w-2.5' : 'h-3 w-3'" class="shrink-0" />
      <span class="truncate">{{ specialEdition }}</span>
    </span>
  </span>
</template>
