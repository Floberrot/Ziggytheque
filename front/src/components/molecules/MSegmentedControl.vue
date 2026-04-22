<script setup lang="ts">
import { RadioGroup, RadioGroupOption } from '@headlessui/vue'

export interface Option {
  value: string
  label: string
  icon?: string
}

export interface Props {
  modelValue: string
  options: Option[]
}

defineProps<Props>()

defineEmits<{
  'update:modelValue': [value: string]
}>()
</script>

<template>
  <RadioGroup :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)">
    <div class="join bg-base-200 rounded-lg p-1">
      <RadioGroupOption
        v-for="option in options"
        :key="option.value"
        :value="option.value"
        as="template"
        v-slot="{ checked }"
      >
        <button
          :class="[
            'join-item btn btn-sm gap-1 flex-1',
            checked ? 'btn-active' : 'btn-ghost',
          ]"
        >
          <AIcon v-if="option.icon" :name="option.icon" size="sm" />
          {{ option.label }}
        </button>
      </RadioGroupOption>
    </div>
  </RadioGroup>
</template>
