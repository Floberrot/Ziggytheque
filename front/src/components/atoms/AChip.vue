<script setup lang="ts">
export interface Props {
  variant?: 'primary' | 'secondary' | 'success' | 'warning' | 'error'
  icon?: string
  removable?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'primary',
})

defineEmits<{
  remove: []
}>()

const variantClasses: Record<string, string> = {
  primary: 'badge-primary',
  secondary: 'badge-secondary',
  success: 'badge-success',
  warning: 'badge-warning',
  error: 'badge-error',
}
</script>

<template>
  <div :class="['badge badge-lg gap-1', variantClasses[props.variant]]">
    <AIcon v-if="icon" :name="icon" size="xs" />
    <slot />
    <button
      v-if="removable"
      class="btn btn-ghost btn-xs btn-circle"
      aria-label="Remove"
      @click="$emit('remove')"
    >
      <AIcon name="lucide:x" size="xs" />
    </button>
  </div>
</template>
