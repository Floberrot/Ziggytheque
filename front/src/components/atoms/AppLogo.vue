<script setup lang="ts">
import { computed } from 'vue'
import { useThemeStore } from '@/stores/useThemeStore'

const props = defineProps<{
  /**
   * full=true  → logo avec texte  (sidebar desktop)   → logo-dark.png / logo-light.png
   * full=false → icône seule      (header + nav mobile) → icon-dark.png  / icon-light.png
   */
  full?: boolean
}>()

const themeStore = useThemeStore()

const logoSrc = computed(() => {
  const variant = themeStore.isDark ? 'dark' : 'light'
  return props.full ? `/logo-${variant}.png` : `/icon-${variant}.png`
})
</script>

<template>
  <img
    :src="logoSrc"
    alt="Ziggytheque"
    :class="full ? 'w-full h-auto' : 'h-8 w-auto'"
  />
</template>
