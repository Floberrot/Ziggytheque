import { defineStore } from 'pinia'
import { ref } from 'vue'

interface Toast {
  id: string
  message: string
  type: 'success' | 'error' | 'info' | 'warning'
}

export const useUiStore = defineStore('ui', () => {
  const isLoading = ref(false)
  const toasts = ref<Toast[]>([])

  function addToast(options: { message: string; type?: 'success' | 'error' | 'info' | 'warning' }) {
    const id = crypto.randomUUID()
    toasts.value.push({ id, message: options.message, type: options.type || 'info' })
    setTimeout(() => removeToast(id), 3500)
  }

  function removeToast(id: string) {
    toasts.value = toasts.value.filter((t) => t.id !== id)
  }

  return { isLoading, toasts, addToast, removeToast }
})
