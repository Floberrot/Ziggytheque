<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/useAuthStore'
import { postGate } from '@/api/auth'
import BaseLogo from '@/components/atoms/BaseLogo.vue'
import BaseTypewriter from '@/components/atoms/BaseTypewriter.vue'

const router = useRouter()
const auth = useAuthStore()

const password = ref('')
const error = ref('')
const loading = ref(false)
const titleDone = ref(false)

async function submit() {
  if (!password.value.trim()) return
  error.value = ''
  loading.value = true
  try {
    const { token } = await postGate(password.value)
    auth.setToken(token)
    await router.push({ name: 'dashboard' })
  } catch {
    error.value = 'Invalid password.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-base-200">
    <div class="card w-full max-w-sm shadow-2xl bg-base-100">
      <div class="card-body gap-6">

        <!-- Header: icon shown immediately, text types out -->
        <div class="flex flex-col items-center gap-3">
          <BaseLogo size="lg" />
          <h1 class="text-2xl font-bold tracking-tight">
            <BaseTypewriter text="Ziggytheque." :speed="70" @complete="titleDone = true" />
          </h1>
          <p
            class="text-base-content/60 text-sm transition-opacity duration-500"
            :class="titleDone ? 'opacity-100' : 'opacity-0'"
          >
            Your manga collection
          </p>
        </div>

        <Transition
          enter-active-class="transition-all duration-500 ease-out"
          enter-from-class="opacity-0 translate-y-4"
        >
          <form v-if="titleDone" class="flex flex-col gap-4" @submit.prevent="submit">
            <div class="form-control">
              <input
                v-model="password"
                type="password"
                placeholder="Access password"
                class="input input-bordered w-full"
                :class="{ 'input-error': error }"
                autofocus
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
              Enter
            </button>
          </form>
        </Transition>

      </div>
    </div>
  </div>
</template>
