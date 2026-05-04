<script setup lang="ts">
import { ref, shallowRef, watch, onMounted, onUnmounted, computed } from 'vue'
import * as THREE from 'three'
import { OrbitControls } from 'three/addons/controls/OrbitControls.js'
import { useQuery } from '@tanstack/vue-query'
import { getShelf, type ShelfCollection } from '@/api/shelf'
import { coverUrl } from '@/utils/coverUrl'
import { X } from 'lucide-vue-next'

// ── Reactive state ────────────────────────────────────────────────────────────

interface SelectedBook {
  mangaTitle: string
  number: number
  coverUrl: string | null
}

const containerRef = ref<HTMLDivElement>()
const canvasRef = ref<HTMLCanvasElement>()
const selectedBook = ref<SelectedBook | null>(null)
const isInitialized = shallowRef(false)

// ── Data ──────────────────────────────────────────────────────────────────────

const { data: shelfData, isLoading, error } = useQuery({
  queryKey: ['shelf'],
  queryFn: getShelf,
})

const hasBooks = computed(() => shelfData.value && shelfData.value.length > 0)

// ── Three.js internals ────────────────────────────────────────────────────────

let renderer: THREE.WebGLRenderer | null = null
let scene: THREE.Scene | null = null
let camera: THREE.PerspectiveCamera | null = null
let controls: OrbitControls | null = null
let animFrameId = 0

const bookMap = new Map<THREE.Object3D, SelectedBook>()
const originalZ = new Map<THREE.Object3D, number>()

let hoveredMesh: THREE.Object3D | null = null
const animating = new Map<THREE.Object3D, { originZ: number; originRotY: number }>()
const PULL_OUT = 0.55
const LERP_SPEED = 0.14

const raycaster = new THREE.Raycaster()
const pointer = new THREE.Vector2()

function containerSize(): { width: number; height: number } {
  return containerRef.value
    ? { width: containerRef.value.clientWidth, height: containerRef.value.clientHeight }
    : { width: window.innerWidth, height: window.innerHeight }
}

function disposeScene(): void {
  if (animFrameId) cancelAnimationFrame(animFrameId)
  controls?.dispose()
  renderer?.dispose()
  renderer = null
  scene = null
  camera = null
  controls = null
  bookMap.clear()
  originalZ.clear()
  animating.clear()
  hoveredMesh = null
  isInitialized.value = false
}

// ── Color helpers ─────────────────────────────────────────────────────────────

function seedColorInt(str: string): number {
  let hash = 5381
  for (let i = 0; i < str.length; i++) {
    hash = ((hash << 5) + hash) ^ str.charCodeAt(i)
    hash |= 0
  }
  return hslToInt((Math.abs(hash) % 360) / 360, 0.68, 0.46)
}

function hslToInt(h: number, s: number, l: number): number {
  const a = s * Math.min(l, 1 - l)
  const f = (n: number): number => {
    const k = (n + h * 12) % 12
    return l - a * Math.max(Math.min(k - 3, 9 - k, 1), -1)
  }
  return (Math.round(f(0) * 255) << 16) | (Math.round(f(8) * 255) << 8) | Math.round(f(4) * 255)
}

// ── Spine texture ─────────────────────────────────────────────────────────────

function createSpineTexture(
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

// ── Scene constants ───────────────────────────────────────────────────────────

const BOOK_H = 1.55
const BOOK_THICK = 0.18
const BOOK_DEPTH = 0.85
const BOOK_GAP = 0.015
const GROUP_GAP = 0.22
const SHELF_USABLE_W = 13.6
const SHELF_LEFT_X = -6.8
const SHELF_Z = -4.0
const SHELF_ROW_BOTTOMS = [0.18, 2.28, 4.38, 6.48]
const SPINE_FACE_Z = SHELF_Z + 0.75 - 0.01

// ── Room ──────────────────────────────────────────────────────────────────────

function buildRoom(sceneObj: THREE.Scene): void {
  const floor = new THREE.Mesh(new THREE.PlaneGeometry(40, 40), new THREE.MeshLambertMaterial({ color: 0x4a2e10 }))
  floor.rotation.x = -Math.PI / 2
  floor.receiveShadow = true
  sceneObj.add(floor)

  const rugBorder = new THREE.Mesh(new THREE.PlaneGeometry(12.4, 8.4), new THREE.MeshLambertMaterial({ color: 0x5c2e18 }))
  rugBorder.rotation.x = -Math.PI / 2
  rugBorder.position.set(0, 0.003, 4)
  sceneObj.add(rugBorder)

  const rug = new THREE.Mesh(new THREE.PlaneGeometry(12, 8), new THREE.MeshLambertMaterial({ color: 0x7b3f2a }))
  rug.rotation.x = -Math.PI / 2
  rug.position.set(0, 0.005, 4)
  rug.receiveShadow = true
  sceneObj.add(rug)

  const backWall = new THREE.Mesh(new THREE.PlaneGeometry(40, 20), new THREE.MeshLambertMaterial({ color: 0xeedd99 }))
  backWall.position.set(0, 10, -8)
  backWall.receiveShadow = true
  sceneObj.add(backWall)

  const leftWall = new THREE.Mesh(new THREE.PlaneGeometry(20, 20), new THREE.MeshLambertMaterial({ color: 0xe8d0c0 }))
  leftWall.rotation.y = Math.PI / 2
  leftWall.position.set(-14, 10, 2)
  leftWall.receiveShadow = true
  sceneObj.add(leftWall)

  const rightWall = new THREE.Mesh(new THREE.PlaneGeometry(20, 20), new THREE.MeshLambertMaterial({ color: 0xe8d0c0 }))
  rightWall.rotation.y = -Math.PI / 2
  rightWall.position.set(14, 10, 2)
  rightWall.receiveShadow = true
  sceneObj.add(rightWall)

  const ceil = new THREE.Mesh(new THREE.PlaneGeometry(40, 20), new THREE.MeshLambertMaterial({ color: 0xf5ead8 }))
  ceil.rotation.x = Math.PI / 2
  ceil.position.set(0, 12, 2)
  sceneObj.add(ceil)

  // Window on left wall
  const windowGlow = new THREE.Mesh(new THREE.PlaneGeometry(4.2, 4.2), new THREE.MeshBasicMaterial({ color: 0xfff3cc }))
  windowGlow.rotation.y = Math.PI / 2
  windowGlow.position.set(-13.9, 7, -2)
  sceneObj.add(windowGlow)

  const frameMat = new THREE.MeshLambertMaterial({ color: 0x5a3415 })
  ;([
    [new THREE.BoxGeometry(0.1, 0.15, 4.6), -13.87, 9.15, -2, 0],
    [new THREE.BoxGeometry(0.1, 0.15, 4.6), -13.87, 4.85, -2, 0],
    [new THREE.BoxGeometry(0.1, 4.4, 0.15), -13.87, 7, -4.2, 0],
    [new THREE.BoxGeometry(0.1, 4.4, 0.15), -13.87, 7, 0.2, 0],
  ] as [THREE.BufferGeometry, number, number, number, number][]).forEach(([geo, x, y, z]) => {
    const m = new THREE.Mesh(geo, frameMat)
    m.rotation.y = Math.PI / 2
    m.position.set(x, y, z)
    sceneObj.add(m)
  })

  // Curtains
  const curtainMat = new THREE.MeshLambertMaterial({ color: 0x6b3040, side: THREE.DoubleSide })
  const curtainL = new THREE.Mesh(new THREE.PlaneGeometry(2.0, 6), curtainMat)
  curtainL.rotation.y = Math.PI / 2
  curtainL.position.set(-13.85, 7.0, -4.8)
  sceneObj.add(curtainL)
  const curtainR = new THREE.Mesh(new THREE.PlaneGeometry(2.0, 6), curtainMat)
  curtainR.rotation.y = Math.PI / 2
  curtainR.position.set(-13.85, 7.0, 0.8)
  sceneObj.add(curtainR)

  const curtainRod = new THREE.Mesh(
    new THREE.CylinderGeometry(0.04, 0.04, 8, 8),
    new THREE.MeshPhongMaterial({ color: 0x3d1c02, shininess: 60 }),
  )
  curtainRod.rotation.x = Math.PI / 2
  curtainRod.position.set(-13.87, 9.4, -2)
  sceneObj.add(curtainRod)

  // Baseboards
  const baseboardMat = new THREE.MeshLambertMaterial({ color: 0x9e7c50 })
  const baseboardBack = new THREE.Mesh(new THREE.BoxGeometry(40, 0.22, 0.06), baseboardMat)
  baseboardBack.position.set(0, 0.11, -7.97)
  sceneObj.add(baseboardBack)
  const baseboardRight = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.22, 20), baseboardMat)
  baseboardRight.position.set(13.97, 0.11, 2)
  sceneObj.add(baseboardRight)
  const baseboardLeft = new THREE.Mesh(new THREE.BoxGeometry(0.06, 0.22, 20), baseboardMat)
  baseboardLeft.position.set(-13.97, 0.11, 2)
  sceneObj.add(baseboardLeft)
}

