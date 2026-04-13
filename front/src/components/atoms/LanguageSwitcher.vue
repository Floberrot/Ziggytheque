<script setup lang="ts">
import { useI18n } from 'vue-i18n'

const { locale } = useI18n()

function toggle() {
  locale.value = locale.value === 'fr' ? 'en' : 'fr'
  localStorage.setItem('locale', locale.value)
}
</script>

<template>
  <button class="btn btn-ghost btn-xs font-mono" @click="toggle">
    {{ locale.toUpperCase() }}
  </button>
</template>
