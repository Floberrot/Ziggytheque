<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { postRequestPasswordReset } from '@/api/auth'
import { useThemeStore } from '@/stores/useThemeStore'
import BaseButton from '@/components/atoms/BaseButton.vue'

const router = useRouter()
const themeStore = useThemeStore()
const { t } = useI18n()

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
          <h2 class="text-lg font-semibold">{{ t('auth.forgot.sentTitle') }}</h2>
          <p class="text-base-content/70 text-sm text-center">
            {{ t('auth.forgot.sentBody') }}
          </p>
          <button class="btn btn-primary btn-sm" @click="router.push({ name: 'login' })">
            {{ t('auth.backToLogin') }}
          </button>
        </template>

        <template v-else>
          <h2 class="text-lg font-semibold">{{ t('auth.forgot.title') }}</h2>
          <p class="text-base-content/60 text-sm text-center">
            {{ t('auth.forgot.intro') }}
          </p>

          <form class="flex flex-col gap-3 w-full" @submit.prevent="submit">
            <input
              v-model="email"
              type="email"
              :placeholder="t('auth.email')"
              :aria-label="t('auth.email')"
              class="input input-bordered w-full"
              autocomplete="email"
              autofocus
            />

            <BaseButton
              type="submit"
              class="btn btn-primary w-full"
              :loading="loading"
            >
              {{ t('auth.forgot.submit') }}
            </BaseButton>
          </form>

          <router-link to="/login" class="link link-hover text-sm">
            {{ t('auth.backToLogin') }}
          </router-link>
        </template>
      </div>
    </div>
  </div>
</template>
