import { defineStore } from 'pinia'
import { ref } from 'vue'

export type ToastVariant = 'success' | 'error' | 'info' | 'progress'

export interface Toast {
  id: string
  message: string
  type: ToastVariant
  progress?: { current: number; total: number }
  persistent?: boolean
}

export const useUiStore = defineStore('ui', () => {
  const isLoading = ref(false)
  const toasts = ref<Toast[]>([])

  function addToast(message: string, type: 'success' | 'error' | 'info' = 'info') {
    const id = crypto.randomUUID()
    toasts.value.push({ id, message, type })
    setTimeout(() => removeToast(id), 3500)
  }

  function addProgressToast(message: string, total: number): string {
    const id = crypto.randomUUID()
    toasts.value.push({ id, message, type: 'progress', progress: { current: 0, total }, persistent: true })
    return id
  }

  function updateProgressToast(id: string, message: string, current: number, total: number): void {
    const toast = toasts.value.find((t) => t.id === id)
    if (!toast) return
    toast.message = message
    toast.progress = { current, total }
  }

  function closeProgressToast(id: string, finalMessage: string, type: ToastVariant = 'success'): void {
    const toast = toasts.value.find((t) => t.id === id)
    if (!toast) return
    toast.message = finalMessage
    toast.type = type
    toast.persistent = false
    setTimeout(() => removeToast(id), 3500)
  }

  function removeToast(id: string) {
    toasts.value = toasts.value.filter((t) => t.id !== id)
  }

  return { isLoading, toasts, addToast, addProgressToast, updateProgressToast, closeProgressToast, removeToast }
})
