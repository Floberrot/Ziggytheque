<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/useAuthStore'
import { postGate } from '@/api/auth'

const router = useRouter()
const auth = useAuthStore()

const password = ref('')
const error = ref('')
const loading = ref(false)

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
        <div class="text-center">
          <h1 class="text-3xl font-bold tracking-tight">Ziggytheque</h1>
          <p class="text-base-content/60 mt-1 text-sm">Your manga collection</p>
        </div>

        <form class="flex flex-col gap-4" @submit.prevent="submit">
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
      </div>
    </div>
  </div>
</template>
