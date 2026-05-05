<script setup lang="ts">
withDefaults(defineProps<{
  showText?: boolean
  size?: 'sm' | 'md' | 'lg'
}>(), {
  showText: false,
  size: 'md',
})
</script>

<template>
  <span
    class="inline-flex items-center font-bold tracking-tight select-none"
    :class="{
      'gap-2 text-lg': size === 'sm',
      'gap-2.5 text-xl': size === 'md',
      'gap-3.5 text-3xl': size === 'lg',
    }"
  >
    <!--
      Book icon — traced from brand assets.
      currentColor = book body + eye pupils (adapts to theme text color).
      var(--color-base-100) = spine + eye whites (adapts to theme background).
    -->
    <svg
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 190 200"
      aria-hidden="true"
      class="shrink-0"
      :class="{
        'h-7 w-auto': size === 'sm',
        'h-9 w-auto': size === 'md',
        'h-14 w-auto': size === 'lg',
      }"
    >
      <!-- Book body -->
      <path
        fill="currentColor"
        d="M95,7 L184,27 L184,162 L166,177 L115,177 L105,188 L95,192 L85,188 L75,177 L24,177 L6,162 L6,27 Z"
      />
      <!-- Spine (page separator) -->
      <rect x="89" y="7" width="12" height="185" fill="var(--color-base-100, white)" />
      <!-- Left eye — outer (background cutout) -->
      <path
        fill="var(--color-base-100, white)"
        d="M19,118 C19,91 83,91 83,118 C83,145 19,145 19,118 Z"
      />
      <!-- Left eye — inner (pupil, book color) -->
      <path
        fill="currentColor"
        d="M32,118 C32,106 70,106 70,118 C70,130 32,130 32,118 Z"
      />
      <!-- Right eye — outer (background cutout) -->
      <path
        fill="var(--color-base-100, white)"
        d="M107,118 C107,91 171,91 171,118 C171,145 107,145 107,118 Z"
      />
      <!-- Right eye — inner (pupil, book color) -->
      <path
        fill="currentColor"
        d="M120,118 C120,106 158,106 158,118 C158,130 120,130 120,118 Z"
      />
    </svg>

    <span v-if="showText">Ziggytheque.</span>
  </span>
</template>
