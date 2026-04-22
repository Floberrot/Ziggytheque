<script setup lang="ts">
import { ref, watch } from 'vue'

export interface Props {
  modelValue: string
  placeholder?: string
  label?: string
  error?: string
  disabled?: boolean
  minRows?: number
}

const props = withDefaults(defineProps<Props>(), {
  minRows: 3,
})

const textareaRef = ref<HTMLTextAreaElement>()

function updateHeight() {
  if (textareaRef.value) {
    const el = textareaRef.value
    el.style.height = 'auto'
    el.style.height = Math.max(el.scrollHeight, 24 * props.minRows) + 'px'
  }
}

watch(() => props.modelValue, updateHeight, { immediate: true })

defineEmits<{
  'update:modelValue': [value: string]
}>()
</script>

<template>
  <div>
    <label v-if="label" class="form-control">
      <div class="label">
        <span class="label-text">{{ label }}</span>
      </div>
    </label>
    <textarea
      ref="textareaRef"
      :value="modelValue"
      :placeholder="placeholder"
      :disabled="disabled"
      :aria-invalid="!!error"
      :aria-describedby="error ? `error-${label}` : undefined"
      class="textarea textarea-bordered w-full resize-none overflow-hidden"
      :class="{ 'textarea-error': error }"
      @input="
        $emit('update:modelValue', ($event.target as HTMLTextAreaElement).value)
        updateHeight()
      "
    />
    <div v-if="error" :id="`error-${label}`" class="label">
      <span class="label-text-alt text-error">{{ error }}</span>
    </div>
  </div>
</template>
