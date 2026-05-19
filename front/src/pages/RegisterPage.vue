<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { postRegister } from '@/api/auth'
import { useThemeStore } from '@/stores/useThemeStore'

const router = useRouter()
const themeStore = useThemeStore()

const email = ref('')
const password = ref('')
const displayName = ref('')
const error = ref('')
const loading = ref(false)
const success = ref(false)

const logoSrc = computed(() =>
  themeStore.isDark ? '/logo-dark.png' : '/logo-light.png',
)

async function submit() {
  if (!email.value.trim() || !password.value.trim() || !displayName.value.trim()) return
  error.value = ''
  loading.value = true
  try {
    await postRegister(email.value.trim(), password.value, displayName.value.trim())
    success.value = true
  } catch (err) {
    if (axios.isAxiosError(err) && err.response?.status === 409) {
      error.value = 'Cet email est déjà utilisé.'
    } else if (axios.isAxiosError(err) && err.response?.status === 422) {
      error.value = 'Email invalide ou mot de passe trop court (8 caractères min).'
    } else {
      error.value = 'L\'inscription a échoué. Réessayez plus tard.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-base-200 px-4 py-8">
    <div class="card w-full max-w-sm shadow-2xl bg-base-100">
      <div class="card-body gap-5 items-center pt-10 pb-6">
        <img :src="logoSrc" alt="Ziggytheque" class="h-28 w-auto object-contain" />

        <div v-if="success" class="space-y-4 text-center">
          <h2 class="text-lg font-semibold">Inscription envoyée&nbsp;!</h2>
          <p class="text-base-content/70 text-sm">
            Un email de vérification vous a été envoyé. Cliquez sur le lien
            pour activer votre compte. Un administrateur devra ensuite valider
            votre demande avant que vous puissiez vous connecter.
          </p>
          <button class="btn btn-primary btn-sm" @click="router.push({ name: 'login' })">
            Retour à la connexion
          </button>
        </div>

        <template v-else>
          <p class="text-base-content/50 text-sm tracking-wide">
            Créez votre compte
          </p>

          <form class="flex flex-col gap-3 w-full" @submit.prevent="submit">
            <input
              v-model="displayName"
              type="text"
              placeholder="Nom d'affichage"
              class="input input-bordered w-full"
              :class="{ 'input-error': error }"
              autocomplete="name"
              autofocus
            />
            <input
              v-model="email"
              type="email"
              placeholder="Email"
              class="input input-bordered w-full"
              :class="{ 'input-error': error }"
              autocomplete="email"
            />
            <div class="form-control">
              <input
                v-model="password"
                type="password"
                placeholder="Mot de passe (8 caractères min)"
                class="input input-bordered w-full"
                :class="{ 'input-error': error }"
                autocomplete="new-password"
                minlength="8"
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
              S'inscrire
            </button>
          </form>

          <router-link to="/login" class="link link-hover text-sm">
            Déjà inscrit&nbsp;? Connectez-vous
          </router-link>
        </template>
      </div>
    </div>
  </div>
</template>