// ── Shelf ─────────────────────────────────────────────────────────────────────

function buildShelf(sceneObj: THREE.Scene): void {
  const wood = new THREE.MeshPhongMaterial({ color: 0x7a4520, shininess: 25 })
  const darkWood = new THREE.MeshPhongMaterial({ color: 0x5c3210, shininess: 10 })

  const back = new THREE.Mesh(new THREE.BoxGeometry(14.3, 8.3, 0.08), darkWood)
  back.position.set(0, 4.15, SHELF_Z - 0.71)
  back.receiveShadow = true
  sceneObj.add(back)

  ;([-7.15, 7.15] as const).forEach((x) => {
    const panel = new THREE.Mesh(new THREE.BoxGeometry(0.18, 8.3, 1.5), wood)
    panel.position.set(x, 4.15, SHELF_Z)
    panel.castShadow = true
    panel.receiveShadow = true
    sceneObj.add(panel)
  })

  ;([8.15, 0.09, 2.18, 4.28, 6.38] as const).forEach((y, i) => {
    const w = i < 2 ? 14.3 : 13.7
    const h = i < 2 ? 0.18 : 0.12
    const plank = new THREE.Mesh(new THREE.BoxGeometry(w, h, 1.5), wood)
    plank.position.set(0, y, SHELF_Z)
    plank.castShadow = true
    plank.receiveShadow = true
    sceneObj.add(plank)
  })
}

// ── Plants ────────────────────────────────────────────────────────────────────

