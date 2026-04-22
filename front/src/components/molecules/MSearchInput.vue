<script setup lang="ts">
import AInput from '../atoms/AInput.vue'
import AIconButton from '../atoms/AIconButton.vue'

interface Props {
  modelValue: string
  placeholder?: string
  loading?: boolean
}

defineProps<Props>()
defineEmits<{
  'update:modelValue': [value: string]
  search: []
}>()
</script>

<template>
  <div class="relative">
    <AInput
      :model-value="modelValue"
      type="search"
      :placeholder="placeholder"
      :disabled="loading"
      @update:model-value="$emit('update:modelValue', $event)"
      @keydown.enter="$emit('search')"
    />
    <div class="absolute right-2 top-1/2 -translate-y-1/2 flex gap-1">
      <AIconButton
        v-if="modelValue"
        icon="lucide:x"
        aria-label="Clear search"
        size="sm"
        @click="$emit('update:modelValue', '')"
      />
      <AIconButton
        icon="lucide:search"
        aria-label="Search"
        size="sm"
        :disabled="loading"
        @click="$emit('search')"
      />
    </div>
  </div>
</template>
