<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { postVerifyEmail } from '@/api/auth'
import { useThemeStore } from '@/stores/useThemeStore'
import BaseLoader from '@/components/atoms/BaseLoader.vue'

const route = useRoute()
const router = useRouter()
const themeStore = useThemeStore()
const { t } = useI18n()

const status = ref<'loading' | 'success' | 'error'>('loading')

const logoSrc = computed(() =>
  themeStore.isDark ? '/logo-dark.png' : '/logo-light.png',
)

onMounted(async () => {
  const token = route.query.token
  if (typeof token !== 'string' || token === '') {
    status.value = 'error'
    return
  }
  try {
    await postVerifyEmail(token)
    status.value = 'success'
  } catch {
    status.value = 'error'
  }
})
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-base-200 px-4">
    <div class="card w-full max-w-sm shadow-2xl bg-base-100">
      <div class="card-body gap-5 items-center pt-10 pb-6 text-center">
        <img :src="logoSrc" alt="Ziggytheque" class="h-28 w-auto object-contain" />

        <template v-if="status === 'loading'">
          <BaseLoader size="lg" class="text-primary" />
          <p class="text-base-content/70 text-sm">{{ t('auth.verify.checking') }}</p>
        </template>

        <template v-else-if="status === 'success'">
          <h2 class="text-lg font-semibold">{{ t('auth.verify.doneTitle') }}</h2>
          <p class="text-base-content/70 text-sm">
            {{ t('auth.verify.doneBody') }}
          </p>
          <button class="btn btn-primary btn-sm" @click="router.push({ name: 'login' })">
            {{ t('auth.goToLogin') }}
          </button>
        </template>

        <template v-else>
          <h2 class="text-lg font-semibold text-error">{{ t('auth.verify.failedTitle') }}</h2>
          <p class="text-base-content/70 text-sm">
            {{ t('auth.verify.failedBody') }}
          </p>
          <button class="btn btn-primary btn-sm" @click="router.push({ name: 'login' })">
            {{ t('auth.backToLogin') }}
          </button>
        </template>
      </div>
    </div>
  </div>
</template>
