<script setup lang="ts">
import { useNotifications, useMarkNotificationRead } from '@/composables/queries/useNotificationQueries'

const { data: notifications, isLoading } = useNotifications()
const { mutate: markRead } = useMarkNotificationRead()
</script>

<template>
  <div class="p-4 lg:p-6">
    <div class="mb-6 flex items-center justify-between">
      <h1 class="heading-xl">Notifications</h1>
      <span class="badge">{{ notifications?.length ?? 0 }}</span>
    </div>

    <div v-if="isLoading" class="space-y-2">
      <ASkeleton v-for="i in 3" :key="i" height="60px" />
    </div>

    <div v-else-if="notifications?.length" class="space-y-2">
      <div
        v-for="notif in notifications"
        :key="notif.id"
        class="card bg-base-200 p-4 flex items-center justify-between"
        :class="{ 'opacity-50': notif.isRead }"
      >
        <p class="flex-1">{{ notif.message }}</p>
        <AButton
          v-if="!notif.isRead"
          size="sm"
          variant="ghost"
          @click="markRead(notif.id)"
        >
          Mark read
        </AButton>
      </div>
    </div>

    <AEmptyState v-else icon="lucide:bell" title="No notifications" />
  </div>
</template>
