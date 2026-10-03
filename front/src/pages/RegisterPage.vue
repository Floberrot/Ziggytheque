<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import axios from 'axios'
import { Eye, EyeOff } from 'lucide-vue-next'
import { postRegister } from '@/api/auth'
import { useThemeStore } from '@/stores/useThemeStore'
import BaseButton from '@/components/atoms/BaseButton.vue'

const router = useRouter()
const themeStore = useThemeStore()
const { t } = useI18n()

const email = ref('')
const password = ref('')
const passwordConfirm = ref('')
const displayName = ref('')
const showPassword = ref(false)
const showPasswordConfirm = ref(false)
/** i18n key of the failure, '' when none. */
const error = ref('')
const loading = ref(false)
const success = ref(false)

const logoSrc = computed(() =>
  themeStore.isDark ? '/logo-dark.png' : '/logo-light.png',
)

const passwordStrength = computed(() => {
  const value = password.value
  if (!value) return { score: 0, labelKey: '', barClass: '', textClass: '' }

  let score = 0
  if (value.length >= 8) score++
  if (value.length >= 12) score++
  if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++
  if (/\d/.test(value)) score++
  if (/[^A-Za-z0-9]/.test(value)) score++

  if (score <= 1) return { score, labelKey: 'auth.register.strengthWeak', barClass: 'bg-error', textClass: 'text-error' }
  if (score <= 2) return { score, labelKey: 'auth.register.strengthFair', barClass: 'bg-warning', textClass: 'text-warning' }
  if (score <= 3) return { score, labelKey: 'auth.register.strengthGood', barClass: 'bg-info', textClass: 'text-info' }
  if (score <= 4) return { score, labelKey: 'auth.register.strengthStrong', barClass: 'bg-success', textClass: 'text-success' }
  return { score, labelKey: 'auth.register.strengthVeryStrong', barClass: 'bg-success', textClass: 'text-success' }
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
    // An address that already has an account gets the same answer as a new one
    // (its owner is told by email), so there is no "already taken" case here.
    if (axios.isAxiosError(err) && err.response?.status === 422) {
      error.value = 'auth.register.invalid'
    } else if (axios.isAxiosError(err) && err.response?.status === 429) {
      error.value = 'auth.register.tooManyAttempts'
    } else {
      error.value = 'auth.register.failed'
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
          <h2 class="text-lg font-semibold">{{ t('auth.register.sentTitle') }}</h2>
          <p class="text-base-content/70 text-sm">
            {{ t('auth.register.sentBody') }}
          </p>
          <button class="btn btn-primary btn-sm" @click="router.push({ name: 'login' })">
            {{ t('auth.backToLogin') }}
          </button>
        </div>

        <template v-else>
          <p class="text-base-content/50 text-sm tracking-wide">
            {{ t('auth.register.subtitle') }}
          </p>

          <form class="flex flex-col gap-3 w-full" @submit.prevent="submit">
            <input
              v-model="displayName"
              type="text"
              :placeholder="t('auth.register.displayName')"
              :aria-label="t('auth.register.displayName')"
              class="input input-bordered w-full"
              :class="{ 'input-error': error }"
              autocomplete="name"
              autofocus
            />
            <input
              v-model="email"
              type="email"
              :placeholder="t('auth.email')"
              :aria-label="t('auth.email')"
              class="input input-bordered w-full"
              :class="{ 'input-error': error }"
              autocomplete="email"
            />

            <div class="form-control">
              <div class="relative">
                <input
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  :placeholder="t('auth.register.passwordHint')"
                  :aria-label="t('auth.register.passwordHint')"
                  class="input input-bordered w-full pr-12"
                  :class="{ 'input-error': error }"
                  autocomplete="new-password"
                  minlength="8"
                />
                <button
                  type="button"
                  class="absolute inset-y-0 right-0 px-3 flex items-center text-base-content/60 hover:text-base-content"
                  :aria-label="showPassword ? t('auth.hidePassword') : t('auth.showPassword')"
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
                  {{ t('auth.register.strength', { level: t(passwordStrength.labelKey) }) }}
                </p>
              </div>
            </div>

            <div class="form-control">
              <div class="relative">
                <input
                  v-model="passwordConfirm"
                  :type="showPasswordConfirm ? 'text' : 'password'"
                  :placeholder="t('auth.confirmPassword')"
                  :aria-label="t('auth.confirmPassword')"
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
                  :aria-label="showPasswordConfirm ? t('auth.hidePassword') : t('auth.showPassword')"
                  tabindex="-1"
                  @click="showPasswordConfirm = !showPasswordConfirm"
                >
                  <EyeOff v-if="showPasswordConfirm" class="w-4 h-4" />
                  <Eye v-else class="w-4 h-4" />
                </button>
              </div>
              <label v-if="passwordsMismatch" class="label">
                <span class="label-text-alt text-error">{{ t('auth.passwordsMismatch') }}</span>
              </label>
              <label v-else-if="error" class="label">
                <span class="label-text-alt text-error">{{ t(error) }}</span>
              </label>
            </div>

            <BaseButton
              type="submit"
              class="btn btn-primary w-full"
              :loading="loading"
              :disabled="!canSubmit"
            >
              {{ t('auth.register.submit') }}
            </BaseButton>
          </form>

          <router-link to="/login" class="link link-hover text-sm">
            {{ t('auth.register.haveAccount') }}
          </router-link>
        </template>
      </div>
    </div>
  </div>
</template>
