<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useQuery, useQueryClient } from '@tanstack/vue-query'
import {
  approveUser,
  deleteUser,
  generateResetLink,
  getUsers,
  patchUser,
  type UpdateUserPayload,
} from '@/api/admin'
import type { User } from '@/api/auth'
import { useUiStore } from '@/stores/useUiStore'
import { X } from 'lucide-vue-next'
import { useI18n } from 'vue-i18n'
import BaseButton from '@/components/atoms/BaseButton.vue'
import BaseLoader from '@/components/atoms/BaseLoader.vue'
import BaseModal from '@/components/atoms/BaseModal.vue'

const { t } = useI18n()
const ui = useUiStore()
const queryClient = useQueryClient()

function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => word[0]!.toUpperCase())
    .join('')
}

const search = ref('')
const statusFilter = ref<string>('')
const page = ref(1)
const limit = 20

watch([search, statusFilter], () => { page.value = 1 })

const { data, isPending } = useQuery({
  queryKey: computed(() => ['admin', 'users', search.value, statusFilter.value, page.value]),
  queryFn: () =>
    getUsers({
      search: search.value,
      status: statusFilter.value === '' ? undefined : statusFilter.value,
      page: page.value,
      limit,
    }),
})

const totalPages = computed(() => Math.max(1, Math.ceil((data.value?.total ?? 0) / limit)))

// i18n key of each account status — the filter, the badges and the edit form share them.
const STATUS_LABEL_KEYS: Record<User['status'], string> = {
  pending_email_verification: 'admin.status.pending_email_verification',
  pending_admin_approval: 'admin.status.pending_admin_approval',
  active: 'admin.status.active',
  disabled: 'admin.status.disabled',
}

const STATUSES = Object.keys(STATUS_LABEL_KEYS) as User['status'][]

const STATUS_BADGES: Record<User['status'], string> = {
  pending_email_verification: 'badge-warning',
  pending_admin_approval: 'badge-info',
  active: 'badge-success',
  disabled: 'badge-error',
}

// ── Edit modal ────────────────────────────────────────────────────────────

const editing = ref<User | null>(null)
const editForm = ref<UpdateUserPayload>({})
const saving = ref(false)

function openEdit(user: User): void {
  editing.value = user
  // The admin never sees or edits the user's actual notification email
  // or Discord webhook URL — only the channel choice is admin-editable.
  editForm.value = {
    displayName: user.displayName,
    status: user.status,
    notificationChannel: user.notificationChannel,
  }
}

function closeEdit(): void {
  editing.value = null
  editForm.value = {}
}

async function saveEdit(): Promise<void> {
  if (editing.value === null) return
  saving.value = true
  try {
    await patchUser(editing.value.id, editForm.value)
    ui.addToast(t('admin.updated'), 'success')
    await queryClient.invalidateQueries({ queryKey: ['admin', 'users'] })
    closeEdit()
  } catch {
    ui.addToast(t('admin.updateFailed'), 'error')
  } finally {
    saving.value = false
  }
}

// ── Actions ──────────────────────────────────────────────────────────────

async function approve(user: User): Promise<void> {
  try {
    await approveUser(user.id)
    ui.addToast(t('admin.approved', { name: user.displayName }), 'success')
    await queryClient.invalidateQueries({ queryKey: ['admin', 'users'] })
  } catch {
    ui.addToast(t('admin.approveFailed'), 'error')
  }
}

async function remove(user: User): Promise<void> {
  if (!confirm(t('admin.deleteConfirm', { name: user.displayName, email: user.email }))) {
    return
  }
  try {
    await deleteUser(user.id)
    ui.addToast(t('admin.deleted'), 'success')
    await queryClient.invalidateQueries({ queryKey: ['admin', 'users'] })
  } catch {
    ui.addToast(t('admin.deleteFailed'), 'error')
  }
}

async function copyResetLink(user: User): Promise<void> {
  try {
    const { resetLink } = await generateResetLink(user.id)
    await navigator.clipboard.writeText(resetLink)
    ui.addToast(t('admin.linkCopied', { name: user.displayName }), 'success')
  } catch {
    ui.addToast(t('admin.linkFailed'), 'error')
  }
}
</script>

