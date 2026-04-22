<script setup lang="ts">
import { computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/useAuthStore'
import AIconButton from '../atoms/AIconButton.vue'
import AIcon from '../atoms/AIcon.vue'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const navItems = computed(() => [
  { name: 'dashboard', label: 'Dashboard', icon: 'lucide:layout-dashboard' },
  { name: 'collection', label: 'Collection', icon: 'lucide:books' },
  { name: 'wishlist', label: 'Wishlist', icon: 'lucide:heart' },
  { name: 'add', label: 'Add', icon: 'lucide:plus' },
  { name: 'notifications', label: 'Notifications', icon: 'lucide:bell' },
])

const isActive = (name: string) => route.name === name
</script>

<template>
  <aside class="fixed inset-y-0 left-0 w-64 border-r border-base-300 bg-base-100 flex flex-col">
    <!-- Logo -->
    <div class="px-6 py-4 border-b border-base-300">
      <h1 class="text-xl font-bold text-primary">Ziggytheque</h1>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto p-4">
      <ul class="space-y-2">
        <li v-for="item in navItems" :key="item.name">
          <RouterLink
            :to="{ name: item.name }"
            :class="[
              'flex items-center gap-3 px-4 py-2 rounded-lg transition-colors',
              isActive(item.name)
                ? 'bg-primary text-primary-content font-medium'
                : 'text-base-content hover:bg-base-200',
            ]"
          >
            <AIcon :name="item.icon" size="md" />
            <span>{{ item.label }}</span>
          </RouterLink>
        </li>
      </ul>
    </nav>

    <!-- Footer -->
    <div class="border-t border-base-300 p-4">
      <button
        type="button"
        class="w-full btn btn-ghost btn-sm justify-start"
        @click="auth.logout()"
      >
        <AIcon name="lucide:log-out" size="md" />
        <span>Logout</span>
      </button>
    </div>
  </aside>
</template>
