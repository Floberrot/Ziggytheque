<script setup lang="ts">
withDefaults(defineProps<{ removable?: boolean }>(), {
  removable: false,
})

defineEmits<{ remove: [] }>()
</script>

<template>
  <div class="badge badge-lg gap-2">
    <slot />
    <button
      v-if="removable"
      type="button"
      class="btn btn-ghost btn-circle btn-xs"
      @click="$emit('remove')"
    >
      ✕
    </button>
  </div>
</template>
