<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppLayout from '@/layouts/AppLayout.vue'
import AuthLayout from '@/layouts/AuthLayout.vue'

const route = useRoute()

const currentLayout = computed(() => {
  const layout = route.meta.layout as string | undefined
  return layout === 'auth' ? AuthLayout : layout === 'app' ? AppLayout : AuthLayout
})
</script>

<template>
  <div>
    <component :is="currentLayout">
      <template #sidebar>
        <nav class="space-y-2">
          <RouterLink to="/dashboard" class="block px-3 py-2 rounded hover:bg-base-300">
            Dashboard
          </RouterLink>
          <RouterLink to="/collection" class="block px-3 py-2 rounded hover:bg-base-300">
            Collection
          </RouterLink>
          <RouterLink to="/wishlist" class="block px-3 py-2 rounded hover:bg-base-300">
            Wishlist
          </RouterLink>
          <RouterLink to="/add" class="block px-3 py-2 rounded hover:bg-base-300">
            Add
          </RouterLink>
          <RouterLink to="/notifications" class="block px-3 py-2 rounded hover:bg-base-300">
            Notifications
          </RouterLink>
        </nav>
      </template>

      <template #bottom-nav>
        <div class="flex justify-around p-2">
          <RouterLink to="/dashboard" class="btn btn-ghost btn-sm">
            <AIcon name="lucide:home" />
          </RouterLink>
          <RouterLink to="/collection" class="btn btn-ghost btn-sm">
            <AIcon name="lucide:library" />
          </RouterLink>
          <RouterLink to="/wishlist" class="btn btn-ghost btn-sm">
            <AIcon name="lucide:heart" />
          </RouterLink>
          <RouterLink to="/add" class="btn btn-ghost btn-sm">
            <AIcon name="lucide:plus" />
          </RouterLink>
          <RouterLink to="/notifications" class="btn btn-ghost btn-sm">
            <AIcon name="lucide:bell" />
          </RouterLink>
        </div>
      </template>

      <template #modals>
        <!-- App-level modals go here -->
      </template>
    </component>
  </div>
</template>
