<script setup lang="ts">
export interface Option {
  value: string | number
  label: string
}

export interface Props {
  modelValue: string | number
  options: Option[]
  label?: string
  error?: string
  disabled?: boolean
}

defineProps<Props>()

defineEmits<{
  'update:modelValue': [value: string | number]
}>()
</script>

<template>
  <div>
    <label v-if="label" class="form-control">
      <div class="label">
        <span class="label-text">{{ label }}</span>
      </div>
    </label>
    <select
      :value="modelValue"
      :disabled="disabled"
      :aria-invalid="!!error"
      :aria-describedby="error ? `error-${label}` : undefined"
      class="select select-bordered w-full"
      :class="{ 'select-error': error }"
      @change="$emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
    >
      <option disabled value="">Select...</option>
      <option v-for="opt in options" :key="opt.value" :value="opt.value">
        {{ opt.label }}
      </option>
    </select>
    <div v-if="error" :id="`error-${label}`" class="label">
      <span class="label-text-alt text-error">{{ error }}</span>
    </div>
  </div>
</template>