function buildPlants(sceneObj: THREE.Scene): void {
  const potMat   = new THREE.MeshLambertMaterial({ color: 0xc1440e })
  const potMat2  = new THREE.MeshLambertMaterial({ color: 0xb5895a })
  const potMat3  = new THREE.MeshLambertMaterial({ color: 0x7a5c3a })
  const soilMat  = new THREE.MeshLambertMaterial({ color: 0x3a200a })
  const leafA    = new THREE.MeshLambertMaterial({ color: 0x2d5016 })
  const leafB    = new THREE.MeshLambertMaterial({ color: 0x3d6e22 })
  const leafC    = new THREE.MeshLambertMaterial({ color: 0x1e4510 })
  const vineLeaf  = new THREE.MeshLambertMaterial({ color: 0x2a7a18 })
  const vineLeaf2 = new THREE.MeshLambertMaterial({ color: 0x4a9e28 })

  function addPlant(x: number, z: number, scale: number, count: number, mat: THREE.Material = potMat): void {
    const h = 0.7 * scale
    const pot = new THREE.Mesh(new THREE.CylinderGeometry(0.45 * scale, 0.32 * scale, h, 10), mat)
    pot.position.set(x, h / 2, z)
    pot.castShadow = true
    sceneObj.add(pot)
    const soil = new THREE.Mesh(new THREE.CylinderGeometry(0.43 * scale, 0.43 * scale, 0.05, 10), soilMat)
    soil.position.set(x, h, z)
    sceneObj.add(soil)
    for (let i = 0; i < count; i++) {
      const angle = (i / count) * Math.PI * 2
      const rLeaf = (0.25 + (i % 3) * 0.08) * scale
      const size  = (0.5  + (i % 3) * 0.15) * scale
      const lMat  = i % 3 === 0 ? leafA : i % 3 === 1 ? leafB : leafC
      const leaf  = new THREE.Mesh(new THREE.SphereGeometry(size, 7, 5), lMat)
      leaf.scale.set(0.9, 0.65 + (i % 2) * 0.3, 0.9)
      leaf.position.set(x + Math.cos(angle) * rLeaf, h + size * 0.5 + i * 0.12 * scale, z + Math.sin(angle) * rLeaf)
      leaf.castShadow = true
      sceneObj.add(leaf)
    }
  }

  // Tall snake-plant style (narrow leaves pointing up)
  function addSnakePlant(x: number, z: number, scale: number): void {
    const pot = new THREE.Mesh(new THREE.CylinderGeometry(0.38 * scale, 0.28 * scale, 0.55 * scale, 10), potMat3)
    pot.position.set(x, 0.275 * scale, z)
    pot.castShadow = true
    sceneObj.add(pot)
    const soil = new THREE.Mesh(new THREE.CylinderGeometry(0.36 * scale, 0.36 * scale, 0.05, 10), soilMat)
    soil.position.set(x, 0.55 * scale, z)
    sceneObj.add(soil)
    const leafCount = 6
    for (let i = 0; i < leafCount; i++) {
      const angle = (i / leafCount) * Math.PI * 2 + 0.2
      const lean  = 0.12 + (i % 3) * 0.06
      const height = (1.4 + (i % 2) * 0.5) * scale
      const leaf = new THREE.Mesh(
        new THREE.BoxGeometry(0.08 * scale, height, 0.22 * scale),
        i % 2 === 0 ? leafA : leafB,
      )
      leaf.position.set(
        x + Math.cos(angle) * lean * scale,
        0.55 * scale + height / 2,
        z + Math.sin(angle) * lean * scale,
      )
      leaf.rotation.z = Math.cos(angle) * 0.15
      leaf.rotation.x = Math.sin(angle) * 0.15
      sceneObj.add(leaf)
    }
  }

  // Hanging plant suspended from ceiling
  function addHangingPlant(x: number, ceilY: number, z: number, scale: number): void {
    const ropeMat = new THREE.MeshLambertMaterial({ color: 0x8b7050 })
    const potY = ceilY - 2.5 * scale
    // Three rope strands
    for (let i = 0; i < 3; i++) {
      const angle = (i / 3) * Math.PI * 2
      const rx = Math.cos(angle) * 0.18 * scale
      const rz = Math.sin(angle) * 0.18 * scale
      const ropeLen = ceilY - potY - 0.1
      const rope = new THREE.Mesh(new THREE.CylinderGeometry(0.018, 0.018, ropeLen, 4), ropeMat)
      rope.position.set(x + rx, potY + 0.1 + ropeLen / 2, z + rz)
      sceneObj.add(rope)
    }
    // Pot
    const pot = new THREE.Mesh(new THREE.CylinderGeometry(0.38 * scale, 0.28 * scale, 0.5 * scale, 10), potMat2)
    pot.position.set(x, potY, z)
    sceneObj.add(pot)
    // Overflowing foliage + draping tendrils
    for (let i = 0; i < 9; i++) {
      const angle  = (i / 9) * Math.PI * 2
      const radius = (0.28 + (i % 3) * 0.06) * scale
      const foliage = new THREE.Mesh(new THREE.SphereGeometry(0.22 * scale, 7, 5), i % 2 === 0 ? leafA : leafB)
      foliage.scale.set(1.0, 0.6, 1.0)
      foliage.position.set(x + Math.cos(angle) * radius, potY - 0.05, z + Math.sin(angle) * radius)
      sceneObj.add(foliage)
      // Tendril draping down from each foliage cluster
      for (let j = 0; j < 4; j++) {
        const drapeX = x + Math.cos(angle) * (radius + j * 0.07 * scale)
        const drapeY = potY - 0.15 - j * 0.25 * scale
        const drapeZ = z + Math.sin(angle) * (radius + j * 0.07 * scale)
        const bead = new THREE.Mesh(
          new THREE.SphereGeometry(Math.max(0.09 * scale - j * 0.01 * scale, 0.03), 6, 4),
          j % 2 === 0 ? vineLeaf : vineLeaf2,
        )
        bead.scale.set(1.5, 0.42, 1.0)
        bead.position.set(drapeX, drapeY, drapeZ)
        sceneObj.add(bead)
      }
    }
  }

  // Trailing vine on wall corner — starts high, falls down
  function addWallVine(x: number, startY: number, z: number, dx: number, segments: number): void {
    for (let i = 0; i < segments; i++) {
      const t = i / segments
      const yPos = startY - i * 0.3
      const xOff = dx * Math.sin(i * 1.2) * 0.12
      const zOff = Math.cos(i * 0.9) * 0.05
      const size = Math.max(0.12 - t * 0.04, 0.04)
      const bead = new THREE.Mesh(new THREE.SphereGeometry(size, 6, 4), i % 2 === 0 ? vineLeaf : vineLeaf2)
      bead.scale.set(1.6, 0.42, 1.1)
      bead.position.set(x + xOff, yPos, z + zOff)
      sceneObj.add(bead)
      if (i < segments - 1) {
        const stem = new THREE.Mesh(
          new THREE.CylinderGeometry(0.016, 0.016, 0.3, 4),
          new THREE.MeshLambertMaterial({ color: 0x3a5820 }),
        )
        stem.position.set(x + xOff, yPos - 0.15, z + zOff)
        sceneObj.add(stem)
      }
    }
  }

  // ── Floor & corner plants ───────────────────────────────────────────────────
  addPlant(-11, -1.0, 1.4, 8)                      // large left-corner monstera
  addPlant(10.5, -2.0, 1.0, 6)                      // right near back
  addPlant(-10, 3.5, 0.75, 5, potMat2)              // left mid-wall
  addPlant(12, 5.5, 0.7, 4, potMat2)                // right mid-wall
  addPlant(-5, 7.5, 0.6, 4, potMat3)               // back-left corner of room
  addPlant(4, 7.5, 0.55, 3, potMat)                 // back-right zone
  addPlant(-12.5, 1.5, 1.05, 6, potMat2)            // near window/left wall
  addSnakePlant(11.5, -3.0, 1.2)                     // tall snake plant back-right
  addSnakePlant(-13.0, 6.0, 0.85)                    // snake plant left corner

  // ── Hanging plants from ceiling ─────────────────────────────────────────────
  addHangingPlant(4.5, 11.5, 3.5, 1.0)
  addHangingPlant(-3.0, 11.5, 5.5, 0.9)

  // ── Trailing wall vines from corners ────────────────────────────────────────
  addWallVine(-13.0, 10, 5.0, 1,  14)   // left wall, falls down
  addWallVine(13.0,  9,  5.5, -1, 12)   // right wall, falls down
  addWallVine(-13.0, 8,  -2.0, 1, 10)   // left wall near window

  // ── Fiddle leaf fig (tall MCM favourite) — right back corner ────────────────
  function addFiddleLeafFig(x: number, z: number, scale: number): void {
    const flfPot = new THREE.Mesh(new THREE.CylinderGeometry(0.52 * scale, 0.40 * scale, 0.68 * scale, 14), potMat3)
    flfPot.position.set(x, 0.34 * scale, z)
    flfPot.castShadow = true
    sceneObj.add(flfPot)
    const flfSoil = new THREE.Mesh(new THREE.CylinderGeometry(0.50 * scale, 0.50 * scale, 0.05, 10), soilMat)
    flfSoil.position.set(x, 0.68 * scale, z)
    sceneObj.add(flfSoil)
    const trunkMat = new THREE.MeshLambertMaterial({ color: 0x5c3a1e })
    const trunkH = 2.6 * scale
    const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.075 * scale, 0.105 * scale, trunkH, 8), trunkMat)
    trunk.position.set(x, 0.68 * scale + trunkH / 2, z)
    sceneObj.add(trunk)
    const leafCount = 9
    for (let flfI = 0; flfI < leafCount; flfI++) {
      const flfAngle = (flfI / leafCount) * Math.PI * 2 + 0.3
      const flfR    = (0.28 + (flfI % 3) * 0.14) * scale
      const flfH    = (3.0 + (flfI % 5) * 0.38) * scale
      const flfLeaf = new THREE.Mesh(new THREE.SphereGeometry(0.42 * scale, 8, 6), flfI % 2 === 0 ? leafA : leafB)
      flfLeaf.scale.set(0.55, 0.9, 0.72)
      flfLeaf.position.set(x + Math.cos(flfAngle) * flfR, flfH, z + Math.sin(flfAngle) * flfR)
      flfLeaf.castShadow = true
      sceneObj.add(flfLeaf)
    }
  }

  addFiddleLeafFig(12.0, -1.5, 1.15)   // back right corner, tall statement plant
  addFiddleLeafFig(-4.5,  7.2, 0.82)   // back left zone, shorter one

  // ── Rubber plant — dark glossy leaves, left mid area ────────────────────────
  const rubberLeafMat = new THREE.MeshPhongMaterial({ color: 0x1a3808, shininess: 60 })
  const rubberLeafMat2 = new THREE.MeshPhongMaterial({ color: 0x2a4e12, shininess: 50 })
  const rubberPot = new THREE.Mesh(new THREE.CylinderGeometry(0.4, 0.3, 0.55, 12), potMat2)
  rubberPot.position.set(-9.0, 0.275, 2.0)
  rubberPot.castShadow = true
  sceneObj.add(rubberPot)
  const rubberSoil = new THREE.Mesh(new THREE.CylinderGeometry(0.38, 0.38, 0.05, 10), soilMat)
  rubberSoil.position.set(-9.0, 0.55, 2.0)
  sceneObj.add(rubberSoil)
  for (let rubberI = 0; rubberI < 8; rubberI++) {
    const rubberAngle = (rubberI / 8) * Math.PI * 2
    const rubberR = 0.18 + (rubberI % 3) * 0.08
    const rubberH = 0.7 + rubberI * 0.22
    const rubberLeaf = new THREE.Mesh(new THREE.SphereGeometry(0.28, 8, 6), rubberI % 2 === 0 ? rubberLeafMat : rubberLeafMat2)
    rubberLeaf.scale.set(0.5, 1.4, 0.7)
    rubberLeaf.position.set(-9.0 + Math.cos(rubberAngle) * rubberR, rubberH, 2.0 + Math.sin(rubberAngle) * rubberR)
    rubberLeaf.castShadow = true
    sceneObj.add(rubberLeaf)
  }

  // ── Small succulents on shelf planks ────────────────────────────────────────
  const succMat  = new THREE.MeshLambertMaterial({ color: 0x5a9e44 })
  const succMat2 = new THREE.MeshLambertMaterial({ color: 0x88b44a })
  const tinyPot  = new THREE.MeshLambertMaterial({ color: 0xd4845a })

  function addSucculent(x: number, y: number, z: number): void {
    const pot = new THREE.Mesh(new THREE.CylinderGeometry(0.1, 0.08, 0.14, 8), tinyPot)
    pot.position.set(x, y + 0.07, z)
    sceneObj.add(pot)
    for (let succI = 0; succI < 5; succI++) {
      const succAngle = (succI / 5) * Math.PI * 2
      const petal = new THREE.Mesh(new THREE.SphereGeometry(0.07, 6, 4), succI % 2 === 0 ? succMat : succMat2)
      petal.scale.set(0.7, 0.55, 0.7)
      petal.position.set(x + Math.cos(succAngle) * 0.07, y + 0.18, z + Math.sin(succAngle) * 0.07)
      sceneObj.add(petal)
    }
    const centerPetal = new THREE.Mesh(new THREE.SphereGeometry(0.06, 6, 4), succMat2)
    centerPetal.scale.set(0.6, 0.8, 0.6)
    centerPetal.position.set(x, y + 0.22, z)
    sceneObj.add(centerPetal)
  }

  // Place succulents on shelf planks (y values match SHELF_ROW_BOTTOMS + plank thickness)
  addSucculent( 4.5, 2.28, SHELF_Z - 0.25)
  addSucculent(-2.8, 4.38, SHELF_Z - 0.25)
  addSucculent( 1.2, 6.48, SHELF_Z - 0.22)
  addSucculent(-5.5, 6.48, SHELF_Z - 0.22)
  addSucculent( 6.2, 4.38, SHELF_Z - 0.25)

  // Small pot on desk
  const deskPot = new THREE.Mesh(new THREE.CylinderGeometry(0.17, 0.13, 0.22, 9), potMat2)
  deskPot.position.set(8.2, 2.315, 3.1)
  sceneObj.add(deskPot)
  for (let i = 0; i < 4; i++) {
    const angle = (i / 4) * Math.PI * 2
    const l = new THREE.Mesh(new THREE.SphereGeometry(0.1, 6, 4), leafB)
    l.scale.set(0.7, 1.3, 0.7)
    l.position.set(8.2 + Math.cos(angle) * 0.14, 2.52, 3.1 + Math.sin(angle) * 0.14)
    sceneObj.add(l)
  }
}

