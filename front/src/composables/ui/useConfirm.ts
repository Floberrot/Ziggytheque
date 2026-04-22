import { ref } from 'vue'

interface ConfirmOptions {
  title: string
  description?: string
  confirmText?: string
  cancelText?: string
  danger?: boolean
}

let confirmResolver: ((value: boolean) => void) | null = null

const confirmDialog = ref<{
  open: boolean
  options: ConfirmOptions | null
}>({
  open: false,
  options: null,
})

export function useConfirm() {
  return {
    confirm: (options: ConfirmOptions): Promise<boolean> => {
      return new Promise((resolve) => {
        confirmResolver = resolve
        confirmDialog.value = {
          open: true,
          options,
        }
      })
    },
    confirmDialog,
    confirmAction: () => {
      if (confirmResolver) {
        confirmResolver(true)
        confirmResolver = null
      }
      confirmDialog.value.open = false
    },
    cancelAction: () => {
      if (confirmResolver) {
        confirmResolver(false)
        confirmResolver = null
      }
      confirmDialog.value.open = false
    },
  }
}
