import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { effectScope } from 'vue'
import { useLongPress } from '../useLongPress'

function touchEvent(x: number, y: number, touches = 1): TouchEvent {
  return {
    touches: Array.from({ length: touches }, () => ({ clientX: x, clientY: y })),
    cancelable: true,
    preventDefault: vi.fn(),
  } as unknown as TouchEvent
}

describe('useLongPress', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  function setup() {
    const onLongPress = vi.fn()
    const scope = effectScope()
    const press = scope.run(() => useLongPress(onLongPress, 400))!
    return { onLongPress, press, scope }
  }

  it('fires after the delay with the pressed point, then swallows the click', () => {
    const { onLongPress, press, scope } = setup()

    press.onTouchStart(touchEvent(20, 30))
    vi.advanceTimersByTime(400)
    const end = touchEvent(20, 30)
    press.onTouchEnd(end)

    expect(onLongPress).toHaveBeenCalledWith({ x: 20, y: 30 })
    expect(end.preventDefault).toHaveBeenCalled()
    expect(press.swallowClick()).toBe(true)
    expect(press.swallowClick()).toBe(false)
    scope.stop()
  })

  it('a short tap is a normal click', () => {
    const { onLongPress, press, scope } = setup()

    press.onTouchStart(touchEvent(20, 30))
    vi.advanceTimersByTime(150)
    press.onTouchEnd(touchEvent(20, 30))
    vi.advanceTimersByTime(400)

    expect(onLongPress).not.toHaveBeenCalled()
    expect(press.swallowClick()).toBe(false)
    scope.stop()
  })

  it('scrolling cancels the press', () => {
    const { onLongPress, press, scope } = setup()

    press.onTouchStart(touchEvent(20, 30))
    press.onTouchMove(touchEvent(20, 80))
    vi.advanceTimersByTime(400)

    expect(onLongPress).not.toHaveBeenCalled()
    scope.stop()
  })

  it('ignores multi-finger gestures', () => {
    const { onLongPress, press, scope } = setup()

    press.onTouchStart(touchEvent(20, 30, 2))
    vi.advanceTimersByTime(400)

    expect(onLongPress).not.toHaveBeenCalled()
    scope.stop()
  })

  it('a contextmenu during the press takes over the pending long press', () => {
    const { onLongPress, press, scope } = setup()

    press.onTouchStart(touchEvent(20, 30))
    expect(press.isTouching()).toBe(true)
    expect(press.markHandled()).toBe(true)
    vi.advanceTimersByTime(400)

    expect(onLongPress).not.toHaveBeenCalled()
    expect(press.swallowClick()).toBe(true)
    scope.stop()
  })

  it('a contextmenu after the long press fired is not handled twice', () => {
    const { press, scope } = setup()

    press.onTouchStart(touchEvent(20, 30))
    vi.advanceTimersByTime(400)

    expect(press.markHandled()).toBe(false)
    scope.stop()
  })

  it('is not touching once the finger is lifted', () => {
    const { press, scope } = setup()

    press.onTouchStart(touchEvent(20, 30))
    press.onTouchEnd(touchEvent(20, 30))

    expect(press.isTouching()).toBe(false)
    scope.stop()
  })
})
