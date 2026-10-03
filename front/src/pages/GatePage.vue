<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '@/stores/useAuthStore'
import BaseButton from '@/components/atoms/BaseButton.vue'
import BaseModal from '@/components/atoms/BaseModal.vue'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const { t } = useI18n()

const password = ref('')
/** i18n key of the failure, '' when none. */
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
    error.value = 'auth.gate.invalid'
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
      <h3 class="font-bold text-lg">{{ t('auth.gate.title') }}</h3>
      <p class="py-2 text-sm text-base-content/70">
        {{ t('auth.gate.intro') }}
      </p>

      <form class="flex flex-col gap-4 pt-2" @submit.prevent="submit">
        <div class="form-control">
          <input
            v-model="password"
            type="password"
            :placeholder="t('auth.gate.password')"
            :aria-label="t('auth.gate.password')"
            class="input input-bordered w-full"
            :class="{ 'input-error': error }"
            autocomplete="current-password"
            autofocus
          />
          <label v-if="error" class="label">
            <span class="label-text-alt text-error">{{ t(error) }}</span>
          </label>
        </div>

        <div class="flex justify-end gap-2">
          <button
            type="button"
            class="btn btn-ghost"
            :disabled="loading"
            @click="cancel"
          >
            {{ t('common.cancel') }}
          </button>
          <BaseButton
            type="submit"
            class="btn btn-primary"
            :loading="loading"
          >
            {{ t('auth.gate.submit') }}
          </BaseButton>
        </div>
      </form>
    </div>
  </BaseModal>
</template>
