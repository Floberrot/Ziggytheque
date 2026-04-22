<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useThemeStore } from '@/stores/useThemeStore'
import { useAuthStore } from '@/stores/useAuthStore'
import MBottomSheet from '../molecules/MBottomSheet.vue'
import MSegmentedControl from '../molecules/MSegmentedControl.vue'
import AButton from '../atoms/AButton.vue'

interface Props {
  open: boolean
}

defineProps<Props>()
defineEmits<{ close: [] }>()

const { locale } = useI18n()
const themeStore = useThemeStore()
const auth = useAuthStore()

const themeOptions = [
  { value: 'light', label: '☀️ Light' },
  { value: 'dark', label: '🌙 Dark' },
  { value: 'system', label: '💻 System' },
]

const localeOptions = [
  { value: 'en', label: 'English' },
  { value: 'fr', label: 'Français' },
]
</script>

<template>
  <MBottomSheet :open="open" title="Settings" @close="$emit('close')">
    <div class="space-y-6">
      <!-- Theme -->
      <div>
        <label class="block text-sm font-medium mb-3">Theme</label>
        <MSegmentedControl
          :model-value="themeStore.mode"
          :options="themeOptions"
          @update:model-value="themeStore.setMode"
        />
      </div>

      <!-- Language -->
      <div>
        <label class="block text-sm font-medium mb-3">Language</label>
        <MSegmentedControl
          :model-value="locale"
          :options="localeOptions"
          @update:model-value="(l) => ($i18n.locale = l)"
        />
      </div>

      <!-- Logout -->
      <div class="pt-4 border-t border-base-300">
        <AButton variant="danger" class="w-full" @click="auth.logout()">
          Logout
        </AButton>
      </div>
    </div>
  </MBottomSheet>
</template>