// ── Desk ──────────────────────────────────────────────────────────────────────

function buildDesk(sceneObj: THREE.Scene): void {
  const deskMat = new THREE.MeshPhongMaterial({ color: 0x3d1c02, shininess: 40 })
  const legMat  = new THREE.MeshPhongMaterial({ color: 0x2a1200, shininess: 20 })

  const top = new THREE.Mesh(new THREE.BoxGeometry(6, 0.12, 3), deskMat)
  top.position.set(10, 2.2, 2)
  top.castShadow = true
  top.receiveShadow = true
  sceneObj.add(top)

  ;([[-2.7, -1.3], [2.7, -1.3], [-2.7, 1.3], [2.7, 1.3]] as [number, number][]).forEach(([dx, dz]) => {
    const leg = new THREE.Mesh(new THREE.BoxGeometry(0.12, 2.2, 0.12), legMat)
    leg.position.set(10 + dx, 1.1, 2 + dz)
    leg.castShadow = true
    sceneObj.add(leg)
  })

  // Mug
  const mug = new THREE.Mesh(new THREE.CylinderGeometry(0.18, 0.15, 0.35, 10), new THREE.MeshPhongMaterial({ color: 0xd4956a, shininess: 60 }))
  mug.position.set(8.5, 2.44, 1.5)
  mug.castShadow = true
  sceneObj.add(mug)

  const b1 = new THREE.Mesh(new THREE.BoxGeometry(1.0, 0.18, 0.7), new THREE.MeshPhongMaterial({ color: 0x8e7cc3 }))
  b1.position.set(11.5, 2.35, 1.8)
  b1.castShadow = true
  sceneObj.add(b1)
  const b2 = new THREE.Mesh(new THREE.BoxGeometry(0.9, 0.18, 0.65), new THREE.MeshPhongMaterial({ color: 0xe8a87c }))
  b2.position.set(11.5, 2.53, 1.8)
  b2.rotation.y = 0.15
  b2.castShadow = true
  sceneObj.add(b2)

  // Desk lamp
  const metalMat = new THREE.MeshPhongMaterial({ color: 0x2c2c2c, shininess: 100 })
  const shadeMat = new THREE.MeshPhongMaterial({ color: 0xc8a828, shininess: 40, side: THREE.DoubleSide })
  const lampBase = new THREE.Mesh(new THREE.CylinderGeometry(0.22, 0.28, 0.07, 12), metalMat)
  lampBase.position.set(9.6, 2.275, 2.9)
  sceneObj.add(lampBase)
  const lampStem = new THREE.Mesh(new THREE.CylinderGeometry(0.035, 0.035, 1.15, 8), metalMat)
  lampStem.position.set(9.6, 2.875, 2.9)
  lampStem.castShadow = true
  sceneObj.add(lampStem)
  const lampShade = new THREE.Mesh(new THREE.ConeGeometry(0.4, 0.5, 12, 1, true), shadeMat)
  lampShade.rotation.x = Math.PI
  lampShade.position.set(9.6, 3.52, 2.9)
  lampShade.castShadow = true
  sceneObj.add(lampShade)
  const bulb = new THREE.Mesh(new THREE.SphereGeometry(0.07, 8, 6), new THREE.MeshBasicMaterial({ color: 0xffeeaa }))
  bulb.position.set(9.6, 3.4, 2.9)
  sceneObj.add(bulb)
  const lampLight = new THREE.PointLight(0xffd966, 3.0, 8)
  lampLight.position.set(9.6, 3.35, 2.9)
  lampLight.castShadow = true
  lampLight.shadow.mapSize.set(512, 512)
  sceneObj.add(lampLight)
}

// ── Decorations ───────────────────────────────────────────────────────────────

