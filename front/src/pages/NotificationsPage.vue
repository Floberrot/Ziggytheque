<script setup lang="ts">
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query'
import { getNotifications, markNotificationRead } from '@/api/notification'
import { useI18n } from 'vue-i18n'

const qc = useQueryClient()
const { t } = useI18n()

const { data: notifications, isPending } = useQuery({
  queryKey: ['notifications'],
  queryFn: getNotifications,
})

const markReadMutation = useMutation({
  mutationFn: (id: string) => markNotificationRead(id),
  onSuccess: () => qc.invalidateQueries({ queryKey: ['notifications'] }),
})
</script>

<template>
  <div class="p-6 space-y-6">
    <h1 class="text-2xl font-bold">{{ t('notifications.title') }}</h1>

    <div v-if="isPending" class="flex justify-center py-16">
      <span class="loading loading-spinner loading-lg" />
    </div>

    <div v-else-if="!notifications?.length" class="text-center py-16 text-base-content/50">
      {{ t('notifications.empty') }}
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="n in notifications"
        :key="n.id"
        class="alert"
        :class="n.type === 'new_volume' ? 'alert-info' : 'alert-warning'"
      >
        <span>{{ n.message }}</span>
        <button class="btn btn-ghost btn-xs ml-auto" @click="markReadMutation.mutate(n.id)">
          {{ t('notifications.markRead') }}
        </button>
      </div>
    </div>
  </div>
</template>
