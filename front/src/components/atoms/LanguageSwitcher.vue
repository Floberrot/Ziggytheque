<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import { SUPPORTED_LOCALES, persistLocale, type AppLocale } from '@/i18n/locale'

/** FR / EN segmented switch: applies at once and is remembered for the next visits. */
const { locale, t } = useI18n()

// Each language is named in its own language, whatever the current one.
const LANGUAGE_NAME_KEYS: Record<AppLocale, string> = {
  fr: 'settings.languageFrench',
  en: 'settings.languageEnglish',
}

function select(next: AppLocale): void {
  locale.value = next
  persistLocale(next)
}
</script>

<template>
  <div class="join" role="group" :aria-label="t('settings.language')">
    <button
      v-for="option in SUPPORTED_LOCALES"
      :key="option"
      type="button"
      class="btn btn-xs join-item font-mono"
      :class="locale === option ? 'btn-primary' : 'btn-ghost'"
      :aria-pressed="locale === option"
      :aria-label="t(LANGUAGE_NAME_KEYS[option])"
      :lang="option"
      @click="select(option)"
    >
      {{ option.toUpperCase() }}
    </button>
  </div>
</template>
