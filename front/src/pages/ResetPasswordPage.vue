<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import { postResetPassword } from '@/api/auth'
import { useThemeStore } from '@/stores/useThemeStore'

const route = useRoute()
const router = useRouter()
const themeStore = useThemeStore()

const newPassword = ref('')
const confirmPassword = ref('')
const error = ref('')
const loading = ref(false)
const success = ref(false)

const logoSrc = computed(() =>
  themeStore.isDark ? '/logo-dark.png' : '/logo-light.png',
)

async function submit() {
  if (newPassword.value !== confirmPassword.value) {
    error.value = 'Les mots de passe ne correspondent pas.'
    return
  }
  if (newPassword.value.length < 8) {
    error.value = 'Le mot de passe doit faire au moins 8 caractères.'
    return
  }
  const token = route.query.token
  if (typeof token !== 'string' || token === '') {
    error.value = 'Lien invalide.'
    return
  }

  error.value = ''
  loading.value = true
  try {
    await postResetPassword(token, newPassword.value)
    success.value = true
  } catch (err) {
    if (axios.isAxiosError(err) && err.response?.status === 400) {
      error.value = 'Ce lien est invalide ou a expiré.'
    } else {
      error.value = 'La réinitialisation a échoué.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-base-200 px-4">
    <div class="card w-full max-w-sm shadow-2xl bg-base-100">
      <div class="card-body gap-5 items-center pt-10 pb-6">
        <img :src="logoSrc" alt="Ziggytheque" class="h-28 w-auto object-contain" />

        <template v-if="success">
          <h2 class="text-lg font-semibold">Mot de passe modifié&nbsp;!</h2>
          <p class="text-base-content/70 text-sm text-center">
            Vous pouvez désormais vous connecter avec votre nouveau mot de passe.
          </p>
          <button class="btn btn-primary btn-sm" @click="router.push({ name: 'login' })">
            Aller à la connexion
          </button>
        </template>

        <template v-else>
          <h2 class="text-lg font-semibold">Nouveau mot de passe</h2>

          <form class="flex flex-col gap-3 w-full" @submit.prevent="submit">
            <input
              v-model="newPassword"
              type="password"
              placeholder="Nouveau mot de passe"
              class="input input-bordered w-full"
              :class="{ 'input-error': error }"
              autocomplete="new-password"
              minlength="8"
              autofocus
            />
            <div class="form-control">
              <input
                v-model="confirmPassword"
                type="password"
                placeholder="Confirmer le mot de passe"
                class="input input-bordered w-full"
                :class="{ 'input-error': error }"
                autocomplete="new-password"
              />
              <label v-if="error" class="label">
                <span class="label-text-alt text-error">{{ error }}</span>
              </label>
            </div>

            <button
              type="submit"
              class="btn btn-primary w-full"
              :class="{ loading }"
              :disabled="loading"
            >
              Réinitialiser
            </button>
          </form>
        </template>
      </div>
    </div>
  </div>
</template>
