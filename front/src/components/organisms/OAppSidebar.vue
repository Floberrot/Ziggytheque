<script setup lang="ts">
import { useRoute } from 'vue-router'

const route = useRoute()
</script>

<template>
  <div class="flex flex-col h-full gap-4 py-4">
    <div class="px-4">
      <h1 class="text-2xl font-bold text-primary">Ziggy</h1>
    </div>

    <nav class="flex-1 space-y-2 px-2">
      <RouterLink
        to="/dashboard"
        :class="{ 'bg-primary text-primary-content': route.name === 'dashboard' }"
        class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-base-300 transition-colors"
      >
        <AIcon name="lucide:home" />
        <span>Dashboard</span>
      </RouterLink>
      <RouterLink
        to="/collection"
        :class="{ 'bg-primary text-primary-content': route.name?.toString().includes('collection') }"
        class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-base-300 transition-colors"
      >
        <AIcon name="lucide:library" />
        <span>Collection</span>
      </RouterLink>
      <RouterLink
        to="/wishlist"
        :class="{ 'bg-primary text-primary-content': route.name === 'wishlist' }"
        class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-base-300 transition-colors"
      >
        <AIcon name="lucide:heart" />
        <span>Wishlist</span>
      </RouterLink>
      <RouterLink
        to="/add"
        :class="{ 'bg-primary text-primary-content': route.name === 'add' }"
        class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-base-300 transition-colors"
      >
        <AIcon name="lucide:plus" />
        <span>Add</span>
      </RouterLink>
      <RouterLink
        to="/notifications"
        :class="{ 'bg-primary text-primary-content': route.name === 'notifications' }"
        class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-base-300 transition-colors"
      >
        <AIcon name="lucide:bell" />
        <span>Notifications</span>
      </RouterLink>
    </nav>

    <div class="border-t border-base-300 pt-4 px-2 space-y-2">
      <button class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-base-300 transition-colors w-full">
        <AIcon name="lucide:settings" />
        <span>Settings</span>
      </button>
      <button class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-base-300 transition-colors w-full text-error">
        <AIcon name="lucide:log-out" />
        <span>Logout</span>
      </button>
    </div>
  </div>
</template>