function buildDecorations(sceneObj: THREE.Scene): void {

  // ── Candles ─────────────────────────────────────────────────────────────────
  const candleMat = new THREE.MeshLambertMaterial({ color: 0xf0e0c8 })
  const flameMat  = new THREE.MeshBasicMaterial({ color: 0xff9922 })
  const innerMat  = new THREE.MeshBasicMaterial({ color: 0xffdd55 })

  function addCandle(x: number, y: number, z: number): void {
    const body = new THREE.Mesh(new THREE.CylinderGeometry(0.075, 0.075, 0.45, 9), candleMat)
    body.position.set(x, y + 0.225, z)
    body.castShadow = true
    sceneObj.add(body)
    const outer = new THREE.Mesh(new THREE.SphereGeometry(0.065, 6, 5), flameMat)
    outer.scale.set(0.6, 1.4, 0.6)
    outer.position.set(x, y + 0.52, z)
    sceneObj.add(outer)
    const inner = new THREE.Mesh(new THREE.SphereGeometry(0.035, 6, 5), innerMat)
    inner.scale.set(0.5, 1.2, 0.5)
    inner.position.set(x, y + 0.51, z)
    sceneObj.add(inner)
    const cl = new THREE.PointLight(0xff7722, 0.8, 5)
    cl.position.set(x, y + 0.6, z)
    sceneObj.add(cl)
  }

  addCandle(-3.8, 8.15, SHELF_Z - 0.15)
  addCandle(3.2,  8.15, SHELF_Z - 0.05)
  addCandle(5.8,  4.28, SHELF_Z - 0.1)

  // ── Figurine on shelf ───────────────────────────────────────────────────────
  const figurineMat = new THREE.MeshPhongMaterial({ color: 0x9b6e4a, shininess: 80 })
  const figurine = new THREE.Mesh(new THREE.SphereGeometry(0.2, 10, 8), figurineMat)
  figurine.scale.set(0.8, 1.4, 0.8)
  figurine.position.set(-4.5, 2.55, SHELF_Z - 0.1)
  figurine.castShadow = true
  sceneObj.add(figurine)
  const figurineBase = new THREE.Mesh(new THREE.CylinderGeometry(0.16, 0.18, 0.08, 10), figurineMat)
  figurineBase.position.set(-4.5, 2.22, SHELF_Z - 0.1)
  sceneObj.add(figurineBase)

  // ── Floor book stack ────────────────────────────────────────────────────────
  const stackColors = [0x8b4513, 0x2e5e8b, 0x6b2d5e, 0x3a6e3a]
  stackColors.forEach((color, i) => {
    const sb = new THREE.Mesh(
      new THREE.BoxGeometry(1.1 - i * 0.05, 0.12, 0.85),
      new THREE.MeshPhongMaterial({ color }),
    )
    sb.position.set(-5.5, 0.06 + i * 0.13, 2.5)
    sb.rotation.y = (i - 1.5) * 0.18
    sb.castShadow = true
    sb.receiveShadow = true
    sceneObj.add(sb)
  })

  // ── Clock on right wall ─────────────────────────────────────────────────────
  const clockRim = new THREE.Mesh(
    new THREE.TorusGeometry(0.55, 0.08, 8, 24),
    new THREE.MeshPhongMaterial({ color: 0x4a2c0a, shininess: 50 }),
  )
  clockRim.rotation.y = -Math.PI / 2
  clockRim.position.set(13.85, 8, -3)
  sceneObj.add(clockRim)
  const clockFace = new THREE.Mesh(
    new THREE.CircleGeometry(0.5, 24),
    new THREE.MeshBasicMaterial({ color: 0xf5f0e8 }),
  )
  clockFace.rotation.y = -Math.PI / 2
  clockFace.position.set(13.82, 8, -3)
  sceneObj.add(clockFace)

  // ── Lava lamps ──────────────────────────────────────────────────────────────
  const metalShiny = new THREE.MeshPhongMaterial({ color: 0xaaaaaa, shininess: 140 })

  function addSideTable(x: number, z: number): void {
    const tTop = new THREE.Mesh(new THREE.CylinderGeometry(0.55, 0.55, 0.06, 16), new THREE.MeshPhongMaterial({ color: 0x5a2d0c, shininess: 30 }))
    tTop.position.set(x, 0.82, z)
    tTop.castShadow = true
    sceneObj.add(tTop)
    const tStem = new THREE.Mesh(new THREE.CylinderGeometry(0.08, 0.08, 0.76, 8), new THREE.MeshPhongMaterial({ color: 0x3d1c02 }))
    tStem.position.set(x, 0.42, z)
    sceneObj.add(tStem)
    const tBase = new THREE.Mesh(new THREE.CylinderGeometry(0.38, 0.44, 0.06, 16), new THREE.MeshPhongMaterial({ color: 0x5a2d0c, shininess: 30 }))
    tBase.position.set(x, 0.03, z)
    sceneObj.add(tBase)
  }

  function addLavaLamp(x: number, baseY: number, z: number, blobColor: number, glowColor: number): void {
    const glassMat = new THREE.MeshPhongMaterial({ color: 0xffffff, transparent: true, opacity: 0.28, shininess: 120 })
    const blobMat  = new THREE.MeshPhongMaterial({ color: blobColor, shininess: 60 })

    // Base cap
    const base = new THREE.Mesh(new THREE.CylinderGeometry(0.22, 0.28, 0.28, 14), metalShiny)
    base.position.set(x, baseY + 0.14, z)
    sceneObj.add(base)
    // Glass body
    const glass = new THREE.Mesh(new THREE.CylinderGeometry(0.19, 0.19, 1.4, 18), glassMat)
    glass.position.set(x, baseY + 0.28 + 0.7, z)
    sceneObj.add(glass)
    // Top cap
    const top = new THREE.Mesh(new THREE.CylinderGeometry(0.22, 0.17, 0.22, 14), metalShiny)
    top.position.set(x, baseY + 0.28 + 1.4 + 0.11, z)
    sceneObj.add(top)

    // Lava blobs
    ;([
      [0.05,  0.4,  -0.03, 0.14, 0.9, 1.5, 0.9],
      [-0.04, 0.85,  0.04, 0.11, 1.0, 0.8, 1.0],
      [0.0,   1.28,  0.0,  0.09, 0.8, 1.2, 0.8],
    ] as [number, number, number, number, number, number, number][]).forEach(([bx, by, bz, r, sx, sy, sz]) => {
      const blob = new THREE.Mesh(new THREE.SphereGeometry(r, 10, 8), blobMat)
      blob.scale.set(sx, sy, sz)
      blob.position.set(x + bx, baseY + 0.28 + by, z + bz)
      sceneObj.add(blob)
    })

    // Glow
    const lavaLight = new THREE.PointLight(glowColor, 1.8, 7)
    lavaLight.position.set(x, baseY + 0.28 + 0.7, z)
    sceneObj.add(lavaLight)
  }

  // Left side table + purple lava lamp
  addSideTable(-10.5, 4.5)
  addLavaLamp(-10.5, 0.85, 4.5, 0x9b34cc, 0xcc44ff)

  // Right side table + orange/red lava lamp
  addSideTable(11.5, 5.5)
  addLavaLamp(11.5, 0.85, 5.5, 0xff5c1a, 0xff8822)

  // Extra small lava lamp on desk corner (warm amber)
  addLavaLamp(12.8, 2.2, 0.8, 0xdd3366, 0xff3366)

  // ── Floor lamp (torchiere arc) ───────────────────────────────────────────────
  const poleMat  = new THREE.MeshPhongMaterial({ color: 0x444444, shininess: 100 })
  const shadeLampMat = new THREE.MeshPhongMaterial({ color: 0xe8d4a0, shininess: 20, side: THREE.DoubleSide })

  // Arc floor lamp near left of scene
  const flBase = new THREE.Mesh(new THREE.CylinderGeometry(0.38, 0.42, 0.07, 16), poleMat)
  flBase.position.set(-4.0, 0.035, 4.5)
  flBase.castShadow = true
  sceneObj.add(flBase)

  // Vertical pole
  const flPole = new THREE.Mesh(new THREE.CylinderGeometry(0.04, 0.04, 5.2, 8), poleMat)
  flPole.position.set(-4.0, 2.6, 4.5)
  flPole.castShadow = true
  sceneObj.add(flPole)

  // Arc arm (angled cylinder leaning over room)
  const flArm = new THREE.Mesh(new THREE.CylinderGeometry(0.03, 0.03, 2.5, 8), poleMat)
  flArm.rotation.z = 0.45
  flArm.position.set(-3.0, 5.7, 4.5)
  sceneObj.add(flArm)

  // Shade — open bell pointing down
  const flShade = new THREE.Mesh(new THREE.ConeGeometry(0.52, 0.6, 16, 1, true), shadeLampMat)
  flShade.rotation.x = Math.PI
  flShade.position.set(-1.8, 6.3, 4.5)
  flShade.castShadow = true
  sceneObj.add(flShade)

  // Bulb glow
  const flBulb = new THREE.Mesh(new THREE.SphereGeometry(0.08, 8, 6), new THREE.MeshBasicMaterial({ color: 0xfffce0 }))
  flBulb.position.set(-1.8, 6.1, 4.5)
  sceneObj.add(flBulb)

  // Floor lamp light (soft downward cone)
  const flLight = new THREE.SpotLight(0xffe0aa, 3.2, 14, Math.PI / 4.5, 0.6, 1.5)
  flLight.position.set(-1.8, 6.1, 4.5)
  flLight.target.position.set(-1.8, 0, 4.5)
  flLight.castShadow = true
  flLight.shadow.mapSize.set(512, 512)
  sceneObj.add(flLight)
  sceneObj.add(flLight.target)

  // ── Tripod floor lamp (MCM) — right side ────────────────────────────────────
  const tripodMat      = new THREE.MeshPhongMaterial({ color: 0x1a1208, shininess: 120 })
  const tripodShadeMat = new THREE.MeshPhongMaterial({ color: 0xd4a030, shininess: 30, side: THREE.DoubleSide })
  const tripodX = 8.5
  const tripodZ = 7.5
  const tripodTop = 5.6

  const tripodShade = new THREE.Mesh(new THREE.ConeGeometry(0.62, 0.7, 16, 1, true), tripodShadeMat)
  tripodShade.rotation.x = Math.PI
  tripodShade.position.set(tripodX, tripodTop + 0.35, tripodZ)
  sceneObj.add(tripodShade)

  for (let legIdx = 0; legIdx < 3; legIdx++) {
    const legAngle = (legIdx / 3) * Math.PI * 2
    const legLen   = 6.1
    const leg = new THREE.Mesh(new THREE.CylinderGeometry(0.025, 0.025, legLen, 6), tripodMat)
    leg.position.set(
      tripodX + Math.cos(legAngle) * 0.42,
      tripodTop - legLen / 2 + 0.2,
      tripodZ + Math.sin(legAngle) * 0.42,
    )
    leg.rotation.z =  Math.cos(legAngle) * 0.36
    leg.rotation.x =  Math.sin(legAngle) * 0.36
    sceneObj.add(leg)
  }

  const tripodBulb = new THREE.Mesh(new THREE.SphereGeometry(0.07, 8, 6), new THREE.MeshBasicMaterial({ color: 0xfffce0 }))
  tripodBulb.position.set(tripodX, tripodTop + 0.05, tripodZ)
  sceneObj.add(tripodBulb)

  const tripodLight = new THREE.SpotLight(0xffd580, 2.8, 13, Math.PI / 4, 0.5, 1.4)
  tripodLight.position.set(tripodX, tripodTop + 0.2, tripodZ)
  tripodLight.target.position.set(tripodX, 0, tripodZ)
  tripodLight.castShadow = true
  tripodLight.shadow.mapSize.set(512, 512)
  sceneObj.add(tripodLight)
  sceneObj.add(tripodLight.target)

  // ── Second arc floor lamp — left front, warm teal shade ─────────────────────
  const arc2PoleMat  = new THREE.MeshPhongMaterial({ color: 0x2a1800, shininess: 80 })
  const arc2ShadeMat = new THREE.MeshPhongMaterial({ color: 0x3d7a6a, shininess: 20, side: THREE.DoubleSide })

  const arc2Base = new THREE.Mesh(new THREE.CylinderGeometry(0.35, 0.40, 0.07, 14), arc2PoleMat)
  arc2Base.position.set(-8.5, 0.035, 6.8)
  sceneObj.add(arc2Base)

  const arc2Pole = new THREE.Mesh(new THREE.CylinderGeometry(0.038, 0.038, 4.8, 8), arc2PoleMat)
  arc2Pole.position.set(-8.5, 2.44, 6.8)
  sceneObj.add(arc2Pole)

  const arc2Arm = new THREE.Mesh(new THREE.CylinderGeometry(0.028, 0.028, 2.2, 8), arc2PoleMat)
  arc2Arm.rotation.z = -0.42
  arc2Arm.position.set(-7.6, 5.3, 6.8)
  sceneObj.add(arc2Arm)

  const arc2Shade = new THREE.Mesh(new THREE.ConeGeometry(0.48, 0.55, 14, 1, true), arc2ShadeMat)
  arc2Shade.rotation.x = Math.PI
  arc2Shade.position.set(-6.5, 5.85, 6.8)
  sceneObj.add(arc2Shade)

  const arc2Bulb = new THREE.Mesh(new THREE.SphereGeometry(0.07, 8, 6), new THREE.MeshBasicMaterial({ color: 0xffeecc }))
  arc2Bulb.position.set(-6.5, 5.65, 6.8)
  sceneObj.add(arc2Bulb)

  const arc2Light = new THREE.SpotLight(0xffc87a, 2.6, 13, Math.PI / 4.2, 0.55, 1.4)
  arc2Light.position.set(-6.5, 5.65, 6.8)
  arc2Light.target.position.set(-6.5, 0, 6.8)
  arc2Light.castShadow = true
  arc2Light.shadow.mapSize.set(512, 512)
  sceneObj.add(arc2Light)
  sceneObj.add(arc2Light.target)

  // ── Sputnik pendant (MCM) — above center of room ────────────────────────────
  const sputnikMat = new THREE.MeshPhongMaterial({ color: 0xc8b08a, shininess: 130 })

  const sputnikWire = new THREE.Mesh(new THREE.CylinderGeometry(0.012, 0.012, 1.6, 5), new THREE.MeshBasicMaterial({ color: 0x222222 }))
  sputnikWire.position.set(1.5, 11.2, 1.5)
  sceneObj.add(sputnikWire)

  const sputnikBody = new THREE.Mesh(new THREE.SphereGeometry(0.2, 12, 8), sputnikMat)
  sputnikBody.position.set(1.5, 10.4, 1.5)
  sceneObj.add(sputnikBody)

  const spokeDirections: [number, number, number][] = [
    [1,0,0],[-1,0,0],[0,1,0],[0,-1,0],[0,0,1],[0,0,-1],
    [0.7,0.7,0],[-0.7,0.7,0],[0.7,-0.7,0],[-0.7,-0.7,0],
    [0.7,0,0.7],[0.7,0,-0.7],[-0.7,0,0.7],[-0.7,0,-0.7],
  ]

  spokeDirections.forEach(([sx, sy, sz]) => {
    const spokeLen = 0.95
    const spoke = new THREE.Mesh(new THREE.CylinderGeometry(0.012, 0.012, spokeLen, 5), sputnikMat)
    spoke.position.set(1.5 + sx * spokeLen / 2, 10.4 + sy * spokeLen / 2, 1.5 + sz * spokeLen / 2)
    const dir = new THREE.Vector3(sx, sy, sz).normalize()
    spoke.quaternion.setFromUnitVectors(new THREE.Vector3(0, 1, 0), dir)
    sceneObj.add(spoke)
    const tipBulb = new THREE.Mesh(new THREE.SphereGeometry(0.045, 6, 4), new THREE.MeshBasicMaterial({ color: 0xfff2aa }))
    tipBulb.position.set(1.5 + sx * spokeLen, 10.4 + sy * spokeLen, 1.5 + sz * spokeLen)
    sceneObj.add(tipBulb)
  })

  const sputnikLight = new THREE.PointLight(0xffeebb, 2.2, 18)
  sputnikLight.position.set(1.5, 10.4, 1.5)
  sceneObj.add(sputnikLight)

  // ── String lights ────────────────────────────────────────────────────────────
  const stringBulbMat = new THREE.MeshBasicMaterial({ color: 0xffe8aa })
  const wireMat = new THREE.MeshBasicMaterial({ color: 0x221100 })

  function addStringLights(x1: number, x2: number, y: number, z: number, count: number): void {
    for (let i = 0; i < count; i++) {
      const t = i / (count - 1)
      const droop = Math.sin(t * Math.PI) * 0.5
      const bulbMesh = new THREE.Mesh(new THREE.SphereGeometry(0.055, 6, 4), stringBulbMat)
      bulbMesh.position.set(x1 + t * (x2 - x1), y - droop, z)
      sceneObj.add(bulbMesh)
      // Short wire between bulbs
      if (i < count - 1) {
        const wx = x1 + (t + 0.5 / (count - 1)) * (x2 - x1)
        const wd = Math.sin((t + 0.5 / (count - 1)) * Math.PI) * 0.5
        const wire = new THREE.Mesh(new THREE.CylinderGeometry(0.008, 0.008, (x2 - x1) / (count - 1) * 1.1, 4), wireMat)
        wire.rotation.z = Math.PI / 2
        wire.position.set(wx, y - wd, z)
        sceneObj.add(wire)
      }
    }
    // One ambient glow per string
    const sl = new THREE.PointLight(0xffe0aa, 0.6, 14)
    sl.position.set((x1 + x2) / 2, y, z)
    sceneObj.add(sl)
  }

  // String lights draped along back wall near ceiling
  addStringLights(-8, 8, 11.4, -7.0, 18)
  // String lights across room above viewing area
  addStringLights(-7, 7, 11.0, 7.0, 16)

  // ── Paintings (tableaux) ────────────────────────────────────────────────────

  function addPainting(
    cx: number, cy: number, cz: number,
    rotY: number,
    pw: number, ph: number,
    bgColor: number,
    shapes: { type: 'circle' | 'rect'; color: number; x: number; y: number; w: number; h: number }[],
    frameColor = 0x3a1c06,
  ): void {
    const group = new THREE.Group()
    group.position.set(cx, cy, cz)
    group.rotation.y = rotY

    const frameMat = new THREE.MeshPhongMaterial({ color: frameColor, shininess: 50 })
    const frame = new THREE.Mesh(new THREE.BoxGeometry(pw + 0.26, ph + 0.26, 0.12), frameMat)
    frame.castShadow = true
    group.add(frame)

    const canvas = new THREE.Mesh(new THREE.PlaneGeometry(pw, ph), new THREE.MeshBasicMaterial({ color: bgColor }))
    canvas.position.z = 0.063
    group.add(canvas)

    shapes.forEach((sh) => {
      let geo: THREE.BufferGeometry
      if (sh.type === 'circle') {
        geo = new THREE.CircleGeometry(sh.w / 2, 14)
      } else {
        geo = new THREE.PlaneGeometry(sh.w, sh.h)
      }
      const mesh = new THREE.Mesh(geo, new THREE.MeshBasicMaterial({ color: sh.color }))
      mesh.position.set(sh.x, sh.y, 0.065)
      group.add(mesh)
    })

    sceneObj.add(group)
  }

  // Back wall paintings (rotY = 0, facing camera)
  addPainting(-9,  8.5, -7.9, 0, 2.4, 1.6, 0x1a3a1a, [
    { type: 'rect',   color: 0x0d220d, x: 0,    y: -0.25, w: 2.4,  h: 0.55 },
    { type: 'circle', color: 0x2a6e1a, x: -0.5, y: 0.1,   w: 0.9,  h: 0.9 },
    { type: 'circle', color: 0x3d8c28, x: 0.4,  y: 0.2,   w: 0.65, h: 0.65 },
    { type: 'circle', color: 0x1e4c10, x: 0.0,  y: -0.05, w: 0.5,  h: 0.5 },
  ], 0x6b3a10)  // forest scene

  addPainting(5.5, 8.0, -7.9, 0, 2.8, 1.9, 0x7fa8b0, [
    { type: 'circle', color: 0xd97c28, x: -0.3, y: 0.3,  w: 0.9, h: 0.9 },
    { type: 'rect',   color: 0x5c8aa0, x: 0,    y: -0.2, w: 2.8, h: 0.7 },
    { type: 'circle', color: 0xf5d080, x: -0.3, y: 0.3,  w: 0.55, h: 0.55 },
  ], 0x2a1200)  // sunset over water

  addPainting(-2, 6.2, -7.9, 0, 1.4, 1.4, 0x1a0d2e, [
    { type: 'circle', color: 0xffffff, x: -0.3, y: 0.2,  w: 0.15, h: 0.15 },
    { type: 'circle', color: 0xffffff, x: 0.2,  y: 0.4,  w: 0.08, h: 0.08 },
    { type: 'circle', color: 0xffffff, x: 0.4,  y: -0.1, w: 0.12, h: 0.12 },
    { type: 'circle', color: 0xaaccff, x: -0.1, y: -0.3, w: 0.4,  h: 0.4 },
    { type: 'circle', color: 0x88aaee, x: 0.1,  y: -0.3, w: 0.28, h: 0.28 },
  ], 0x4a3010)  // night sky / moon

  // Right wall painting (rotY = -π/2)
  addPainting(13.88, 6.5, 1.5, -Math.PI / 2, 1.6, 2.8, 0x2a1a0a, [
    { type: 'rect',   color: 0x7a4520, x: 0,    y: 0.6,  w: 1.6,  h: 0.4 },
    { type: 'circle', color: 0xd4956a, x: 0,    y: 0.1,  w: 0.6,  h: 0.6 },
    { type: 'rect',   color: 0x4a2a08, x: 0,    y: -0.8, w: 1.6,  h: 1.2 },
  ], 0x5a3010)  // portrait style

  addPainting(13.88, 9.5, -4.5, -Math.PI / 2, 2.2, 1.5, 0x2255aa, [
    { type: 'rect',   color: 0x1a4488, x: 0,    y: -0.1, w: 2.2,  h: 0.8 },
    { type: 'circle', color: 0xffffff, x: 0.3,  y: 0.3,  w: 0.35, h: 0.35 },
    { type: 'rect',   color: 0xffa040, x: -0.4, y: -0.2, w: 0.8,  h: 0.12 },
  ], 0x2a1200)  // abstract seascape

  // Left wall painting above window
  addPainting(-13.88, 10.2, 3.5, Math.PI / 2, 2.0, 1.4, 0x3a1a2a, [
    { type: 'circle', color: 0xcc44aa, x: -0.3, y: 0.1,  w: 0.7, h: 0.7 },
    { type: 'circle', color: 0x8822cc, x: 0.3,  y: -0.1, w: 0.5, h: 0.5 },
    { type: 'rect',   color: 0x220a30, x: 0,    y: 0,    w: 2.0, h: 0.2 },
  ], 0x8b5c2a)  // abstract purple

  // ── MCM additions ──────────────────────────────────────────────────────────

  // Rothko-style color-field — back wall center, warm ochre/burnt sienna stacked bands
  addPainting(0, 7.5, -7.9, 0, 3.0, 2.0, 0xb5411a, [
    { type: 'rect', color: 0xd4601e, x: 0,  y: 0.55,  w: 2.7, h: 0.75 },
    { type: 'rect', color: 0x8a1c08, x: 0,  y: -0.45, w: 2.7, h: 0.65 },
    { type: 'rect', color: 0xc94c14, x: 0,  y:  0.05, w: 2.7, h: 0.35 },
  ], 0x2a1200)  // Rothko warm

  // Mondrian-inspired grid — right wall, taller narrow panel
  addPainting(13.88, 7.5, 3.0, -Math.PI / 2, 1.8, 2.6, 0xf5f0e0, [
    { type: 'rect', color: 0x1a3a80, x: -0.45, y:  0.6, w: 0.82, h: 0.85 },
    { type: 'rect', color: 0xc82020, x:  0.45, y: -0.5, w: 0.82, h: 0.85 },
    { type: 'rect', color: 0xe8b820, x: -0.45, y: -0.5, w: 0.82, h: 0.6  },
    { type: 'rect', color: 0x111111, x:  0,    y:  0,   w: 1.8,  h: 0.06 },
    { type: 'rect', color: 0x111111, x: -0.01, y:  0,   w: 0.06, h: 2.6  },
  ], 0x1a1208)  // Mondrian

  // Teal abstract geometry — back wall far right
  addPainting(8.5, 5.5, -7.9, 0, 1.5, 2.0, 0x0d3d3a, [
    { type: 'circle', color: 0x1a7a6e, x:  0.1,  y:  0.4, w: 1.1, h: 1.1 },
    { type: 'rect',   color: 0x0a2e2c, x:  0.0,  y: -0.5, w: 1.5, h: 0.7 },
    { type: 'circle', color: 0xd4a030, x:  0.35, y:  0.5, w: 0.3, h: 0.3 },
  ], 0x3d2008)  // teal MCM

  // Mustard-and-charcoal abstract — back wall far left lower
  addPainting(-7.0, 5.0, -7.9, 0, 1.6, 1.2, 0x2a2012, [
    { type: 'rect',   color: 0xc89820, x: -0.2, y:  0.15, w: 0.9, h: 0.75 },
    { type: 'rect',   color: 0x3a2c10, x:  0.4, y: -0.15, w: 0.55, h: 0.6 },
    { type: 'circle', color: 0xe0b030, x: -0.4, y: -0.25, w: 0.35, h: 0.35 },
  ], 0x5c3a10)  // mustard

  // Thin tall MCM print — left wall lower
  addPainting(-13.88, 5.5, 5.5, Math.PI / 2, 1.2, 2.2, 0xf0e8d8, [
    { type: 'rect',   color: 0x2a3a5c, x:  0,    y:  0.5, w: 1.0, h: 0.85 },
    { type: 'circle', color: 0xc84020, x: -0.1,  y: -0.3, w: 0.65, h: 0.65 },
    { type: 'rect',   color: 0xe8c030, x:  0.3,  y: -0.6, w: 0.5, h: 0.38 },
  ], 0x4a2c08)  // primary MCM
}

