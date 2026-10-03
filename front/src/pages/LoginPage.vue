<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import axios from 'axios'
import { useAuthStore } from '@/stores/useAuthStore'
import { useThemeStore } from '@/stores/useThemeStore'
import BaseButton from '@/components/atoms/BaseButton.vue'

const router = useRouter()
const auth = useAuthStore()
const themeStore = useThemeStore()
const { t } = useI18n()

const email = ref('')
const password = ref('')
/** i18n key of the failure, '' when none. */
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
      error.value = 'auth.login.inactive'
    } else if (axios.isAxiosError(err) && err.response?.status === 429) {
      error.value = 'auth.login.tooManyAttempts'
    } else {
      error.value = 'auth.login.invalid'
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
          {{ t('auth.login.subtitle') }}
        </p>

        <form class="flex flex-col gap-3 w-full" @submit.prevent="submit">
          <div class="form-control">
            <input
              v-model="email"
              type="email"
              :placeholder="t('auth.email')"
              :aria-label="t('auth.email')"
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
              :placeholder="t('auth.password')"
              :aria-label="t('auth.password')"
              class="input input-bordered w-full"
              :class="{ 'input-error': error }"
              autocomplete="current-password"
            />
            <label v-if="error" class="label">
              <span class="label-text-alt text-error">{{ t(error) }}</span>
            </label>
          </div>

          <BaseButton
            type="submit"
            class="btn btn-primary w-full"
            :loading="loading"
          >
            {{ t('auth.login.submit') }}
          </BaseButton>
        </form>

        <div class="flex flex-col items-center gap-1 w-full">
          <router-link to="/register" class="link link-hover text-sm">
            {{ t('auth.login.noAccount') }}
          </router-link>
          <router-link to="/forgot-password" class="link link-hover text-sm text-base-content/60">
            {{ t('auth.login.forgotPassword') }}
          </router-link>
        </div>
      </div>
    </div>
  </div>
</template>
