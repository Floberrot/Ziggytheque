export interface ScreenPoint {
  x: number
  y: number
}

/**
 * Where a context menu opens: at the pointer — or, opened from the keyboard (Menu key,
 * Shift+F10), which reports no pointer position, at the centre of the focused element.
 */
export function contextMenuPoint(event: MouseEvent): ScreenPoint {
  const element = event.currentTarget
  const fromKeyboard = event.clientX === 0 && event.clientY === 0
  if (!fromKeyboard || !(element instanceof Element)) {
    return { x: event.clientX, y: event.clientY }
  }
  const rect = element.getBoundingClientRect()
  return { x: rect.left + rect.width / 2, y: rect.top + rect.height / 2 }
}
