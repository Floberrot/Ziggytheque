<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import axios from 'axios'
import { postResetPassword } from '@/api/auth'
import { useThemeStore } from '@/stores/useThemeStore'
import BaseButton from '@/components/atoms/BaseButton.vue'

const route = useRoute()
const router = useRouter()
const themeStore = useThemeStore()
const { t } = useI18n()

const newPassword = ref('')
const confirmPassword = ref('')
/** i18n key of the failure, '' when none. */
const error = ref('')
const loading = ref(false)
const success = ref(false)

const logoSrc = computed(() =>
  themeStore.isDark ? '/logo-dark.png' : '/logo-light.png',
)

async function submit() {
  if (newPassword.value !== confirmPassword.value) {
    error.value = 'auth.passwordsMismatch'
    return
  }
  if (newPassword.value.length < 8) {
    error.value = 'auth.reset.tooShort'
    return
  }
  const token = route.query.token
  if (typeof token !== 'string' || token === '') {
    error.value = 'auth.reset.invalidLink'
    return
  }

  error.value = ''
  loading.value = true
  try {
    await postResetPassword(token, newPassword.value)
    success.value = true
  } catch (err) {
    if (axios.isAxiosError(err) && err.response?.status === 400) {
      error.value = 'auth.reset.expired'
    } else {
      error.value = 'auth.reset.failed'
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
          <h2 class="text-lg font-semibold">{{ t('auth.reset.doneTitle') }}</h2>
          <p class="text-base-content/70 text-sm text-center">
            {{ t('auth.reset.doneBody') }}
          </p>
          <button class="btn btn-primary btn-sm" @click="router.push({ name: 'login' })">
            {{ t('auth.goToLogin') }}
          </button>
        </template>

        <template v-else>
          <h2 class="text-lg font-semibold">{{ t('auth.reset.title') }}</h2>

          <form class="flex flex-col gap-3 w-full" @submit.prevent="submit">
            <input
              v-model="newPassword"
              type="password"
              :placeholder="t('auth.reset.newPassword')"
              :aria-label="t('auth.reset.newPassword')"
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
                :placeholder="t('auth.confirmPassword')"
                :aria-label="t('auth.confirmPassword')"
                class="input input-bordered w-full"
                :class="{ 'input-error': error }"
                autocomplete="new-password"
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
              {{ t('auth.reset.submit') }}
            </BaseButton>
          </form>
        </template>
      </div>
    </div>
  </div>
</template>
