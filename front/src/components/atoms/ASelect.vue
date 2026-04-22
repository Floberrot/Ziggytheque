<script setup lang="ts">
interface Option {
  value: string | number
  label: string
}

interface Props {
  modelValue: string | number
  options: Option[]
  placeholder?: string
  disabled?: boolean
  label?: string
  error?: string
}

defineProps<Props>()
defineEmits<{
  'update:modelValue': [value: string | number]
}>()
</script>

<template>
  <div class="form-control w-full">
    <label v-if="label" class="label">
      <span class="label-text">{{ label }}</span>
    </label>
    <select
      :value="modelValue"
      :disabled="disabled"
      :class="[
        'select select-bordered w-full',
        { 'select-error': !!error },
      ]"
      @change="$emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
    >
      <option v-if="placeholder" value="" disabled selected>
        {{ placeholder }}
      </option>
      <option v-for="opt in options" :key="opt.value" :value="opt.value">
        {{ opt.label }}
      </option>
    </select>
    <label v-if="error" class="label">
      <span class="label-text-alt text-xs text-error">{{ error }}</span>
    </label>
  </div>
</template>
