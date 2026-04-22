<script setup lang="ts">
export interface Props {
  modelValue: string | number
  type?: 'text' | 'number' | 'email' | 'password' | 'search' | 'url'
  placeholder?: string
  label?: string
  error?: string
  disabled?: boolean
  readonly?: boolean
}

defineProps<Props>()

defineEmits<{
  'update:modelValue': [value: string | number]
  blur: []
  focus: []
}>()
</script>

<template>
  <div>
    <label v-if="label" class="form-control">
      <div class="label">
        <span class="label-text">{{ label }}</span>
      </div>
    </label>
    <input
      :value="modelValue"
      :type="type"
      :placeholder="placeholder"
      :disabled="disabled"
      :readonly="readonly"
      :aria-invalid="!!error"
      :aria-describedby="error ? `error-${label}` : undefined"
      class="input input-bordered w-full"
      :class="{ 'input-error': error }"
      @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      @blur="$emit('blur')"
      @focus="$emit('focus')"
    />
    <div v-if="error" :id="`error-${label}`" class="label">
      <span class="label-text-alt text-error">{{ error }}</span>
    </div>
  </div>
</template>
