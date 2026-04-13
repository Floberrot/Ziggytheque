import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(sessionStorage.getItem('token'))

  const isAuthenticated = computed(() => token.value !== null)

  function setToken(newToken: string) {
    token.value = newToken
    sessionStorage.setItem('token', newToken)
  }

  function logout() {
    token.value = null
    sessionStorage.removeItem('token')
  }

  return { token, isAuthenticated, setToken, logout }
})