// ── Books ─────────────────────────────────────────────────────────────────────

function buildBooks(sceneObj: THREE.Scene, collections: ShelfCollection[]): void {
  const pagesMat = new THREE.MeshLambertMaterial({ color: 0xf0ead8 })
  const topMat   = new THREE.MeshLambertMaterial({ color: 0xe8e0cc })
  const geom     = new THREE.BoxGeometry(BOOK_THICK, BOOK_H, BOOK_DEPTH)
  const loader   = new THREE.TextureLoader()

  let row = 0
  let curX = SHELF_LEFT_X
  let firstGroup = true

  collections.forEach((collection) => {
    if (!firstGroup) {
      if (curX + GROUP_GAP + BOOK_THICK > SHELF_LEFT_X + SHELF_USABLE_W) {
        row++
        curX = SHELF_LEFT_X
      } else {
        curX += GROUP_GAP
      }
    }
    firstGroup = false

    const colorInt = seedColorInt(collection.id)
    const resolvedCover = coverUrl(collection.manga.coverUrl)
    const coverFaceMat = resolvedCover
      ? new THREE.MeshLambertMaterial({ map: loader.load(resolvedCover) })
      : new THREE.MeshLambertMaterial({ color: colorInt })

    collection.volumes.forEach((volume) => {
      if (row >= SHELF_ROW_BOTTOMS.length) return
      if (curX + BOOK_THICK > SHELF_LEFT_X + SHELF_USABLE_W) {
        row++
        curX = SHELF_LEFT_X
        if (row >= SHELF_ROW_BOTTOMS.length) return
      }

      const volCoverUrl = coverUrl(volume.coverUrl) ?? resolvedCover
      const volCoverFaceMat = volCoverUrl && volCoverUrl !== resolvedCover
        ? new THREE.MeshLambertMaterial({ map: loader.load(volCoverUrl) })
        : coverFaceMat

      const spineMat = new THREE.MeshLambertMaterial({
        map: createSpineTexture(collection.manga.edition, collection.manga.title, volume.number, colorInt),
      })
      const backMat = new THREE.MeshLambertMaterial({ color: colorInt })

      // BoxGeometry face order: +x (cover), -x (pages), +y (top), -y (bottom), +z (spine→camera), -z (back)
      const mesh = new THREE.Mesh(geom, [volCoverFaceMat, pagesMat, topMat, topMat, spineMat, backMat])

      const spineZ = SPINE_FACE_Z - BOOK_DEPTH / 2
      mesh.position.set(curX + BOOK_THICK / 2, SHELF_ROW_BOTTOMS[row] + BOOK_H / 2, spineZ)
      mesh.castShadow = true

      originalZ.set(mesh, spineZ)
      bookMap.set(mesh, {
        mangaTitle: collection.manga.title,
        number:     volume.number,
        coverUrl:   volume.coverUrl ?? collection.manga.coverUrl ?? null,
      })

      sceneObj.add(mesh)
      curX += BOOK_THICK + BOOK_GAP
    })
  })
}

