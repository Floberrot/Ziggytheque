import { ref } from 'vue'

export interface ConfirmDialog {
  title: string
  description?: string
  confirmText?: string
  cancelText?: string
}

const isOpen = ref(false)
const dialog = ref<ConfirmDialog>({
  title: '',
})
let resolvePromise: ((value: boolean) => void) | null = null

export function useConfirm() {
  function confirm(options: ConfirmDialog): Promise<boolean> {
    return new Promise((resolve) => {
      dialog.value = options
      isOpen.value = true
      resolvePromise = resolve
    })
  }

  function handleConfirm() {
    if (resolvePromise) {
      resolvePromise(true)
      resolvePromise = null
    }
    isOpen.value = false
  }

  function handleCancel() {
    if (resolvePromise) {
      resolvePromise(false)
      resolvePromise = null
    }
    isOpen.value = false
  }

  return {
    confirm,
    handleConfirm,
    handleCancel,
    isOpen,
    dialog,
  }
}
