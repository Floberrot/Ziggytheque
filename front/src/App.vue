<script setup lang="ts">
import { useRoute } from 'vue-router'
import { useThemeStore } from '@/stores/useThemeStore'

const route = useRoute()
const themeStore = useThemeStore()
</script>

<template>
  <RouterView />
</template>
