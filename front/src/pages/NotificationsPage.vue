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
        <div class="card-body gap-6">
          <div>
            <h2 class="card-title text-lg">{{ t('notifications.settings.title') }}</h2>
            <p class="text-sm text-base-content/60 mt-1">
              {{ t('notifications.settings.description') }}
            </p>
          </div>

          <!-- Channel picker cards -->
          <div class="grid grid-cols-2 gap-3">
            <!-- Email card -->
            <button
              type="button"
              class="relative flex flex-col items-center gap-3 p-5 rounded-2xl border-2 transition-all focus:outline-none"
              :class="channel === 'email'
                ? 'border-primary bg-primary/10 shadow-sm'
                : 'border-base-300 bg-base-200/40 hover:border-base-content/20 hover:bg-base-200/70'"
              @click="channel = 'email'"
            >
              <div
                class="w-14 h-14 rounded-full flex items-center justify-center transition-colors"
                :class="channel === 'email' ? 'bg-primary/20 text-primary' : 'bg-base-300/60 text-base-content/40'"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M1.5 8.67v8.58a3 3 0 003 3h15a3 3 0 003-3V8.67l-8.928 5.493a3 3 0 01-3.144 0L1.5 8.67z"/>
                  <path d="M22.5 6.908V6.75a3 3 0 00-3-3h-15a3 3 0 00-3 3v.158l9.714 5.978a1.5 1.5 0 001.572 0L22.5 6.908z"/>
                </svg>
              </div>
              <div class="text-center space-y-0.5">
                <div class="font-semibold text-sm" :class="channel === 'email' ? 'text-primary' : 'text-base-content'">
                  {{ t('notifications.settings.channelEmail') }}
                </div>
                <div class="text-xs text-base-content/40">adresse@exemple.com</div>
              </div>
              <div
                v-if="channel === 'email'"
                class="absolute top-2.5 right-2.5 w-5 h-5 rounded-full bg-primary flex items-center justify-center"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 text-primary-content" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                </svg>
              </div>
            </button>

            <!-- Discord card -->
            <button
              type="button"
              class="relative flex flex-col items-center gap-3 p-5 rounded-2xl border-2 transition-all focus:outline-none"
              :class="channel === 'discord'
                ? 'border-[#5865F2] bg-[#5865F2]/10 shadow-sm'
                : 'border-base-300 bg-base-200/40 hover:border-base-content/20 hover:bg-base-200/70'"
              @click="channel = 'discord'"
            >
              <div
                class="w-14 h-14 rounded-full flex items-center justify-center transition-colors"
                :class="channel === 'discord' ? 'bg-[#5865F2]/20 text-[#5865F2]' : 'bg-base-300/60 text-base-content/40'"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03z"/>
                </svg>
              </div>
              <div class="text-center space-y-0.5">
                <div class="font-semibold text-sm" :class="channel === 'discord' ? 'text-[#5865F2]' : 'text-base-content'">
                  Discord
                </div>
                <div class="text-xs text-base-content/40">Webhook</div>
              </div>
              <div
                v-if="channel === 'discord'"
                class="absolute top-2.5 right-2.5 w-5 h-5 rounded-full flex items-center justify-center"
                style="background-color: #5865F2"
              >
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 text-white" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                </svg>
              </div>
            </button>
          </div>

          <!-- Email input -->
          <div v-if="channel === 'email'" class="form-control">
            <label class="label py-1">
              <span class="label-text font-medium">{{ t('notifications.settings.email') }}</span>
            </label>
            <label class="input input-bordered flex items-center gap-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-base-content/40 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
              </svg>
              <input v-model="notificationEmail" type="email" class="grow" placeholder="adresse@exemple.com" />
            </label>
          </div>

          <!-- Discord URL input -->
          <div v-if="channel === 'discord'" class="form-control">
            <label class="label py-1">
              <span class="label-text font-medium">{{ t('notifications.settings.discord') }}</span>
            </label>
            <label class="input input-bordered flex items-center gap-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0 text-[#5865F2]" viewBox="0 0 24 24" fill="currentColor">
                <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03z"/>
              </svg>
              <input v-model="discordWebhookUrl" type="url" class="grow" placeholder="https://discord.com/api/webhooks/…" />
            </label>
          </div>

          <div class="divider my-0" />

          <div class="flex gap-3 justify-end">
            <button
              type="button"
              class="btn btn-ghost gap-2"
              :disabled="!canTest || testing || saving"
              @click="sendTest"
            >
              <span v-if="testing" class="loading loading-spinner loading-xs" />
              <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="22" y1="2" x2="11" y2="13"/>
                <polygon points="22 2 15 22 11 13 2 9 22 2"/>
              </svg>
              {{ t('notifications.settings.test') }}
            </button>
            <button
              type="button"
              class="btn btn-primary gap-2"
              :disabled="saving || testing"
              @click="savePreferences"
            >
              <span v-if="saving" class="loading loading-spinner loading-xs" />
              <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
              </svg>
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
