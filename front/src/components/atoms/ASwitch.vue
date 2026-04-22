<script setup lang="ts">
export interface Props {
  modelValue: boolean
  disabled?: boolean
}

defineProps<Props>()

defineEmits<{
  'update:modelValue': [value: boolean]
}>()
</script>

<template>
  <input
    type="checkbox"
    class="toggle"
    :checked="modelValue"
    :disabled="disabled"
    @change="$emit('update:modelValue', ($event.target as HTMLInputElement).checked)"
  />
</template>
