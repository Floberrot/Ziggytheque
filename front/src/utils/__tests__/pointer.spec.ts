import { describe, it, expect, vi } from 'vitest'
import { contextMenuPoint } from '../pointer'

function contextMenuOn(element: HTMLElement, clientX: number, clientY: number): MouseEvent {
  const event = new MouseEvent('contextmenu', { clientX, clientY })
  Object.defineProperty(event, 'currentTarget', { value: element })
  return event
}

describe('contextMenuPoint', () => {
  it('opens at the pointer for a right click', () => {
    const element = document.createElement('div')

    expect(contextMenuPoint(contextMenuOn(element, 120, 340))).toEqual({ x: 120, y: 340 })
  })

  it('opens at the centre of the focused element when the keyboard opened it', () => {
    const element = document.createElement('div')
    vi.spyOn(element, 'getBoundingClientRect').mockReturnValue(
      { left: 100, top: 200, width: 60, height: 90 } as DOMRect,
    )

    expect(contextMenuPoint(contextMenuOn(element, 0, 0))).toEqual({ x: 130, y: 245 })
  })
})
