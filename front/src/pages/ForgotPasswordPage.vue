<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { postRequestPasswordReset } from '@/api/auth'
import { useThemeStore } from '@/stores/useThemeStore'
import BaseLoader from '@/components/atoms/BaseLoader.vue'

const router = useRouter()
const themeStore = useThemeStore()

const email = ref('')
const loading = ref(false)
const submitted = ref(false)

const logoSrc = computed(() =>
  themeStore.isDark ? '/logo-dark.png' : '/logo-light.png',
)

async function submit() {
  if (!email.value.trim()) return
  loading.value = true
  try {
    await postRequestPasswordReset(email.value.trim())
    submitted.value = true
  } catch {
    submitted.value = true
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

        <template v-if="submitted">
          <h2 class="text-lg font-semibold">Email envoyé</h2>
          <p class="text-base-content/70 text-sm text-center">
            Si un compte existe pour cette adresse, un lien de réinitialisation
            vient d'être envoyé.
          </p>
          <button class="btn btn-primary btn-sm" @click="router.push({ name: 'login' })">
            Retour à la connexion
          </button>
        </template>

        <template v-else>
          <h2 class="text-lg font-semibold">Mot de passe oublié&nbsp;?</h2>
          <p class="text-base-content/60 text-sm text-center">
            Entrez votre email pour recevoir un lien de réinitialisation.
          </p>

          <form class="flex flex-col gap-3 w-full" @submit.prevent="submit">
            <input
              v-model="email"
              type="email"
              placeholder="Email"
              class="input input-bordered w-full"
              autocomplete="email"
              autofocus
            />

            <button
              type="submit"
              class="btn btn-primary w-full"
              :disabled="loading"
            >
              <BaseLoader v-if="loading" size="xs" />
              Envoyer le lien
            </button>
          </form>

          <router-link to="/login" class="link link-hover text-sm">
            Retour à la connexion
          </router-link>
        </template>
      </div>
    </div>
  </div>
</template>
