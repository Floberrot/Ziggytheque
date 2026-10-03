<script setup lang="ts">
  import { computed } from 'vue'
  import { useI18n } from 'vue-i18n'

  /**
   * The app's one loader: every loading state shows this ring, so it looks the same everywhere.
   *
   * - `inline` (default): in a line of text, a field, a toast — and inside BaseButton.
   * - `section`: stands in for a block that is loading (a list, a tab, a modal body).
   * - `page`: stands in for the whole page while its data loads.
   *
   * Section and page loaders fade in after a short delay, so a fast answer never flashes a spinner.
   * It announces itself as a status: the caption when there is one, else a hidden "Loading…".
   */
  type LoaderSize = 'xs' | 'sm' | 'md' | 'lg' | 'xl'
  type LoaderVariant = 'inline' | 'section' | 'page'
  type LoaderTone = 'current' | 'primary' | 'warning'

  const props = withDefaults(
    defineProps<{
      variant?: LoaderVariant
      /** Ring diameter. Defaults to xs inline, lg for a section, xl for a page. */
      size?: LoaderSize
      /** Visible caption, also what assistive technologies announce. */
      label?: string
      /** current = the surrounding text colour. Defaults to current inline, primary otherwise. */
      tone?: LoaderTone
    }>(),
    {
      variant: 'inline',
      size: undefined,
      label: undefined,
      tone: undefined,
    },
  )

  const { t } = useI18n()

  // xs lines up with the h-4 icons of buttons; lg / xl stand in for a section / a page.
  const DIMENSIONS: Record<LoaderSize, { box: string; thickness: string }> = {
    xs: { box: '1rem', thickness: '2px' },
    sm: { box: '1.5rem', thickness: '2px' },
    md: { box: '2rem', thickness: '3px' },
    lg: { box: '3rem', thickness: '3px' },
    xl: { box: '4rem', thickness: '4px' },
  }

  const DEFAULT_SIZE: Record<LoaderVariant, LoaderSize> = { inline: 'xs', section: 'lg', page: 'xl' }

  const TONE_CLASSES: Record<LoaderTone, string> = {
    current: '',
    primary: 'text-primary',
    warning: 'text-warning',
  }

  const resolvedSize = computed<LoaderSize>(() => props.size ?? DEFAULT_SIZE[props.variant])

  const toneClass = computed(
    () => TONE_CLASSES[props.tone ?? (props.variant === 'inline' ? 'current' : 'primary')],
  )

  const styleVars = computed(() => ({
    '--zig-loader-size': DIMENSIONS[resolvedSize.value].box,
    '--zig-loader-thickness': DIMENSIONS[resolvedSize.value].thickness,
  }))
</script>

<template>
  <span class="zig-loader" :class="[`zig-loader--${variant}`, toneClass]" role="status" :style="styleVars">
    <span class="zig-loader__spinner" aria-hidden="true">
      <span class="zig-loader__track" />
      <span class="zig-loader__comet" />
    </span>

    <span v-if="label" class="zig-loader__label">{{ label }}</span>
    <span v-else class="sr-only">{{ t('common.loading') }}</span>
  </span>
</template>

<style scoped>
  /*
    Painted in currentColor only: the caller (or `tone`) picks the colour and the ring stays
    legible on Ziggy Dark and Ziggy Light alike — no baked-in colour, no logo.
  */
  .zig-loader {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    vertical-align: middle;
  }

  .zig-loader--section,
  .zig-loader--page {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 0.75rem;
    width: 100%;
    padding-block: 3rem;
    text-align: center;
    /* Invisible for the first 150 ms: a quick answer never flashes a spinner. */
    animation: zig-loader-reveal 0.3s ease-out 0.15s both;
  }

  .zig-loader--page {
    min-height: 60vh;
  }

  .zig-loader--section .zig-loader__label,
  .zig-loader--page .zig-loader__label {
    font-size: 0.875rem;
    color: color-mix(in oklab, var(--color-base-content) 60%, transparent);
  }

  .zig-loader__spinner {
    position: relative;
    display: inline-block;
    flex-shrink: 0;
    width: var(--zig-loader-size);
    height: var(--zig-loader-size);
  }

  /* Faint full ring that stays put, giving the comet something to sweep over. */
  .zig-loader__track {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    border: var(--zig-loader-thickness) solid currentColor;
    opacity: 0.18;
  }

  /* A conic-gradient comet masked into the same ring band, sweeping smoothly on top. */
  .zig-loader__comet {
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background: conic-gradient(from 0deg, transparent 0% 12%, currentColor 100%);
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
    animation: zig-loader-spin 0.8s linear infinite;
  }

  /* Rounded head of the comet, so the sweep ends softly instead of on a hard seam. */
  .zig-loader__comet::after {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    width: var(--zig-loader-thickness);
    height: var(--zig-loader-thickness);
    margin-left: calc(var(--zig-loader-thickness) / -2);
    border-radius: 50%;
    background: currentColor;
  }

  @keyframes zig-loader-spin {
    to {
      transform: rotate(1turn);
    }
  }

  @keyframes zig-loader-reveal {
    from {
      opacity: 0;
    }
    to {
      opacity: 1;
    }
  }

  /* Less motion asked: the ring turns slowly and the loader shows up at once. */
  @media (prefers-reduced-motion: reduce) {
    .zig-loader__comet {
      animation-duration: 2.4s;
    }

    .zig-loader--section,
    .zig-loader--page {
      animation: none;
    }
  }
</style>
