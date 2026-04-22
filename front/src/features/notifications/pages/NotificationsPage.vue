<script setup lang="ts">
import { useNotifications } from '@/composables/queries/useNotificationQueries'

const { data: notifications, isLoading } = useNotifications()
</script>

<template>
  <div class="p-4 lg:p-6">
    <h1 class="heading-xl mb-6">Notifications</h1>
    <div v-if="isLoading" class="space-y-2">
      <ASkeleton height="60px" />
      <ASkeleton height="60px" />
      <ASkeleton height="60px" />
    </div>
    <div v-else-if="notifications && notifications.length" class="space-y-2">
      <div v-for="notif in notifications" :key="notif.id" class="card bg-base-200 p-4">
        <p>{{ notif.message }}</p>
      </div>
    </div>
    <AEmptyState v-else icon="lucide:bell" title="No notifications" />
  </div>
</template>
