import { useMagicKeys, whenever } from '@vueuse/core'
import type { MaybeRef } from 'vue'
import { ref, unref } from 'vue'

export function useKeyboardShortcut(
  keys: MaybeRef<string[]>,
  callback: () => void,
  options?: { enabled?: MaybeRef<boolean> }
) {
  const magicKeys = useMagicKeys()
  const enabled = ref(options?.enabled ?? true)

  whenever(
    () => {
      if (!unref(enabled)) return false
      return unref(keys).every((key) => {
        const k = key.toLowerCase()
        if (k === 'escape') return magicKeys.Escape
        if (k === 'enter') return magicKeys.Enter
        if (k === 'cmd' || k === 'meta') return magicKeys.meta
        if (k === 'ctrl' || k === 'control') return magicKeys.ctrl
        if (k === 'shift') return magicKeys.shift
        if (k === 'alt' || k === 'option') return magicKeys.alt
        return magicKeys[k as keyof typeof magicKeys]
      })
    },
    callback
  )

  return { enabled }
}
