<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/useAuthStore'
import { useToast } from '@/composables/ui/useToast'
import { postGate } from '@/api/auth'
import AInput from '@/components/atoms/AInput.vue'
import AButton from '@/components/atoms/AButton.vue'
import AIcon from '@/components/atoms/AIcon.vue'

const router = useRouter()
const auth = useAuthStore()
const toast = useToast()

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
    toast.success('Welcome!')
    await router.push({ name: 'dashboard' })
  } catch {
    error.value = 'Invalid password'
    toast.error('Invalid password')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="w-full rounded-xl bg-base-100 border border-base-300 p-8 shadow-lg">
    <!-- Header -->
    <div class="text-center mb-8">
      <div class="flex items-center justify-center gap-2 mb-3">
        <AIcon name="lucide:book-open" size="lg" class="text-primary" />
        <h1 class="text-display font-bold text-primary">Ziggytheque</h1>
      </div>
      <p class="text-base-content/60">Your manga collection vault</p>
    </div>

    <!-- Form -->
    <form class="space-y-4" @submit.prevent="submit">
      <AInput
        v-model="password"
        type="password"
        placeholder="Access password"
        :error="error"
        autofocus
        @keydown.enter="submit"
      />

      <AButton
        type="submit"
        variant="primary"
        size="md"
        class="w-full"
        :loading="loading"
        :disabled="loading"
      >
        Enter Collection
      </AButton>
    </form>
  </div>
</template>
