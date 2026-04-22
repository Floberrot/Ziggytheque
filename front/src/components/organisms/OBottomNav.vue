<script setup lang="ts">
defineProps<{
  activeRoute?: string
}>()
</script>

<template>
  <nav class="btm-nav bg-base-100 border-t border-base-300">
    <RouterLink
      to="/dashboard"
      :class="{ active: activeRoute === 'dashboard' }"
      class="btn-ghost"
    >
      <AIcon name="lucide:home" />
      <span class="btm-nav-label">Home</span>
    </RouterLink>
    <RouterLink
      to="/collection"
      :class="{ active: activeRoute === 'collection' }"
      class="btn-ghost"
    >
      <AIcon name="lucide:library" />
      <span class="btm-nav-label">Collection</span>
    </RouterLink>
    <RouterLink
      to="/add"
      :class="{ active: activeRoute === 'add' }"
      class="btn-ghost"
    >
      <AIcon name="lucide:plus" />
      <span class="btm-nav-label">Add</span>
    </RouterLink>
    <RouterLink
      to="/wishlist"
      :class="{ active: activeRoute === 'wishlist' }"
      class="btn-ghost"
    >
      <AIcon name="lucide:heart" />
      <span class="btm-nav-label">Wishlist</span>
    </RouterLink>
    <RouterLink
      to="/notifications"
      :class="{ active: activeRoute === 'notifications' }"
      class="btn-ghost"
    >
      <AIcon name="lucide:bell" />
      <span class="btm-nav-label">Alerts</span>
    </RouterLink>
  </nav>
</template>