// ── Scene init ────────────────────────────────────────────────────────────────

function initScene(collections: ShelfCollection[]): void {
  if (!canvasRef.value || isInitialized.value) return
  disposeScene()

  const { width, height } = containerSize()

  renderer = new THREE.WebGLRenderer({ canvas: canvasRef.value, antialias: true })
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2))
  renderer.setSize(width, height)
  renderer.shadowMap.enabled = true
  renderer.shadowMap.type = THREE.PCFSoftShadowMap

  scene = new THREE.Scene()
  scene.background = new THREE.Color(0x180e06)
  scene.fog = new THREE.FogExp2(0x180e06, 0.02)

  camera = new THREE.PerspectiveCamera(52, width / height, 0.1, 100)
  camera.position.set(0, 5.5, 16)
  camera.lookAt(0, 3.5, -4)

  // Very dim ambient — lights do the work
  scene.add(new THREE.AmbientLight(0xffddbb, 0.28))

  // Window sunlight — afternoon angle from left
  const sun = new THREE.DirectionalLight(0xfff0c8, 0.85)
  sun.position.set(-10, 14, 4)
  sun.castShadow = true
  sun.shadow.mapSize.set(2048, 2048)
  sun.shadow.camera.near = 1
  sun.shadow.camera.far = 50
  sun.shadow.camera.left = -18
  sun.shadow.camera.right = 18
  sun.shadow.camera.top = 20
  sun.shadow.camera.bottom = -5
  sun.shadow.bias = -0.001
  scene.add(sun)

  // Main spotlight over bookshelf
  const shelfSpot = new THREE.SpotLight(0xffcc88, 3.5, 22, Math.PI / 5.5, 0.5, 1.2)
  shelfSpot.position.set(0, 11, -1)
  shelfSpot.target.position.set(0, 4, SHELF_Z)
  shelfSpot.castShadow = true
  shelfSpot.shadow.mapSize.set(1024, 1024)
  shelfSpot.shadow.bias = -0.001
  scene.add(shelfSpot)
  scene.add(shelfSpot.target)

  // Warm fill from right
  const fill = new THREE.PointLight(0xff9966, 1.0, 24)
  fill.position.set(9, 5, 6)
  scene.add(fill)

  // Cool sky fill from window side
  const skyFill = new THREE.PointLight(0xaaccff, 0.35, 22)
  skyFill.position.set(-9, 9, 3)
  scene.add(skyFill)

  // Rim light behind shelf for depth
  const rimLight = new THREE.PointLight(0xff7744, 0.55, 12)
  rimLight.position.set(0, 6, -7)
  scene.add(rimLight)

  buildRoom(scene)
  buildShelf(scene)
  buildPlants(scene)
  buildDesk(scene)
  buildDecorations(scene)
  buildBooks(scene, collections)

  controls = new OrbitControls(camera, renderer.domElement)
  controls.target.set(0, 3.5, -4)
  controls.minDistance = 4
  controls.maxDistance = 24
  controls.minPolarAngle = 0.2
  controls.maxPolarAngle = Math.PI / 2
  controls.minAzimuthAngle = -1.1
  controls.maxAzimuthAngle = 1.1
  controls.enableDamping = true
  controls.dampingFactor = 0.08
  controls.update()

  isInitialized.value = true

  function animate(): void {
    animFrameId = requestAnimationFrame(animate)

    for (const [mesh, state] of animating) {
      const isHovered = mesh === hoveredMesh
      const targetZ    = isHovered ? state.originZ + PULL_OUT : state.originZ
      const targetRotY = isHovered ? -0.48 : state.originRotY

      mesh.position.z += (targetZ    - mesh.position.z)    * LERP_SPEED
      mesh.rotation.y += (targetRotY - mesh.rotation.y)    * LERP_SPEED

      if (!isHovered
        && Math.abs(mesh.position.z - state.originZ)    < 0.003
        && Math.abs(mesh.rotation.y - state.originRotY) < 0.002) {
        mesh.position.z = state.originZ
        mesh.rotation.y = state.originRotY
        animating.delete(mesh)
      }
    }

    controls!.update()
    renderer!.render(scene!, camera!)
  }
  animate()
}

