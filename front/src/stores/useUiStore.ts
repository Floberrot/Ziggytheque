import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useUiStore = defineStore('ui', () => {
  const isLoading = ref(false)
  const toasts = ref<{ id: string; message: string; type: 'success' | 'error' | 'info' }[]>([])

  function addToast(message: string, type: 'success' | 'error' | 'info' = 'info') {
    const id = crypto.randomUUID()
    toasts.value.push({ id, message, type })
    setTimeout(() => removeToast(id), 3500)
  }

  function removeToast(id: string) {
    toasts.value = toasts.value.filter((t) => t.id !== id)
  }

  return { isLoading, toasts, addToast, removeToast }
})
