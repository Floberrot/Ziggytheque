<script setup lang="ts">
import { ref } from 'vue'
import AIcon from './AIcon.vue'

interface Props {
  modelValue: number
  max?: number
  readonly?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  max: 5,
})

defineEmits<{
  'update:modelValue': [value: number]
}>()

const hoverRating = ref(0)
</script>

<template>
  <div class="flex gap-1">
    <button
      v-for="i in max"
      :key="i"
      type="button"
      :disabled="readonly"
      :aria-label="`Rate ${i} out of ${max}`"
      @click="!readonly && $emit('update:modelValue', i)"
      @mouseenter="!readonly && (hoverRating = i)"
      @mouseleave="hoverRating = 0"
    >
      <AIcon
        :name="i <= (hoverRating || modelValue) ? 'lucide:heart-fill' : 'lucide:heart'"
        size="md"
        :class="{
          'text-error': i <= (hoverRating || modelValue),
          'text-base-300': i > (hoverRating || modelValue),
        }"
      />
    </button>
  </div>
</template>