// ── Interaction ───────────────────────────────────────────────────────────────

function updatePointer(event: MouseEvent): void {
  if (!renderer) return
  const rect = renderer.domElement.getBoundingClientRect()
  pointer.x =  ((event.clientX - rect.left) / rect.width)  * 2 - 1
  pointer.y = -((event.clientY - rect.top)  / rect.height) * 2 + 1
}

function onMouseMove(event: MouseEvent): void {
  if (!renderer || !camera) return
  updatePointer(event)
  raycaster.setFromCamera(pointer, camera)
  const hits = raycaster.intersectObjects(Array.from(bookMap.keys()), false)
  const newHovered = hits.length > 0 ? hits[0].object : null

  if (newHovered !== hoveredMesh) {
    hoveredMesh = newHovered
    if (canvasRef.value) canvasRef.value.style.cursor = newHovered ? 'pointer' : ''
    if (newHovered && !animating.has(newHovered)) {
      animating.set(newHovered, {
        originZ:    originalZ.get(newHovered) ?? newHovered.position.z,
        originRotY: newHovered.rotation.y,
      })
    }
  }
}

function onCanvasClick(event: MouseEvent): void {
  if (!renderer || !camera) return
  updatePointer(event)
  raycaster.setFromCamera(pointer, camera)
  const hits = raycaster.intersectObjects(Array.from(bookMap.keys()), false)
  if (hits.length > 0) {
    const hit = bookMap.get(hits[0].object)
    if (hit) selectedBook.value = hit
  }
}

// ── Resize ────────────────────────────────────────────────────────────────────

function onResize(): void {
  if (!renderer || !camera) return
  const { width, height } = containerSize()
  camera.aspect = width / height
  camera.updateProjectionMatrix()
  renderer.setSize(width, height)
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────

watch(shelfData, (data) => {
  if (data && canvasRef.value) initScene(data)
})

onMounted(() => {
  window.addEventListener('resize', onResize)
  if (shelfData.value && canvasRef.value) initScene(shelfData.value)
})

onUnmounted(() => {
  window.removeEventListener('resize', onResize)
  disposeScene()
})
</script>

<template>
  <div
    ref="containerRef"
    class="relative w-full h-[calc(100vh-3.5rem)] lg:h-screen overflow-hidden bg-[#180e06]"
  >
    <div v-if="isLoading" class="absolute inset-0 flex items-center justify-center z-10">
      <div class="text-center text-white/60">
        <div class="loading loading-dots loading-lg mb-3" />
        <p class="text-sm">Chargement de la bibliothèque…</p>
      </div>
    </div>

    <div v-else-if="error" class="absolute inset-0 flex items-center justify-center z-10">
      <p class="text-sm text-white/60">Impossible de charger la collection.</p>
    </div>

    <div
      v-else-if="!isLoading && !hasBooks && shelfData"
      class="absolute inset-0 flex items-center justify-center z-10"
    >
      <div class="text-center text-white/60 px-6 max-w-sm">
        <p class="text-lg font-medium mb-2">Étagère vide</p>
        <p class="text-sm">Marque des tomes comme possédés dans ta collection pour les voir ici.</p>
      </div>
    </div>

    <canvas
      ref="canvasRef"
      class="absolute inset-0 w-full h-full"
      @mousemove="onMouseMove"
      @click="onCanvasClick"
    />

    <div
      v-if="isInitialized && hasBooks"
      class="absolute bottom-4 left-1/2 -translate-x-1/2 z-10 text-white/25 text-xs pointer-events-none select-none"
    >
      Survole pour sortir un tome · Clique pour voir la couverture
    </div>

    <Transition
      enter-active-class="transition-all duration-200 ease-out"
      leave-active-class="transition-all duration-150 ease-in"
      enter-from-class="opacity-0 scale-95"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="selectedBook"
        class="absolute inset-0 z-30 flex items-center justify-center p-4"
        @click.self="selectedBook = null"
      >
        <div class="relative bg-base-100 rounded-2xl shadow-2xl max-w-xs w-full overflow-hidden">
          <button
            class="absolute top-3 right-3 z-10 flex items-center justify-center w-8 h-8 rounded-full bg-base-200 hover:bg-base-300 transition-colors"
            @click="selectedBook = null"
          >
            <X class="w-4 h-4" />
          </button>

          <div class="aspect-[2/3] bg-base-200 overflow-hidden">
            <img
              v-if="coverUrl(selectedBook.coverUrl)"
              :src="coverUrl(selectedBook.coverUrl)!"
              :alt="`${selectedBook.mangaTitle} T${selectedBook.number}`"
              class="w-full h-full object-cover"
            />
            <div v-else class="w-full h-full flex items-center justify-center">
              <span class="text-base-content/30 text-sm">Pas de couverture</span>
            </div>
          </div>

          <div class="p-4">
            <p class="text-xs text-base-content/50 uppercase tracking-wider mb-1">{{ selectedBook.mangaTitle }}</p>
            <p class="text-xl font-bold">Tome {{ selectedBook.number }}</p>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>
