<script setup lang="ts">
import AIcon from './AIcon.vue'

interface Props {
  icon?: string
  indicator?: 'success' | 'warning' | 'error' | 'info'
  count?: number
}

defineProps<Props>()
</script>

<template>
  <div class="flex items-center gap-2 px-3 py-1 rounded-full bg-base-200">
    <AIcon
      v-if="indicator"
      :name="
        indicator === 'success'
          ? 'lucide:check-circle-2'
          : indicator === 'error'
            ? 'lucide:alert-circle'
            : indicator === 'warning'
              ? 'lucide:triangle-alert'
              : 'lucide:info'
      "
      size="sm"
      :class="{
        'text-success': indicator === 'success',
        'text-error': indicator === 'error',
        'text-warning': indicator === 'warning',
        'text-info': indicator === 'info',
      }"
    />
    <AIcon v-else-if="icon" :name="icon" size="sm" />
    <span class="text-sm font-medium">
      <slot />
      <span v-if="count !== undefined" class="ml-1">{{ count }}</span>
    </span>
  </div>
</template>
