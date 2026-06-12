import * as THREE from 'three'

// Shared book-rendering helpers. The colour + synthetic-spine functions were
// originally inline in ShelfPage; they live here so the single-volume
// Volume3DViewer renders an identical fallback look to the 3D shelf.

/** Single-volume viewer book proportions — a tankōbon ≈ 1 : 1.5, ~13 % thick. */
export const VIEWER_BOOK = { width: 1.0, height: 1.5, depth: 0.17 } as const

/** Deterministic, pleasant colour (0xRRGGBB) from an arbitrary string. */
export function seedColorInt(str: string): number {
  let hash = 5381
  for (let i = 0; i < str.length; i++) {
    hash = ((hash << 5) + hash) ^ str.charCodeAt(i)
    hash |= 0
  }
  return hslToInt((Math.abs(hash) % 360) / 360, 0.68, 0.46)
}

export function hslToInt(h: number, s: number, l: number): number {
  const a = s * Math.min(l, 1 - l)
  const f = (n: number): number => {
    const k = (n + h * 12) % 12
    return l - a * Math.max(Math.min(k - 3, 9 - k, 1), -1)
  }
  return (Math.round(f(0) * 255) << 16) | (Math.round(f(8) * 255) << 8) | Math.round(f(4) * 255)
}

/** Synthetic spine: publisher band + vertical title + volume number. */
export function createSpineTexture(
  edition: string | null,
  title: string,
  volume: number,
  colorInt: number,
): THREE.CanvasTexture {
  const W = 48
  const H = 400
  const canvas = document.createElement('canvas')
  canvas.width = W
  canvas.height = H
  const ctx = canvas.getContext('2d')!

  const r = (colorInt >> 16) & 0xff
  const g = (colorInt >> 8) & 0xff
  const b = colorInt & 0xff
  const dark = `rgb(${Math.round(r * 0.45)},${Math.round(g * 0.45)},${Math.round(b * 0.45)})`
  const mid = `rgb(${r},${g},${b})`
  const light = `rgb(${Math.min(255, Math.round(r * 1.3))},${Math.min(255, Math.round(g * 1.3))},${Math.min(255, Math.round(b * 1.3))})`

  const grad = ctx.createLinearGradient(0, 0, 0, H)
  grad.addColorStop(0, dark)
  grad.addColorStop(0.15, mid)
  grad.addColorStop(0.85, mid)
  grad.addColorStop(1, dark)
  ctx.fillStyle = grad
  ctx.fillRect(0, 0, W, H)

  ctx.fillStyle = dark
  ctx.fillRect(0, 0, W, 40)
  ctx.fillRect(0, H - 50, W, 50)

  ctx.fillStyle = light
  ctx.fillRect(0, 40, W, 2)
  ctx.fillRect(0, H - 52, W, 2)

  if (edition) {
    const short = edition.length > 8 ? edition.substring(0, 7) + '…' : edition
    ctx.fillStyle = 'rgba(255,255,255,0.85)'
    ctx.font = 'bold 10px sans-serif'
    ctx.textAlign = 'center'
    ctx.textBaseline = 'middle'
    ctx.fillText(short, W / 2, 20)
  }

  const shortTitle = title.length > 18 ? title.substring(0, 17) + '…' : title
  ctx.save()
  ctx.translate(W / 2, H / 2 - 10)
  ctx.rotate(Math.PI / 2)
  ctx.font = '12px sans-serif'
  ctx.fillStyle = 'rgba(255,255,255,0.82)'
  ctx.textAlign = 'center'
  ctx.textBaseline = 'middle'
  ctx.fillText(shortTitle, 0, 0)
  ctx.restore()

  ctx.fillStyle = 'rgba(255,255,255,0.95)'
  ctx.font = 'bold 22px sans-serif'
  ctx.textAlign = 'center'
  ctx.textBaseline = 'middle'
  ctx.fillText(String(volume), W / 2, H - 25)

  const texture = new THREE.CanvasTexture(canvas)
  texture.needsUpdate = true
  return texture
}

/**
 * Synthetic 4th-of-cover (back) texture, used until a real back-cover photo is
 * captured: a dark wash of the dominant colour + a faint "summary" panel + a
 * procedural barcode, so the rear face reads as a book back, not a flat colour.
 */
export function createBackTexture(colorInt: number): THREE.CanvasTexture {
  const W = 300
  const H = 450
  const canvas = document.createElement('canvas')
  canvas.width = W
  canvas.height = H
  const ctx = canvas.getContext('2d')!

  const r = (colorInt >> 16) & 0xff
  const g = (colorInt >> 8) & 0xff
  const b = colorInt & 0xff
  const dark = `rgb(${Math.round(r * 0.32)},${Math.round(g * 0.32)},${Math.round(b * 0.32)})`
  const darker = `rgb(${Math.round(r * 0.2)},${Math.round(g * 0.2)},${Math.round(b * 0.2)})`

  const grad = ctx.createLinearGradient(0, 0, 0, H)
  grad.addColorStop(0, dark)
  grad.addColorStop(1, darker)
  ctx.fillStyle = grad
  ctx.fillRect(0, 0, W, H)

  // Publisher band at the very top.
  ctx.fillStyle = 'rgba(255,255,255,0.14)'
  ctx.fillRect(0, 0, W, 5)

  // Faint blurb lines, like a back-cover summary.
  ctx.fillStyle = 'rgba(255,255,255,0.10)'
  const pad = 28
  for (let i = 0; i < 7; i++) {
    const lineWidth = (i === 6 ? 0.5 : 0.84) * (W - pad * 2)
    ctx.fillRect(pad, 64 + i * 22, lineWidth, 7)
  }

  // White barcode block, bottom-right.
  const bcW = 104
  const bcH = 60
  const bcX = W - bcW - 24
  const bcY = H - bcH - 28
  ctx.fillStyle = '#ffffff'
  ctx.fillRect(bcX, bcY, bcW, bcH)
  ctx.fillStyle = '#111111'
  let x = bcX + 9
  while (x < bcX + bcW - 9) {
    const barWidth = 1 + Math.round((Math.sin(x * 12.9898) * 0.5 + 0.5) * 3)
    ctx.fillRect(x, bcY + 9, barWidth, bcH - 24)
    x += barWidth + 2
  }
  ctx.font = 'bold 8px monospace'
  ctx.textAlign = 'center'
  ctx.textBaseline = 'alphabetic'
  ctx.fillText('I S B N', bcX + bcW / 2, bcY + bcH - 6)

  const texture = new THREE.CanvasTexture(canvas)
  texture.needsUpdate = true
  return texture
}
