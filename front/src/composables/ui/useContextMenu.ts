import { ref } from 'vue'

export interface ContextMenuOption {
  label: string
  icon?: string
  action: () => void
  divider?: boolean
}

export function useContextMenu() {
  const isOpen = ref(false)
  const x = ref(0)
  const y = ref(0)

  function open(event: MouseEvent) {
    event.preventDefault()
    x.value = event.clientX
    y.value = event.clientY
    isOpen.value = true
  }

  function close() {
    isOpen.value = false
  }

  return {
    isOpen,
    x,
    y,
    open,
    close,
  }
}
