<script setup lang="ts">
import { computed } from 'vue'
import { RadioGroup, RadioGroupOption } from '@headlessui/vue'

interface Option {
  value: string
  label: string
}

interface Props {
  modelValue: string
  options: Option[]
}

defineProps<Props>()
emit = defineEmits<{
  'update:modelValue': [value: string]
}>()
</script>

<template>
  <RadioGroup :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)">
    <div class="inline-flex gap-1 rounded-lg bg-base-200 p-1">
      <RadioGroupOption
        v-for="option in options"
        :key="option.value"
        v-slot="{ checked }"
        :value="option.value"
        as="template"
      >
        <button
          :class="[
            'px-4 py-2 rounded-md text-sm font-medium transition-all',
            checked
              ? 'bg-base-100 text-base-content shadow-sm'
              : 'text-base-content/60 hover:text-base-content',
          ]"
        >
          {{ option.label }}
        </button>
      </RadioGroupOption>
    </div>
  </RadioGroup>
</template>
