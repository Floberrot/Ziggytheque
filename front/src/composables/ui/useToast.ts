import { useUiStore } from '@/stores/useUiStore'

type ToastType = 'success' | 'error' | 'info' | 'warning'

export function useToast() {
  const ui = useUiStore()

  return {
    success: (message: string) => ui.addToast({ type: 'success', message }),
    error: (message: string) => ui.addToast({ type: 'error', message }),
    info: (message: string) => ui.addToast({ type: 'info', message }),
    warning: (message: string) => ui.addToast({ type: 'warning', message }),
  }
}
