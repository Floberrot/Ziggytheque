<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { getArticles, getNotifications } from '@/api/notification'
import { getCollection } from '@/api/collection'
import { patchNotificationPreferences, postNotificationTest } from '@/api/auth'
import { useAuthStore } from '@/stores/useAuthStore'
import { useUiStore } from '@/stores/useUiStore'
import { useI18n } from 'vue-i18n'
import ArticleCard from '@/components/molecules/ArticleCard.vue'
import type { CollectionEntry } from '@/types'

const { t } = useI18n()
const auth = useAuthStore()
const ui = useUiStore()
const queryClient = useQueryClient()

const page = ref(1)
const limit = 12
const selectedCollectionId = ref<string | undefined>(undefined)

const { data: collection } = useQuery({
  queryKey: ['collection'],
  queryFn: () => getCollection(),
})

const followedEntries = computed<CollectionEntry[]>(
  () => collection.value?.items.filter((e) => e.notificationsEnabled) ?? [],
)

const { data: articlePage, isPending } = useQuery({
  queryKey: computed(() => ['articles', page.value, selectedCollectionId.value]),
  queryFn: () => getArticles({ page: page.value, limit, collectionEntryId: selectedCollectionId.value }),
})

watch(selectedCollectionId, () => { page.value = 1 })

// ── Notification preferences form ──────────────────────────────────────

const channel = ref<'email' | 'discord'>('email')
const notificationEmail = ref<string>('')
const discordWebhookUrl = ref<string>('')
const saving = ref(false)
const testing = ref(false)

function syncFormFromUser(): void {
  if (auth.user === null) return
  channel.value = auth.user.notificationChannel
  notificationEmail.value = auth.user.notificationEmail ?? ''
  discordWebhookUrl.value = auth.user.discordWebhookUrl ?? ''
}

onMounted(syncFormFromUser)
watch(() => auth.user, syncFormFromUser)

const canTest = computed(() => {
  if (channel.value === 'email') return notificationEmail.value.trim() !== ''
  return discordWebhookUrl.value.trim() !== ''
})

async function savePreferences(): Promise<void> {
  saving.value = true
  try {
    await patchNotificationPreferences(
      channel.value,
      channel.value === 'email' ? (notificationEmail.value.trim() || null) : null,
      channel.value === 'discord' ? (discordWebhookUrl.value.trim() || null) : null,
    )
    await auth.loadUser()
    ui.addToast(t('notifications.settings.savedToast'), 'success')
  } catch {
    ui.addToast(t('notifications.settings.saveErrorToast'), 'error')
  } finally {
    saving.value = false
  }
}

async function sendTest(): Promise<void> {
  testing.value = true
  try {
    // Save first so the test reflects what the user just typed.
    await patchNotificationPreferences(
      channel.value,
      channel.value === 'email' ? (notificationEmail.value.trim() || null) : null,
      channel.value === 'discord' ? (discordWebhookUrl.value.trim() || null) : null,
    )
    await auth.loadUser()
    await postNotificationTest()
    ui.addToast(t('notifications.settings.testDispatchedToast'), 'info')
    // Refresh the unread notifications shortly after so any async failure
    // surfaces in the page without the user having to reload.
    setTimeout(() => {
      void queryClient.invalidateQueries({ queryKey: ['notifications', 'unread'] })
    }, 5000)
  } catch {
    ui.addToast(t('notifications.settings.testErrorToast'), 'error')
  } finally {
    testing.value = false
  }
}

// ── Unread async notifications (test failures, etc.) ───────────────────

const { data: unread } = useQuery({
  queryKey: ['notifications', 'unread'],
  queryFn: () => getNotifications(),
  refetchInterval: 15_000,
})

</script>

