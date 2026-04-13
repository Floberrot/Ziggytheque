<script setup lang="ts">
import { useThemeStore } from '@/stores/useThemeStore'

const themeStore = useThemeStore()
</script>

<template>
  <div :data-theme="themeStore.theme">
    <RouterView />
  </div>
</template>
