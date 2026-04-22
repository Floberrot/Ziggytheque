import { useMutation } from '@tanstack/vue-query'
import { authClient } from '@/api/auth'
import { useAuthStore } from '@/stores/useAuthStore'
import { useToast } from '../ui/useToast'

export function useGate() {
  const auth = useAuthStore()
  const toast = useToast()

  return useMutation({
    mutationFn: (password: string) => authClient.gate(password),
    onSuccess: (data) => {
      auth.setToken(data.token)
      toast.success('Welcome!')
    },
    onError: () => {
      toast.error('Invalid password')
    },
  })
}

export function useLogout() {
  const auth = useAuthStore()
  return {
    logout: () => {
      auth.logout()
    },
  }
}
