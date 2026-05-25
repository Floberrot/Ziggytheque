import axios from 'axios'
import { useAuthStore } from '@/stores/useAuthStore'

const client = axios.create({
  baseURL: '/api',
  headers: { 'Content-Type': 'application/json' },
})

client.interceptors.request.use((config) => {
  const auth = useAuthStore()
  if (auth.token) {
    config.headers.Authorization = `Bearer ${auth.token}`
  }
  return config
})

const AUTH_ENDPOINTS_SKIPPING_LOGOUT = ['/auth/gate', '/auth/login']

client.interceptors.response.use(
  (res) => res,
  (err) => {
    const requestUrl = err.config?.url ?? ''
    const isAuthAttempt = AUTH_ENDPOINTS_SKIPPING_LOGOUT.some((endpoint) =>
      requestUrl.endsWith(endpoint),
    )
    if (err.response?.status === 401 && !isAuthAttempt) {
      const auth = useAuthStore()
      auth.logout()
      window.location.href = '/login'
    }
    return Promise.reject(err)
  },
)

export default client
