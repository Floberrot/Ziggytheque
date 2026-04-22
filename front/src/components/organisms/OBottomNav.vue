<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AIcon from '../atoms/AIcon.vue'
import AIconButton from '../atoms/AIconButton.vue'

const route = useRoute()

defineEmits<{
  settings: []
}>()

const navItems = computed(() => [
  { name: 'dashboard', icon: 'lucide:layout-dashboard' },
  { name: 'collection', icon: 'lucide:books' },
  { name: 'wishlist', icon: 'lucide:heart' },
  { name: 'add', icon: 'lucide:plus' },
  { name: 'notifications', icon: 'lucide:bell' },
])

const isActive = (name: string) => route.name === name
</script>

<template>
  <nav class="fixed bottom-0 inset-x-0 bg-base-100 border-t border-base-300 flex items-center justify-around py-2">
    <RouterLink
      v-for="item in navItems"
      :key="item.name"
      :to="{ name: item.name }"
      :class="[
        'flex-1 flex justify-center',
        isActive(item.name) ? 'text-primary' : 'text-base-content/60',
      ]"
    >
      <AIcon :name="item.icon" size="lg" />
    </RouterLink>
    <button
      type="button"
      class="flex justify-center flex-1"
      @click="$emit('settings')"
    >
      <AIcon name="lucide:settings" size="lg" class="text-base-content/60" />
    </button>
  </nav>
</template>
