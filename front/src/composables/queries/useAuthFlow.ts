import { useMutation } from '@tanstack/vue-query'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/useAuthStore'
import * as api from '@/api/auth'

export function useGate() {
  const authStore = useAuthStore()

  return useMutation({
    mutationFn: (password: string) => api.postGate(password),
    onSuccess: (data: any) => {
      authStore.setToken(data.token)
    },
  })
}

export function useLogout() {
  const authStore = useAuthStore()
  const router = useRouter()

  return useMutation({
    mutationFn: async () => {
      authStore.logout()
      return Promise.resolve()
    },
    onSuccess: () => {
      router.push('/gate')
    },
  })
}
