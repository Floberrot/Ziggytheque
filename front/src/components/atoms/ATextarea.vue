<script setup lang="ts">
import { ref, watch } from 'vue'

interface Props {
  modelValue: string
  placeholder?: string
  disabled?: boolean
  rows?: number
  error?: string
  label?: string
}

const props = withDefaults(defineProps<Props>(), {
  rows: 3,
})

defineEmits<{
  'update:modelValue': [value: string]
}>()

const textarea = ref<HTMLTextAreaElement>()

function autoGrow() {
  if (!textarea.value) return
  textarea.value.style.height = 'auto'
  textarea.value.style.height = `${textarea.value.scrollHeight}px`
}

watch(() => props.modelValue, autoGrow, { immediate: true })
</script>

<template>
  <div class="form-control w-full">
    <label v-if="label" class="label">
      <span class="label-text">{{ label }}</span>
    </label>
    <textarea
      ref="textarea"
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :rows="rows"
      :class="[
        'textarea textarea-bordered w-full resize-none',
        { 'textarea-error': !!error },
      ]"
      @input="$emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)"
    />
    <label v-if="error" class="label">
      <span class="label-text-alt text-xs text-error">{{ error }}</span>
    </label>
  </div>
</template>
