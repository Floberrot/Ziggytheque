<script setup lang="ts">
import { ref } from 'vue'
import { useGate } from '@/composables/queries/useAuthFlow'

const password = ref('')
const { mutate, isPending } = useGate()

function handleLogin() {
  mutate(password.value)
}
</script>

<template>
  <div class="card bg-base-200 p-8">
    <h1 class="display mb-6 text-center">Ziggytheque</h1>
    <form class="space-y-4" @submit.prevent="handleLogin">
      <AInput
        v-model="password"
        type="password"
        placeholder="Password"
        label="Entrez le mot de passe"
      />
      <AButton :loading="isPending" class="w-full">
        Accéder
      </AButton>
    </form>
  </div>
</template>
