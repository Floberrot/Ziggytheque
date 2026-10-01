import { onScopeDispose } from 'vue'

export interface PressPoint {
  x: number
  y: number
}

/** A finger that drifts further than this is scrolling, not pressing. */
const MOVE_TOLERANCE_PX = 10

/**
 * Long press for touch screens — the right click of a phone (iOS has no
 * `contextmenu` event). The click the browser synthesises after a long press is
 * swallowed, so the card under the finger does not open as well.
 */
export function useLongPress(onLongPress: (point: PressPoint) => void, delayMs = 480) {
  let timer: ReturnType<typeof setTimeout> | null = null
  let origin: PressPoint | null = null
  let fired = false
  let touching = false

  function cancel(): void {
    if (timer) clearTimeout(timer)
    timer = null
  }

  function onTouchStart(event: TouchEvent): void {
    fired = false
    touching = true
    cancel()
    const touch = event.touches[0]
    if (!touch || event.touches.length > 1) return
    const point = { x: touch.clientX, y: touch.clientY }
    origin = point
    timer = setTimeout(() => {
      timer = null
      fired = true
      navigator.vibrate?.(25)
      onLongPress(point)
    }, delayMs)
  }

  function onTouchMove(event: TouchEvent): void {
    const touch = event.touches[0]
    if (!timer || !origin || !touch) return
    if (Math.hypot(touch.clientX - origin.x, touch.clientY - origin.y) > MOVE_TOLERANCE_PX) cancel()
  }

  function onTouchEnd(event: TouchEvent): void {
    touching = false
    cancel()
    // No synthetic click after a long press.
    if (fired && event.cancelable) event.preventDefault()
  }

  function onTouchCancel(): void {
    touching = false
    cancel()
  }

  /** A `contextmenu` event while a finger is down comes from a long press, not a mouse. */
  function isTouching(): boolean {
    return touching
  }

  /**
   * Android also reports a long press as `contextmenu`: whichever comes first opens
   * the menu. False when the long press already did.
   */
  function markHandled(): boolean {
    cancel()
    if (fired) return false
    fired = true
    return true
  }

  /** True — once — for the click that ends a long press. */
  function swallowClick(): boolean {
    if (!fired) return false
    fired = false
    return true
  }

  onScopeDispose(cancel)

  return { onTouchStart, onTouchMove, onTouchEnd, onTouchCancel, isTouching, markHandled, swallowClick }
}
