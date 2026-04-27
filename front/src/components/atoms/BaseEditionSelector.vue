<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { FRENCH_EDITIONS } from '@/data/editions'

const props = defineProps<{
  modelValue: string | null
  inputClass?: string
  placeholder?: string
  autofocus?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string | null]
  'confirm': []
  'cancel': []
}>()

const inputValue = ref(props.modelValue ?? '')
const showDropdown = ref(false)

watch(() => props.modelValue, (v) => {
  if ((v ?? '') !== inputValue.value) inputValue.value = v ?? ''
})

const filtered = computed(() => {
  const q = inputValue.value.toLowerCase().trim()
  if (!q) return FRENCH_EDITIONS
  return FRENCH_EDITIONS.filter((e) => e.name.toLowerCase().includes(q))
})

function select(name: string | null) {
  inputValue.value = name ?? ''
  emit('update:modelValue', name)
  showDropdown.value = false
  emit('confirm')
}

function onInput() {
  emit('update:modelValue', inputValue.value || null)
  showDropdown.value = true
}

function onBlur() {
  setTimeout(() => { showDropdown.value = false }, 150)
}
</script>

<template>
  <div class="relative">
    <input
      v-model="inputValue"
      type="text"
      :class="inputClass ?? 'input input-bordered w-full'"
      :placeholder="placeholder ?? 'Pika, Glénat, Kana…'"
      autocomplete="off"
      :autofocus="autofocus"
      @input="onInput"
      @focus="showDropdown = true"
      @blur="onBlur"
      @keydown.enter.prevent="emit('confirm')"
      @keydown.escape="emit('cancel')"
    />
    <ul
      v-if="showDropdown && filtered.length"
      class="absolute top-full left-0 right-0 z-30 mt-0.5 bg-base-100 border border-base-300 rounded-lg shadow-lg max-h-52 overflow-y-auto text-sm min-w-52"
    >
      <li
        v-if="modelValue"
        class="px-3 py-2 cursor-pointer hover:bg-error/10 hover:text-error transition-colors text-xs text-base-content/50 border-b border-base-200"
        @mousedown.prevent="select(null)"
      >
        × Effacer l'édition
      </li>
      <li
        v-for="ed in filtered"
        :key="ed.name"
        class="px-3 py-2.5 cursor-pointer hover:bg-primary hover:text-primary-content transition-colors flex items-center gap-2.5"
        @mousedown.prevent="select(ed.name)"
      >
        <img
          :src="ed.logo"
          :alt="ed.name"
          class="w-5 h-5 rounded object-contain flex-shrink-0"
        />
        {{ ed.name }}
      </li>
    </ul>
  </div>
</template>
