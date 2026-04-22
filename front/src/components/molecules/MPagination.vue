<script setup lang="ts">
import AButton from '../atoms/AButton.vue'

interface Props {
  currentPage: number
  totalPages: number
}

defineProps<Props>()
defineEmits<{
  'update:currentPage': [page: number]
}>()
</script>

<template>
  <div class="flex items-center justify-center gap-2">
    <AButton
      size="sm"
      variant="ghost"
      :disabled="currentPage === 1"
      @click="$emit('update:currentPage', currentPage - 1)"
    >
      ← Prev
    </AButton>
    <div class="flex gap-1">
      <button
        v-for="page in totalPages"
        :key="page"
        type="button"
        :aria-current="currentPage === page ? 'page' : undefined"
        :class="[
          'px-3 py-1 rounded text-sm',
          currentPage === page
            ? 'btn btn-primary'
            : 'btn btn-ghost',
        ]"
        @click="$emit('update:currentPage', page)"
      >
        {{ page }}
      </button>
    </div>
    <AButton
      size="sm"
      variant="ghost"
      :disabled="currentPage === totalPages"
      @click="$emit('update:currentPage', currentPage + 1)"
    >
      Next →
    </AButton>
  </div>
</template>
