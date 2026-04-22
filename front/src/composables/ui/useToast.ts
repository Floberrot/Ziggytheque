type ToastType = 'success' | 'error' | 'info' | 'warning'

export function useToast() {
  function show(message: string, type: ToastType = 'info') {
    console.log(`Toast [${type}]: ${message}`)
  }

  return {
    success: (message: string) => show(message, 'success'),
    error: (message: string) => show(message, 'error'),
    info: (message: string) => show(message, 'info'),
    warning: (message: string) => show(message, 'warning'),
  }
}
