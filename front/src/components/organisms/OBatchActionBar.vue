<script setup lang="ts">
import { computed } from 'vue'
import AButton from '../atoms/AButton.vue'

interface Props {
  count: number
  actions?: Array<{
    id: string
    label: string
    variant?: 'primary' | 'danger'
  }>
}

const props = withDefaults(defineProps<Props>(), {
  actions: () => [],
})

defineEmits<{
  action: [id: string]
  clear: []
}>()

const pluralLabel = computed(() => `${props.count} selected`)
</script>

<template>
  <div v-if="count > 0" class="fixed bottom-0 inset-x-0 bg-base-100 border-t border-base-300 p-4">
    <div class="max-w-6xl mx-auto flex items-center justify-between gap-4">
      <span class="text-sm font-medium">{{ pluralLabel }}</span>
      <div class="flex gap-2">
        <AButton variant="ghost" size="sm" @click="$emit('clear')">
          Clear
        </AButton>
        <AButton
          v-for="action in actions"
          :key="action.id"
          :variant="action.variant || 'primary'"
          size="sm"
          @click="$emit('action', action.id)"
        >
          {{ action.label }}
        </AButton>
      </div>
    </div>
  </div>
</template>
