<script setup lang="ts">
  import { computed } from 'vue'
  import { useI18n } from 'vue-i18n'

  const props = withDefaults(
    defineProps<{
      /** Outer diameter of the spinner. xs fits inside buttons, xl is a full-page loader. */
      size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl'
      /** Optional caption shown beneath the spinner and used as the accessible name. */
      label?: string
    }>(),
    {
      size: 'md',
      label: undefined,
    },
  )

  const { t } = useI18n()

  // xs is sized to line up with DaisyUI's loading-xs inside buttons; lg/xl are for full-page loaders.
  const DIMENSIONS = {
    xs: { box: '1rem', thickness: '2px' },
    sm: { box: '1.75rem', thickness: '2px' },
    md: { box: '2.75rem', thickness: '3px' },
    lg: { box: '4rem', thickness: '3px' },
    xl: { box: '5.5rem', thickness: '4px' },
  } as const

  const accessibleLabel = computed(() => props.label ?? t('common.loading'))

  const styleVars = computed(() => ({
    '--zig-loader-size': DIMENSIONS[props.size].box,
    '--zig-loader-thickness': DIMENSIONS[props.size].thickness,
  }))
</script>

<template>
  <div
    class="zig-loader inline-flex flex-col items-center justify-center gap-3 text-current"
    role="status"
    :aria-label="accessibleLabel"
    :style="styleVars"
  >
    <span class="zig-loader__spinner" aria-hidden="true">
      <span class="zig-loader__track" />
      <span class="zig-loader__comet" />
    </span>

    <span v-if="label" class="text-sm text-base-content/60">{{ label }}</span>
  </div>
</template>

<style scoped>
  .zig-loader__spinner {
    position: relative;
    display: inline-block;
    width: var(--zig-loader-size);
    height: var(--zig-loader-size);
  }

  /*
    The whole loader paints in currentColor, so the caller picks the colour with a text-*
    utility and it stays legible on every light/dark theme — no baked-in colours, no logo.
  */

  /* Faint full ring that stays put, giving the comet something to sweep over. */
  .zig-loader__track {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    border: var(--zig-loader-thickness) solid currentColor;
    opacity: 0.15;
  }

  /* A conic-gradient comet masked into the same ring band, sweeping smoothly on top. */
  .zig-loader__comet {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background: conic-gradient(from 0deg, transparent, currentColor);
    -webkit-mask: radial-gradient(
      farthest-side,
      transparent calc(100% - var(--zig-loader-thickness)),
      #000 calc(100% - var(--zig-loader-thickness))
    );
    mask: radial-gradient(
      farthest-side,
      transparent calc(100% - var(--zig-loader-thickness)),
      #000 calc(100% - var(--zig-loader-thickness))
    );
    will-change: transform;
    animation: zig-loader-spin 0.7s linear infinite;
  }

  @keyframes zig-loader-spin {
    to {
      transform: rotate(1turn);
    }
  }

  /* Respect users who asked for less motion: slow the sweep right down. */
  @media (prefers-reduced-motion: reduce) {
    .zig-loader__comet {
      animation-duration: 2.4s;
    }
  }
</style>
