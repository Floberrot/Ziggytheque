<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/useAuthStore'
import BaseLoader from '@/components/atoms/BaseLoader.vue'
import BaseModal from '@/components/atoms/BaseModal.vue'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const password = ref('')
const error = ref('')
const loading = ref(false)

function resolveRedirectTarget(): string {
  const redirectQuery = route.query.redirect
  const redirect = Array.isArray(redirectQuery) ? redirectQuery[0] : redirectQuery
  if (typeof redirect === 'string' && redirect.startsWith('/') && !redirect.startsWith('//')) {
    return redirect
  }
  return '/dashboard'
}

async function submit() {
  if (!password.value.trim()) return
  error.value = ''
  loading.value = true
  try {
    await auth.unlockGate(password.value)
    await router.push(resolveRedirectTarget())
  } catch {
    error.value = 'Mot de passe d\'accès invalide.'
    password.value = ''
  } finally {
    loading.value = false
  }
}

function cancel() {
  router.back()
}
</script>

<template>
  <div class="min-h-screen bg-base-200" />

  <BaseModal
    :open="true"
    max-width-class="sm:max-w-sm"
    @close="cancel"
  >
    <div class="p-6 pb-4 sm:pb-6">
      <h3 class="font-bold text-lg">Accès administrateur</h3>
      <p class="py-2 text-sm text-base-content/70">
        Entrez le mot de passe d'accès pour débloquer cette section.
      </p>

      <form class="flex flex-col gap-4 pt-2" @submit.prevent="submit">
        <div class="form-control">
          <input
            v-model="password"
            type="password"
            placeholder="Mot de passe d'accès"
            class="input input-bordered w-full"
            :class="{ 'input-error': error }"
            autocomplete="current-password"
            autofocus
          />
          <label v-if="error" class="label">
            <span class="label-text-alt text-error">{{ error }}</span>
          </label>
        </div>

        <div class="flex justify-end gap-2">
          <button
            type="button"
            class="btn btn-ghost"
            :disabled="loading"
            @click="cancel"
          >
            Annuler
          </button>
          <button
            type="submit"
            class="btn btn-primary"
            :disabled="loading"
          >
            <BaseLoader v-if="loading" size="xs" />
            Débloquer
          </button>
        </div>
      </form>
    </div>
  </BaseModal>
</template>
