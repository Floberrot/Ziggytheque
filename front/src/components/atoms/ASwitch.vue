<script setup lang="ts">
import { computed } from 'vue'
import { Switch } from '@headlessui/vue'

interface Props {
  modelValue: boolean
  disabled?: boolean
  label?: string
}

const props = defineProps<Props>()
const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()

const checked = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
})
</script>

<template>
  <div class="flex items-center gap-3">
    <Switch
      v-model="checked"
      :disabled="disabled"
      :class="[
        checked ? 'bg-primary' : 'bg-base-300',
        disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer',
        'relative inline-flex h-6 w-11 flex-shrink-0 rounded-full border-2 border-transparent',
        'transition-colors duration-200 ease-in-out',
        'focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2',
      ]"
    >
      <span
        :class="[
          checked ? 'translate-x-5' : 'translate-x-0',
          'pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow',
          'transform transition-transform duration-200 ease-in-out',
        ]"
      />
    </Switch>
    <label v-if="label" class="text-sm font-medium">{{ label }}</label>
  </div>
</template>
