<script setup lang="ts">
export interface Props {
  count: number
  loading?: boolean
}

defineProps<Props>()

defineEmits<{
  selectAll: []
  clear: []
  delete: []
  action: [action: string]
}>()
</script>

<template>
  <transition
    enter-active-class="translate-y-0 duration-300"
    enter-from-class="translate-y-full"
    leave-active-class="translate-y-full duration-300"
    leave-to-class="translate-y-full"
  >
    <div v-if="count > 0" class="fixed bottom-0 left-0 right-0 bg-primary text-primary-content p-4 shadow-lg z-40">
      <div class="flex items-center justify-between gap-4">
        <div class="text-sm font-medium">
          {{ count }} {{ count === 1 ? 'item' : 'items' }} selected
        </div>
        <div class="flex gap-2">
          <AButton
            size="sm"
            variant="ghost"
            :disabled="loading"
            @click="$emit('clear')"
          >
            Clear
          </AButton>
          <AButton
            size="sm"
            variant="outline"
            :disabled="loading"
            @click="$emit('delete')"
          >
            Delete
          </AButton>
          <slot />
        </div>
      </div>
    </div>
  </transition>
</template>
