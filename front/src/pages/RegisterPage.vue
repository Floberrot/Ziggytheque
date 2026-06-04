<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { Eye, EyeOff } from 'lucide-vue-next'
import { postRegister } from '@/api/auth'
import { useThemeStore } from '@/stores/useThemeStore'
import BaseLoader from '@/components/atoms/BaseLoader.vue'

const router = useRouter()
const themeStore = useThemeStore()

const email = ref('')
const password = ref('')
const passwordConfirm = ref('')
const displayName = ref('')
const showPassword = ref(false)
const showPasswordConfirm = ref(false)
const error = ref('')
const loading = ref(false)
const success = ref(false)

const logoSrc = computed(() =>
  themeStore.isDark ? '/logo-dark.png' : '/logo-light.png',
)

const passwordStrength = computed(() => {
  const value = password.value
  if (!value) return { score: 0, label: '', barClass: '', textClass: '' }

  let score = 0
  if (value.length >= 8) score++
  if (value.length >= 12) score++
  if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++
  if (/\d/.test(value)) score++
  if (/[^A-Za-z0-9]/.test(value)) score++

  if (score <= 1) return { score, label: 'Faible', barClass: 'bg-error', textClass: 'text-error' }
  if (score <= 2) return { score, label: 'Moyen', barClass: 'bg-warning', textClass: 'text-warning' }
  if (score <= 3) return { score, label: 'Bon', barClass: 'bg-info', textClass: 'text-info' }
  if (score <= 4) return { score, label: 'Fort', barClass: 'bg-success', textClass: 'text-success' }
  return { score, label: 'Très fort', barClass: 'bg-success', textClass: 'text-success' }
})

const passwordsMatch = computed(
  () => passwordConfirm.value.length > 0 && password.value === passwordConfirm.value,
)
const passwordsMismatch = computed(
  () => passwordConfirm.value.length > 0 && password.value !== passwordConfirm.value,
)

const canSubmit = computed(
  () =>
    !loading.value &&
    email.value.trim().length > 0 &&
    displayName.value.trim().length > 0 &&
    password.value.length >= 8 &&
    passwordsMatch.value,
)

async function submit() {
  if (!canSubmit.value) return
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
              <div class="relative">
                <input
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  placeholder="Mot de passe (8 caractères min)"
                  class="input input-bordered w-full pr-12"
                  :class="{ 'input-error': error }"
                  autocomplete="new-password"
                  minlength="8"
                />
                <button
                  type="button"
                  class="absolute inset-y-0 right-0 px-3 flex items-center text-base-content/60 hover:text-base-content"
                  :aria-label="showPassword ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
                  tabindex="-1"
                  @click="showPassword = !showPassword"
                >
                  <EyeOff v-if="showPassword" class="w-4 h-4" />
                  <Eye v-else class="w-4 h-4" />
                </button>
              </div>

              <div v-if="password" class="mt-2 space-y-1">
                <div class="flex gap-1">
                  <div
                    v-for="index in 5"
                    :key="index"
                    class="h-1 flex-1 rounded-full transition-colors"
                    :class="index <= passwordStrength.score ? passwordStrength.barClass : 'bg-base-300'"
                  />
                </div>
                <p class="text-xs" :class="passwordStrength.textClass">
                  Force&nbsp;: {{ passwordStrength.label }}
                </p>
              </div>
            </div>

            <div class="form-control">
              <div class="relative">
                <input
                  v-model="passwordConfirm"
                  :type="showPasswordConfirm ? 'text' : 'password'"
                  placeholder="Confirmer le mot de passe"
                  class="input input-bordered w-full pr-12"
                  :class="{
                    'input-error': passwordsMismatch || error,
                    'input-success': passwordsMatch,
                  }"
                  autocomplete="new-password"
                />
                <button
                  type="button"
                  class="absolute inset-y-0 right-0 px-3 flex items-center text-base-content/60 hover:text-base-content"
                  :aria-label="showPasswordConfirm ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"
                  tabindex="-1"
                  @click="showPasswordConfirm = !showPasswordConfirm"
                >
                  <EyeOff v-if="showPasswordConfirm" class="w-4 h-4" />
                  <Eye v-else class="w-4 h-4" />
                </button>
              </div>
              <label v-if="passwordsMismatch" class="label">
                <span class="label-text-alt text-error">Les mots de passe ne correspondent pas.</span>
              </label>
              <label v-else-if="error" class="label">
                <span class="label-text-alt text-error">{{ error }}</span>
              </label>
            </div>

            <button
              type="submit"
              class="btn btn-primary w-full"
              :disabled="!canSubmit"
            >
              <BaseLoader v-if="loading" size="xs" />
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