<template>
  <div class="p-4 sm:p-6 space-y-6">
    <h1 class="text-2xl font-bold">{{ t('notifications.title') }}</h1>

    <div class="max-w-4xl mx-auto px-6 py-8 space-y-8">
      <!-- Settings form -->
      <section class="card bg-base-200/40 border border-base-300">
        <div class="card-body space-y-4">
          <div>
            <h2 class="card-title text-lg">{{ t('notifications.settings.title') }}</h2>
            <p class="text-sm text-base-content/60 mt-1">
              {{ t('notifications.settings.description') }}
            </p>
          </div>

          <div class="form-control">
            <label class="label py-1">
              <span class="label-text font-medium">{{ t('notifications.settings.channel') }}</span>
            </label>
            <select v-model="channel" class="select select-bordered">
              <option value="email">{{ t('notifications.settings.channelEmail') }}</option>
              <option value="discord">{{ t('notifications.settings.channelDiscord') }}</option>
            </select>
          </div>

          <div v-if="channel === 'email'" class="form-control">
            <label class="label py-1">
              <span class="label-text font-medium">{{ t('notifications.settings.email') }}</span>
            </label>
            <input
              v-model="notificationEmail"
              type="email"
              class="input input-bordered"
              placeholder="adresse@exemple.com"
            />
          </div>

          <div v-if="channel === 'discord'" class="form-control">
            <label class="label py-1">
              <span class="label-text font-medium">{{ t('notifications.settings.discord') }}</span>
            </label>
            <input
              v-model="discordWebhookUrl"
              type="url"
              class="input input-bordered"
              placeholder="https://discord.com/api/webhooks/…"
            />
          </div>

          <div class="flex gap-2 justify-end pt-2">
            <button
              type="button"
              class="btn btn-ghost"
              :disabled="!canTest || testing || saving"
              @click="sendTest"
            >
              <span v-if="testing" class="loading loading-spinner loading-xs" />
              {{ t('notifications.settings.test') }}
            </button>
            <button
              type="button"
              class="btn btn-primary"
              :disabled="saving || testing"
              @click="savePreferences"
            >
              <span v-if="saving" class="loading loading-spinner loading-xs" />
              {{ t('notifications.settings.save') }}
            </button>
          </div>
        </div>
      </section>

      <!-- Async test failures / system notifications for this user -->
      <section v-if="unread && unread.length > 0" class="space-y-2">
        <h3 class="font-semibold text-sm uppercase tracking-wide text-base-content/70">
          {{ t('notifications.systemTitle') }}
        </h3>
        <div
          v-for="n in unread"
          :key="n.id"
          class="alert"
          :class="n.type === 'test_failure' ? 'alert-error' : 'alert-info'"
        >
          <span class="text-sm">{{ n.message }}</span>
        </div>
      </section>

      <!-- Filter bar -->
      <div class="flex items-center gap-3 flex-wrap">
        <span class="text-sm text-base-content/60 shrink-0">{{ t('notifications.filterBy') }}</span>
        <button
          class="btn btn-sm"
          :class="selectedCollectionId === undefined ? 'btn-primary' : 'btn-ghost'"
          @click="selectedCollectionId = undefined"
        >
          {{ t('notifications.allMangas') }}
        </button>
        <button
          v-for="entry in followedEntries"
          :key="entry.id"
          class="btn btn-sm gap-1.5"
          :class="selectedCollectionId === entry.id ? 'btn-primary' : 'btn-ghost'"
          @click="selectedCollectionId = entry.id"
        >
          <img
            v-if="entry.manga.coverUrl"
            :src="entry.manga.coverUrl"
            :alt="entry.manga.title"
            class="w-4 h-5 object-cover rounded-sm"
          />
          <span class="truncate max-w-28">{{ entry.manga.title }}</span>
        </button>

        <!-- No followed entries hint -->
        <p v-if="followedEntries.length === 0" class="text-sm text-base-content/40 italic">
          {{ t('notifications.noFollowed') }}
        </p>
      </div>

      <!-- Loading -->
      <div v-if="isPending" class="space-y-3">
        <div v-for="i in 6" :key="i" class="h-28 rounded-xl bg-base-200 animate-pulse" />
      </div>

      <!-- Empty state -->
      <div
        v-else-if="!articlePage?.items?.length"
        class="flex flex-col items-center justify-center py-24 gap-4 text-base-content/40"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-14 w-14 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 12h6m-6-4h4" />
        </svg>
        <p class="text-lg font-medium">{{ t('notifications.empty') }}</p>
      </div>

      <!-- Article list -->
      <div v-else class="space-y-3">
        <ArticleCard
          v-for="article in articlePage.items"
          :key="article.id"
          :article="article"
        />
      </div>

      <!-- Pagination -->
      <div v-if="(articlePage?.totalPages ?? 0) > 1" class="flex justify-center gap-2 mt-8">
        <button class="btn btn-sm btn-ghost" :disabled="page === 1" @click="page--">‹</button>
        <button
          v-for="p in articlePage!.totalPages"
          :key="p"
          class="btn btn-sm"
          :class="p === page ? 'btn-primary' : 'btn-ghost'"
          @click="page = p"
        >
          {{ p }}
        </button>
        <button class="btn btn-sm btn-ghost" :disabled="page === articlePage!.totalPages" @click="page++">›</button>
      </div>
    </div>
  </div>
</template>
