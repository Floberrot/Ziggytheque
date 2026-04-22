<script setup lang="ts">
import { ref } from 'vue'
import { Menu, MenuButton, MenuItems, MenuItem } from '@headlessui/vue'
import { useFloating, offset, flip, shift, autoUpdate } from '@floating-ui/vue'

interface Option {
  id: string
  label: string
  icon?: string
  danger?: boolean
}

interface Props {
  options: Option[]
  icon?: string
}

defineProps<Props>()
defineEmits<{
  select: [id: string]
}>()

const button = ref()
const items = ref()

const { x, y } = useFloating(button, items, {
  placement: 'bottom-end',
  middleware: [offset(8), flip(), shift({ padding: 8 })],
  whileElementsMounted: autoUpdate,
})
</script>

<template>
  <Menu as="div" class="relative inline-block">
    <MenuButton ref="button" class="btn btn-ghost btn-sm btn-circle">
      ⋮
    </MenuButton>
    <MenuItems
      ref="items"
      :style="{ top: `${y}px`, left: `${x}px` }"
      class="absolute z-50 w-48 bg-base-100 rounded-lg shadow-lg border border-base-300 py-1"
    >
      <MenuItem
        v-for="option in options"
        :key="option.id"
        v-slot="{ active }"
        as="template"
      >
        <button
          :class="[
            'w-full px-4 py-2 text-left text-sm transition-colors',
            active ? 'bg-base-200' : '',
            option.danger ? 'text-error' : 'text-base-content',
          ]"
          @click="$emit('select', option.id)"
        >
          {{ option.label }}
        </button>
      </MenuItem>
    </MenuItems>
  </Menu>
</template>
