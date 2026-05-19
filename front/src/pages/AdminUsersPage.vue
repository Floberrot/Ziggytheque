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

const ui = useUiStore()
const queryClient = useQueryClient()

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
  PendingEmailVerification: 'Email à vérifier',
  PendingAdminApproval: 'À approuver',
  Active: 'Actif',
  Disabled: 'Désactivé',
}

const STATUS_BADGES: Record<User['status'], string> = {
  PendingEmailVerification: 'badge-warning',
  PendingAdminApproval: 'badge-info',
  Active: 'badge-success',
  Disabled: 'badge-error',
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
        <option value="PendingEmailVerification">Email à vérifier</option>
        <option value="PendingAdminApproval">À approuver</option>
        <option value="Active">Actif</option>
        <option value="Disabled">Désactivé</option>
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
                  v-if="user.status === 'PendingAdminApproval'"
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
      <div class="modal-box space-y-4">
        <h3 class="font-bold text-lg">Modifier {{ editing.displayName }}</h3>

        <div class="form-control">
          <label class="label"><span class="label-text">Nom d'affichage</span></label>
          <input v-model="editForm.displayName" type="text" class="input input-bordered" />
        </div>

        <div class="form-control">
          <label class="label"><span class="label-text">Statut</span></label>
          <select v-model="editForm.status" class="select select-bordered">
            <option value="PendingEmailVerification">Email à vérifier</option>
            <option value="PendingAdminApproval">À approuver</option>
            <option value="Active">Actif</option>
            <option value="Disabled">Désactivé</option>
          </select>
        </div>

        <div class="form-control">
          <label class="label"><span class="label-text">Canal de notification</span></label>
          <select v-model="editForm.notificationChannel" class="select select-bordered">
            <option value="email">Email</option>
            <option value="discord">Discord</option>
          </select>
        </div>

        <div v-if="editForm.notificationChannel === 'email'" class="form-control">
          <label class="label"><span class="label-text">Email de notification</span></label>
          <input v-model="editForm.notificationEmail" type="email" class="input input-bordered" />
        </div>

        <div v-if="editForm.notificationChannel === 'discord'" class="form-control">
          <label class="label"><span class="label-text">Webhook Discord</span></label>
          <input v-model="editForm.discordWebhookUrl" type="url" class="input input-bordered" />
        </div>

        <div class="modal-action">
          <button class="btn btn-ghost" :disabled="saving" @click="closeEdit">Annuler</button>
          <button
            class="btn btn-primary"
            :class="{ loading: saving }"
            :disabled="saving"
            @click="saveEdit"
          >
            Enregistrer
          </button>
        </div>
      </div>
      <form method="dialog" class="modal-backdrop"><button @click="closeEdit">close</button></form>
    </dialog>
  </div>
</template>
