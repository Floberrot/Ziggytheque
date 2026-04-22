<script setup lang="ts">
import { Combobox, ComboboxInput, ComboboxOptions, ComboboxOption } from '@headlessui/vue'
import { ref, computed } from 'vue'

export interface Option {
  id: string
  label: string
  value: any
}

export interface Props {
  options: Option[]
  modelValue?: any
  placeholder?: string
  label?: string
}

const props = defineProps<Props>()
const emit = defineEmits<{
  'update:modelValue': [value: any]
  select: [option: Option]
}>()

const query = ref('')

const filteredOptions = computed(() => {
  if (!query.value) return props.options
  return props.options.filter((opt) =>
    opt.label.toLowerCase().includes(query.value.toLowerCase())
  )
})

function handleSelect(option: Option) {
  emit('update:modelValue', option.value)
  emit('select', option)
  query.value = ''
}
</script>

<template>
  <Combobox :modelValue="modelValue" @update:modelValue="(val) => $emit('update:modelValue', val)">
    <div class="relative">
      <label v-if="label" class="form-control">
        <div class="label">
          <span class="label-text">{{ label }}</span>
        </div>
      </label>
      <ComboboxInput
        :placeholder="placeholder"
        class="input input-bordered w-full"
        @change="query = $event.target.value"
      />
      <ComboboxOptions class="absolute top-full left-0 right-0 bg-base-100 border border-base-300 rounded-md shadow-lg z-50 max-h-60 overflow-y-auto">
        <ComboboxOption
          v-for="option in filteredOptions"
          v-slot="{ active, selected }"
          :key="option.id"
          :value="option.value"
          as="template"
          @click="handleSelect(option)"
        >
          <div
            :class="[
              'px-4 py-2 cursor-pointer',
              active ? 'bg-primary text-primary-content' : 'bg-base-100',
              selected ? 'font-bold' : '',
            ]"
          >
            {{ option.label }}
          </div>
        </ComboboxOption>
      </ComboboxOptions>
    </div>
  </Combobox>
</template>
