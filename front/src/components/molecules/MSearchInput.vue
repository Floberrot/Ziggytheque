<script setup lang="ts">
export interface Props {
  modelValue: string
  placeholder?: string
  loading?: boolean
  icon?: string
  clearable?: boolean
}

defineProps<Props>()

defineEmits<{
  'update:modelValue': [value: string]
  clear: []
}>()
</script>

<template>
  <div class="join w-full">
    <div class="join-item flex flex-1 items-center input-bordered border">
      <AIcon v-if="icon" :name="icon" class="ml-3" />
      <input
        :value="modelValue"
        :placeholder="placeholder"
        type="text"
        class="input flex-1 border-none focus:outline-none"
        @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      />
      <button
        v-if="clearable && modelValue"
        class="btn btn-ghost btn-xs mr-1"
        @click="$emit('clear')"
      >
        <AIcon name="lucide:x" size="sm" />
      </button>
      <ASpinner v-if="loading" size="sm" class="mr-3" />
    </div>
  </div>
</template>
