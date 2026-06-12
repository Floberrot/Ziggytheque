<script setup lang="ts">
defineProps<{
  country: string | null
  size?: 'sm' | 'md' | 'lg'
}>()

function countryToFlag(code: string): string {
  const OFFSET = 0x1f1e6 - 'A'.charCodeAt(0)
  return code
    .toUpperCase()
    .slice(0, 2)
    .split('')
    .map((char) => String.fromCodePoint(char.charCodeAt(0) + OFFSET))
    .join('')
}
</script>

<template>
  <span
    :class="{
      'text-sm': size === 'sm',
      'text-xl': size === 'lg',
      'text-base': !size || size === 'md',
    }"
    role="img"
    :aria-label="country ?? 'Unknown'"
  >
    {{ country ? countryToFlag(country) : '🌐' }}
  </span>
</template>
