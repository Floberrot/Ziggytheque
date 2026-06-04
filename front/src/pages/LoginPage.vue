<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { useAuthStore } from '@/stores/useAuthStore'
import { useThemeStore } from '@/stores/useThemeStore'
import BaseLoader from '@/components/atoms/BaseLoader.vue'

const router = useRouter()
const auth = useAuthStore()
const themeStore = useThemeStore()

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

const logoSrc = computed(() =>
  themeStore.isDark ? '/logo-dark.png' : '/logo-light.png',
)

async function submit() {
  if (!email.value.trim() || !password.value.trim()) return
  error.value = ''
  loading.value = true
  try {
    await auth.login(email.value.trim(), password.value)
    await router.push({ name: 'dashboard' })
  } catch (err) {
    if (axios.isAxiosError(err) && err.response?.status === 403) {
      error.value = 'Votre compte n\'est pas encore actif. Vérifiez votre email ou attendez l\'approbation admin.'
    } else {
      error.value = 'Email ou mot de passe invalide.'
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

        <p class="text-base-content/50 text-sm tracking-wide">
          Connectez-vous à Ziggytheque
        </p>

        <form class="flex flex-col gap-3 w-full" @submit.prevent="submit">
          <div class="form-control">
            <input
              v-model="email"
              type="email"
              placeholder="Email"
              class="input input-bordered w-full"
              :class="{ 'input-error': error }"
              autocomplete="email"
              autofocus
            />
          </div>

          <div class="form-control">
            <input
              v-model="password"
              type="password"
              placeholder="Mot de passe"
              class="input input-bordered w-full"
              :class="{ 'input-error': error }"
              autocomplete="current-password"
            />
            <label v-if="error" class="label">
              <span class="label-text-alt text-error">{{ error }}</span>
            </label>
          </div>

          <button
            type="submit"
            class="btn btn-primary w-full"
            :disabled="loading"
          >
            <BaseLoader v-if="loading" size="xs" />
            Se connecter
          </button>
        </form>

        <div class="flex flex-col items-center gap-1 w-full">
          <router-link to="/register" class="link link-hover text-sm">
            Pas encore de compte&nbsp;? Inscrivez-vous
          </router-link>
          <router-link to="/forgot-password" class="link link-hover text-sm text-base-content/60">
            Mot de passe oublié&nbsp;?
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>