<template>
  <div class="p-4 sm:p-6 space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-bold">{{ t('admin.title') }}</h1>
      <div class="text-sm text-base-content/60">
        {{ t('admin.accountCount', { count: data?.total ?? 0 }, data?.total ?? 0) }}
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3">
      <input
        v-model="search"
        type="search"
        :placeholder="t('admin.searchPlaceholder')"
        :aria-label="t('admin.searchPlaceholder')"
        class="input input-bordered input-sm w-64"
      />
      <select v-model="statusFilter" class="select select-bordered select-sm" :aria-label="t('admin.columns.status')">
        <option value="">{{ t('admin.allStatuses') }}</option>
        <option v-for="status in STATUSES" :key="status" :value="status">{{ t(STATUS_LABEL_KEYS[status]) }}</option>
      </select>
    </div>

    <!-- Table -->
    <BaseLoader v-if="isPending" variant="section" />

    <div v-else-if="(data?.items.length ?? 0) === 0" class="text-center py-12 text-base-content/60">
      {{ t('admin.empty') }}
    </div>

    <!-- Mobile: stacked cards (the 6-column table is unusable on a phone) -->
    <div v-else class="space-y-3 sm:hidden">
      <div
        v-for="user in data?.items"
        :key="user.id"
        class="rounded-xl border border-base-200 bg-base-100 p-4 space-y-3"
      >
        <div class="flex items-start gap-3">
          <div class="avatar avatar-placeholder shrink-0">
            <div class="w-10 rounded-full bg-primary/15 text-primary">
              <span class="text-xs font-semibold">{{ initials(user.displayName) }}</span>
            </div>
          </div>
          <div class="min-w-0 flex-1">
            <p class="font-semibold leading-tight truncate">{{ user.displayName }}</p>
            <p class="text-xs text-base-content/50 truncate">{{ user.email }}</p>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-1.5">
          <span
            class="badge badge-sm"
            :class="user.role === 'ROLE_ADMIN' ? 'badge-primary' : 'badge-ghost'"
          >
            {{ user.role === 'ROLE_ADMIN' ? t('admin.roleAdmin') : t('admin.roleUser') }}
          </span>
          <span class="badge badge-sm" :class="STATUS_BADGES[user.status]">
            {{ t(STATUS_LABEL_KEYS[user.status]) }}
          </span>
          <span class="badge badge-sm badge-ghost capitalize">
            {{ user.notificationChannel }}<template v-if="user.notificationConfigured === false"> · {{ t('admin.notConfigured') }}</template>
          </span>
        </div>

        <div class="grid grid-cols-2 gap-2">
          <button
            v-if="user.status === 'pending_admin_approval'"
            class="btn btn-success btn-sm col-span-2"
            @click="approve(user)"
          >
            {{ t('admin.approve') }}
          </button>
          <button class="btn btn-outline btn-sm" @click="openEdit(user)">
            {{ t('common.edit') }}
          </button>
          <button class="btn btn-outline btn-sm" @click="copyResetLink(user)">
            {{ t('admin.resetLink') }}
          </button>
          <button class="btn btn-error btn-outline btn-sm col-span-2" @click="remove(user)">
            {{ t('common.delete') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Desktop: full table -->
    <div v-if="!isPending && (data?.items.length ?? 0) > 0" class="hidden sm:block overflow-x-auto rounded-lg border border-base-200">
      <table class="table table-zebra">
        <thead>
          <tr>
            <th>{{ t('admin.columns.user') }}</th>
            <th>{{ t('admin.columns.email') }}</th>
            <th>{{ t('admin.columns.role') }}</th>
            <th>{{ t('admin.columns.status') }}</th>
            <th>{{ t('admin.columns.notifications') }}</th>
            <th class="text-right">{{ t('admin.columns.actions') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="user in data?.items" :key="user.id">
            <td class="font-medium">{{ user.displayName }}</td>
            <td class="text-sm text-base-content/70">{{ user.email }}</td>
            <td>
              <span
                class="badge badge-sm"
                :class="user.role === 'ROLE_ADMIN' ? 'badge-primary' : 'badge-ghost'"
              >
                {{ user.role === 'ROLE_ADMIN' ? t('admin.roleAdmin') : t('admin.roleUser') }}
              </span>
            </td>
            <td>
              <span class="badge badge-sm" :class="STATUS_BADGES[user.status]">
                {{ t(STATUS_LABEL_KEYS[user.status]) }}
              </span>
            </td>
            <td class="text-sm">
              <span class="capitalize">{{ user.notificationChannel }}</span>
              <span
                v-if="user.notificationConfigured === false"
                class="ml-1 text-xs text-base-content/40"
              >({{ t('admin.notConfigured') }})</span>
            </td>
            <td class="text-right">
              <div class="flex justify-end gap-1">
                <button
                  v-if="user.status === 'pending_admin_approval'"
                  class="btn btn-success btn-xs"
                  @click="approve(user)"
                >
                  {{ t('admin.approve') }}
                </button>
                <button class="btn btn-ghost btn-xs" @click="openEdit(user)">
                  {{ t('common.edit') }}
                </button>
                <button class="btn btn-ghost btn-xs" @click="copyResetLink(user)">
                  {{ t('admin.resetLinkShort') }}
                </button>
                <button class="btn btn-error btn-outline btn-xs" @click="remove(user)">
                  {{ t('common.delete') }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="totalPages > 1" class="flex justify-center items-center gap-2">
      <button
        class="btn btn-sm btn-ghost"
        :disabled="page === 1"
        @click="page--"
      >
        {{ t('common.previous') }}
      </button>
      <span class="text-sm text-base-content/60">
        {{ t('common.pageOf', { page, total: totalPages }) }}
      </span>
      <button
        class="btn btn-sm btn-ghost"
        :disabled="page >= totalPages"
        @click="page++"
      >
        {{ t('common.next') }}
      </button>
    </div>

    <!-- Edit modal -->
    <BaseModal
      :open="editing !== null"
      max-width-class="sm:max-w-md"
      @close="closeEdit"
    >
      <template v-if="editing">
        <!-- Header -->
        <div class="flex items-center gap-3 px-5 py-4 border-b border-base-200">
          <div class="avatar avatar-placeholder">
            <div class="w-11 rounded-full bg-primary/15 text-primary">
              <span class="text-sm font-semibold">{{ initials(editing.displayName) }}</span>
            </div>
          </div>
          <div class="min-w-0 flex-1">
            <h3 class="font-semibold leading-tight truncate">{{ editing.displayName }}</h3>
            <p class="text-xs text-base-content/50 truncate">{{ editing.email }}</p>
          </div>
          <button class="btn btn-sm btn-circle btn-ghost" :disabled="saving" :aria-label="t('common.close')" @click="closeEdit">
            <X class="w-4 h-4" />
          </button>
        </div>

        <!-- Body -->
        <div class="px-5 py-4 space-y-4 overflow-y-auto">
          <div>
            <label for="admin-edit-display-name" class="text-sm font-medium">{{ t('admin.displayName') }}</label>
            <input
              id="admin-edit-display-name"
              v-model="editForm.displayName"
              type="text"
              class="input w-full mt-1.5"
              :placeholder="t('admin.displayName')"
            />
          </div>

          <div>
            <label for="admin-edit-status" class="text-sm font-medium">{{ t('admin.columns.status') }}</label>
            <select id="admin-edit-status" v-model="editForm.status" class="select w-full mt-1.5">
              <option v-for="status in STATUSES" :key="status" :value="status">{{ t(STATUS_LABEL_KEYS[status]) }}</option>
            </select>
          </div>

          <div>
            <label for="admin-edit-channel" class="text-sm font-medium">{{ t('admin.channel') }}</label>
            <select id="admin-edit-channel" v-model="editForm.notificationChannel" class="select w-full mt-1.5">
              <option value="email">{{ t('admin.channelEmail') }}</option>
              <option value="discord">{{ t('admin.channelDiscord') }}</option>
            </select>
            <p class="mt-1.5 text-xs text-base-content/50">
              {{ t('admin.channelHelp') }}
            </p>
          </div>
        </div>

        <!-- Footer -->
        <div class="flex justify-end gap-2 px-5 py-4 border-t border-base-200 bg-base-200/40">
          <button class="btn btn-ghost btn-sm" :disabled="saving" @click="closeEdit">{{ t('common.cancel') }}</button>
          <BaseButton class="btn btn-primary btn-sm" :loading="saving" @click="saveEdit">
            {{ t('common.save') }}
          </BaseButton>
        </div>
      </template>
    </BaseModal>
  </div>
</template>
