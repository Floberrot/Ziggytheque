<script setup lang="ts">
interface Props {
  modelValue: string | number
  type?: 'text' | 'email' | 'password' | 'number' | 'url' | 'search'
  placeholder?: string
  disabled?: boolean
  readonly?: boolean
  error?: string
  label?: string
  hint?: string
}

defineProps<Props>()
defineEmits<{
  'update:modelValue': [value: string | number]
  blur: []
  focus: []
}>()
</script>

<template>
  <div class="form-control w-full">
    <label v-if="label" class="label">
      <span class="label-text">{{ label }}</span>
    </label>
    <input
      :value="modelValue"
      :type="type"
      :placeholder="placeholder"
      :disabled="disabled"
      :readonly="readonly"
      :aria-describedby="error ? `${label}-error` : hint ? `${label}-hint` : undefined"
      :class="[
        'input input-bordered w-full',
        { 'input-error': !!error },
      ]"
      @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      @blur="$emit('blur')"
      @focus="$emit('focus')"
    />
    <label v-if="hint" class="label">
      <span class="label-text-alt text-xs text-base-content/60">{{ hint }}</span>
    </label>
    <label v-if="error" class="label">
      <span :id="`${label}-error`" class="label-text-alt text-xs text-error">
        {{ error }}
      </span>
    </label>
  </div>
</template>
