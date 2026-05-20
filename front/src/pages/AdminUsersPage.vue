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

const STATUS_LABELS: Record<User['status'], string> = {
  pending_email_verification: 'Email à vérifier',
  pending_admin_approval: 'À approuver',
  active: 'Actif',
  disabled: 'Désactivé',
}

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
  editForm.value = {
    displayName: user.displayName,
    status: user.status,
    notificationChannel: user.notificationChannel,
    notificationEmail: user.notificationEmail,
    discordWebhookUrl: user.discordWebhookUrl,
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
    ui.addToast('Utilisateur mis à jour.', 'success')
    await queryClient.invalidateQueries({ queryKey: ['admin', 'users'] })
    closeEdit()
  } catch {
    ui.addToast('La mise à jour a échoué.', 'error')
  } finally {
    saving.value = false
  }
}

// ── Actions ──────────────────────────────────────────────────────────────

async function approve(user: User): Promise<void> {
  try {
    await approveUser(user.id)
    ui.addToast(`${user.displayName} approuvé.`, 'success')
    await queryClient.invalidateQueries({ queryKey: ['admin', 'users'] })
  } catch {
    ui.addToast('L\'approbation a échoué.', 'error')
  }
}

async function remove(user: User): Promise<void> {
  if (!confirm(`Supprimer ${user.displayName} (${user.email}) ? Toutes ses données seront perdues.`)) {
    return
  }
  try {
    await deleteUser(user.id)
    ui.addToast('Utilisateur supprimé.', 'success')
    await queryClient.invalidateQueries({ queryKey: ['admin', 'users'] })
  } catch {
    ui.addToast('La suppression a échoué.', 'error')
  }
}

async function copyResetLink(user: User): Promise<void> {
  try {
    const { resetLink } = await generateResetLink(user.id)
    await navigator.clipboard.writeText(resetLink)
    ui.addToast(`Lien copié pour ${user.displayName}.`, 'success')
  } catch {
    ui.addToast('La génération du lien a échoué.', 'error')
  }
}
</script>

<template>
  <div class="p-4 sm:p-6 space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h1 class="text-2xl font-bold">Utilisateurs</h1>
      <div class="text-sm text-base-content/60">
        {{ data?.total ?? 0 }} compte{{ (data?.total ?? 0) > 1 ? 's' : '' }}
      </div>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap items-center gap-3">
      <input
        v-model="search"
        type="search"
        placeholder="Rechercher email ou nom…"
        class="input input-bordered input-sm w-64"
      />
      <select v-model="statusFilter" class="select select-bordered select-sm">
        <option value="">Tous les statuts</option>
        <option value="pending_email_verification">Email à vérifier</option>
        <option value="pending_admin_approval">À approuver</option>
        <option value="active">Actif</option>
        <option value="disabled">Désactivé</option>
      </select>
    </div>

    <!-- Table -->
    <div v-if="isPending" class="flex justify-center py-12">
      <span class="loading loading-spinner loading-lg" />
    </div>

    <div v-else-if="(data?.items.length ?? 0) === 0" class="text-center py-12 text-base-content/60">
      Aucun utilisateur trouvé.
    </div>

    <div v-else class="overflow-x-auto rounded-lg border border-base-200">
      <table class="table table-zebra">
        <thead>
          <tr>
            <th>Utilisateur</th>
            <th>Email</th>
            <th>Rôle</th>
            <th>Statut</th>
            <th>Notifications</th>
            <th class="text-right">Actions</th>
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
                {{ user.role === 'ROLE_ADMIN' ? 'Admin' : 'Utilisateur' }}
              </span>
            </td>
            <td>
              <span class="badge badge-sm" :class="STATUS_BADGES[user.status]">
                {{ STATUS_LABELS[user.status] }}
              </span>
            </td>
            <td class="text-sm">
              <span class="capitalize">{{ user.notificationChannel }}</span>
            </td>
            <td class="text-right">
              <div class="flex justify-end gap-1">
                <button
                  v-if="user.status === 'pending_admin_approval'"
                  class="btn btn-success btn-xs"
                  @click="approve(user)"
                >
                  Approuver
                </button>
                <button class="btn btn-ghost btn-xs" @click="openEdit(user)">
                  Modifier
                </button>
                <button class="btn btn-ghost btn-xs" @click="copyResetLink(user)">
                  Reset link
                </button>
                <button class="btn btn-error btn-outline btn-xs" @click="remove(user)">
                  Supprimer
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
        Précédent
      </button>
      <span class="text-sm text-base-content/60">
        Page {{ page }} / {{ totalPages }}
      </span>
      <button
        class="btn btn-sm btn-ghost"
        :disabled="page >= totalPages"
        @click="page++"
      >
        Suivant
      </button>
    </div>

    <!-- Edit modal -->
    <dialog v-if="editing !== null" class="modal modal-open" @close="closeEdit">
      <div class="modal-box max-w-md p-0 overflow-hidden">
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
          <button class="btn btn-sm btn-circle btn-ghost" :disabled="saving" @click="closeEdit">
            <X class="w-4 h-4" />
          </button>
        </div>

        <!-- Body -->
        <div class="px-5 py-4 space-y-4">
          <div>
            <label class="text-sm font-medium">Nom d'affichage</label>
            <input
              v-model="editForm.displayName"
              type="text"
              class="input w-full mt-1.5"
              placeholder="Nom d'affichage"
            />
          </div>

          <div>
            <label class="text-sm font-medium">Statut</label>
            <select v-model="editForm.status" class="select w-full mt-1.5">
              <option value="pending_email_verification">Email à vérifier</option>
              <option value="pending_admin_approval">À approuver</option>
              <option value="active">Actif</option>
              <option value="disabled">Désactivé</option>
            </select>
          </div>

          <div>
            <label class="text-sm font-medium">Canal de notification</label>
            <select v-model="editForm.notificationChannel" class="select w-full mt-1.5">
              <option value="email">Email</option>
              <option value="discord">Discord</option>
            </select>
          </div>

          <div v-if="editForm.notificationChannel === 'email'">
            <label class="text-sm font-medium">Email de notification</label>
            <input
              v-model="editForm.notificationEmail"
              type="email"
              class="input w-full mt-1.5"
              placeholder="adresse@exemple.com"
            />
          </div>

          <div v-if="editForm.notificationChannel === 'discord'">
            <label class="text-sm font-medium">Webhook Discord</label>
            <input
              v-model="editForm.discordWebhookUrl"
              type="url"
              class="input w-full mt-1.5"
              placeholder="https://discord.com/api/webhooks/…"
            />
          </div>
        </div>

        <!-- Footer -->
        <div class="flex justify-end gap-2 px-5 py-4 border-t border-base-200 bg-base-200/40">
          <button class="btn btn-ghost btn-sm" :disabled="saving" @click="closeEdit">Annuler</button>
          <button class="btn btn-primary btn-sm" :disabled="saving" @click="saveEdit">
            <span v-if="saving" class="loading loading-spinner loading-xs" />
            Enregistrer
          </button>
        </div>
      </div>
      <form method="dialog" class="modal-backdrop"><button @click="closeEdit">close</button></form>
    </dialog>
  </div>
</template>
