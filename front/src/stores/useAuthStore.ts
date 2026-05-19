import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { getMe, postGate, postLogin, type User } from '@/api/auth'

interface JwtPayload {
  exp?: number
  roles?: string[]
  adminUnlocked?: boolean
}

function decodeJwt(token: string): JwtPayload | null {
  try {
    const payload = token.split('.')[1]
    return JSON.parse(atob(payload.replace(/-/g, '+').replace(/_/g, '/')))
  } catch {
    return null
  }
}

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(sessionStorage.getItem('token'))
  const user = ref<User | null>(null)

  const isAuthenticated = computed(() => token.value !== null)

  const isAdmin = computed(() => user.value?.role === 'ROLE_ADMIN')

  const isAdminUnlocked = computed(() => {
    if (token.value === null) return false
    const payload = decodeJwt(token.value)
    return payload?.adminUnlocked === true
  })

  function setToken(newToken: string): void {
    token.value = newToken
    sessionStorage.setItem('token', newToken)
  }

  async function loadUser(): Promise<void> {
    if (token.value === null) {
      user.value = null
      return
    }
    try {
      user.value = await getMe()
    } catch {
      logout()
    }
  }

  async function login(email: string, password: string): Promise<void> {
    const { token: newToken } = await postLogin(email, password)
    setToken(newToken)
    await loadUser()
  }

  async function unlockGate(password: string): Promise<void> {
    const { token: newToken } = await postGate(password)
    setToken(newToken)
  }

  function logout(): void {
    token.value = null
    user.value = null
    sessionStorage.removeItem('token')
  }

  return {
    token,
    user,
    isAuthenticated,
    isAdmin,
    isAdminUnlocked,
    setToken,
    loadUser,
    login,
    unlockGate,
    logout,
  }
})
