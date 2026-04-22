<script setup lang="ts">
import { ref } from 'vue'
import { useGate } from '@/composables/queries/useAuthFlow'

const password = ref('')
const error = ref('')
const { mutate, isPending } = useGate()

function handleLogin() {
  error.value = ''
  if (!password.value) {
    error.value = 'Password is required'
    return
  }
  mutate(password.value, {
    onError: () => {
      error.value = 'Invalid password'
      password.value = ''
    },
  })
}
</script>

<template>
  <div class="card bg-base-200 p-8 w-full max-w-md mx-auto">
    <h1 class="text-4xl font-bold text-center mb-2 text-primary">Ziggytheque</h1>
    <p class="text-center text-base-content/70 mb-8 text-sm">Manga Collection Manager</p>

    <form class="space-y-4" @submit.prevent="handleLogin">
      <AInput
        v-model="password"
        type="password"
        label="Password"
        placeholder="Enter your password"
        :error="error"
        :disabled="isPending"
      />

      <AButton
        :loading="isPending"
        class="w-full"
        :disabled="!password"
      >
        <AIcon name="lucide:lock-open" size="sm" />
        Access
      </AButton>

      <p class="text-xs text-center text-base-content/50 pt-2">
        Enter the password to access your collection
      </p>
    </form>
  </div>
</template>
