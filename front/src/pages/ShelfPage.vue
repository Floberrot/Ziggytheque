<script setup lang="ts">
import { ref, shallowRef, watch, onMounted, onUnmounted, computed } from 'vue'
import * as THREE from 'three'
import { OrbitControls } from 'three/addons/controls/OrbitControls.js'
import { useQuery } from '@tanstack/vue-query'
import { getShelf, type ShelfCollection } from '@/api/shelf'
import { coverUrl } from '@/utils/coverUrl'
import { seedColorInt, createSpineTexture } from '@/utils/bookTextures'
import Volume3DViewer from '@/components/organisms/Volume3DViewer.vue'
import BaseLoader from '@/components/atoms/BaseLoader.vue'
import { Box, X } from 'lucide-vue-next'

// ── Reactive state ────────────────────────────────────────────────────────────

interface SelectedBook {
  mangaTitle: string
  number: number
  coverUrl: string | null
  spineUrl: string | null
  backCoverUrl: string | null
  edition: string | null
}

const containerRef = ref<HTMLDivElement>()
const canvasRef = ref<HTMLCanvasElement>()
const selectedBook = ref<SelectedBook | null>(null)
const show3d = ref(false)
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

// ── Synthetic spine + colour helpers live in utils/bookTextures, shared with the
//    single-volume Volume3DViewer so both render an identical fallback look. ─────

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

// ── Plants (curated, logically placed) ───────────────────────────────────────

function buildPlants(sceneObj: THREE.Scene): void {
  const potMat2 = new THREE.MeshLambertMaterial({ color: 0xb5895a })
  const potMat3 = new THREE.MeshLambertMaterial({ color: 0x7a5c3a })
  const soilMat = new THREE.MeshLambertMaterial({ color: 0x3a200a })
  const leafA   = new THREE.MeshLambertMaterial({ color: 0x2d5016 })
  const leafB   = new THREE.MeshLambertMaterial({ color: 0x3d6e22 })

  // Fiddle leaf fig — back-left corner, tall statement plant
  const flfX = -11.5
  const flfZ = -5.5
  const flfPot = new THREE.Mesh(new THREE.CylinderGeometry(0.52, 0.40, 0.68, 14), potMat3)
  flfPot.position.set(flfX, 0.34, flfZ)
  flfPot.castShadow = true
  sceneObj.add(flfPot)
  const flfSoil = new THREE.Mesh(new THREE.CylinderGeometry(0.50, 0.50, 0.05, 10), soilMat)
  flfSoil.position.set(flfX, 0.68, flfZ)
  sceneObj.add(flfSoil)
  const flfTrunk = new THREE.Mesh(
    new THREE.CylinderGeometry(0.075, 0.105, 3.1, 8),
    new THREE.MeshLambertMaterial({ color: 0x5c3a1e }),
  )
  flfTrunk.position.set(flfX, 0.68 + 1.55, flfZ)
  sceneObj.add(flfTrunk)
  for (let leafIdx = 0; leafIdx < 9; leafIdx++) {
    const angle = (leafIdx / 9) * Math.PI * 2 + 0.3
    const leafRadius = 0.28 + (leafIdx % 3) * 0.14
    const leafH = 3.0 + (leafIdx % 5) * 0.38
    const leaf = new THREE.Mesh(new THREE.SphereGeometry(0.42, 8, 6), leafIdx % 2 === 0 ? leafA : leafB)
    leaf.scale.set(0.55, 0.9, 0.72)
    leaf.position.set(flfX + Math.cos(angle) * leafRadius, leafH, flfZ + Math.sin(angle) * leafRadius)
    leaf.castShadow = true
    sceneObj.add(leaf)
  }

  // Snake plant — right side of bookshelf, grounding the composition
  const snakeX = 8.4
  const snakeZ = -3.2
  const snakePot = new THREE.Mesh(new THREE.CylinderGeometry(0.42, 0.31, 0.60, 10), potMat3)
  snakePot.position.set(snakeX, 0.30, snakeZ)
  snakePot.castShadow = true
  sceneObj.add(snakePot)
  const snakeSoil = new THREE.Mesh(new THREE.CylinderGeometry(0.40, 0.40, 0.05, 10), soilMat)
  snakeSoil.position.set(snakeX, 0.60, snakeZ)
  sceneObj.add(snakeSoil)
  for (let snakeI = 0; snakeI < 6; snakeI++) {
    const angle = (snakeI / 6) * Math.PI * 2 + 0.2
    const lean = 0.13 + (snakeI % 3) * 0.06
    const height = 1.5 + (snakeI % 2) * 0.55
    const leaf = new THREE.Mesh(new THREE.BoxGeometry(0.09, height, 0.24), snakeI % 2 === 0 ? leafA : leafB)
    leaf.position.set(snakeX + Math.cos(angle) * lean, 0.60 + height / 2, snakeZ + Math.sin(angle) * lean)
    leaf.rotation.z = Math.cos(angle) * 0.15
    leaf.rotation.x = Math.sin(angle) * 0.15
    sceneObj.add(leaf)
  }

  // Small succulents on shelf planks — only on shelves that have empty space
  const succMat  = new THREE.MeshLambertMaterial({ color: 0x5a9e44 })
  const succMat2 = new THREE.MeshLambertMaterial({ color: 0x88b44a })
  const tinyPot  = new THREE.MeshLambertMaterial({ color: 0xd4845a })

  function addSucculent(x: number, y: number, z: number): void {
    const pot = new THREE.Mesh(new THREE.CylinderGeometry(0.1, 0.08, 0.14, 8), tinyPot)
    pot.position.set(x, y + 0.07, z)
    sceneObj.add(pot)
    for (let succI = 0; succI < 5; succI++) {
      const angle = (succI / 5) * Math.PI * 2
      const petal = new THREE.Mesh(new THREE.SphereGeometry(0.07, 6, 4), succI % 2 === 0 ? succMat : succMat2)
      petal.scale.set(0.7, 0.55, 0.7)
      petal.position.set(x + Math.cos(angle) * 0.07, y + 0.18, z + Math.sin(angle) * 0.07)
      sceneObj.add(petal)
    }
    const center = new THREE.Mesh(new THREE.SphereGeometry(0.06, 6, 4), succMat2)
    center.scale.set(0.6, 0.8, 0.6)
    center.position.set(x, y + 0.22, z)
    sceneObj.add(center)
  }

  addSucculent( 4.5, 2.28, SHELF_Z - 0.25)
  addSucculent(-2.8, 4.38, SHELF_Z - 0.25)
  addSucculent( 1.2, 6.48, SHELF_Z - 0.22)

  // ── Plants flanking the bookshelf ───────────────────────────────────────────
  // Large monstera — left side of shelf
  const monsteraX = -9.2
  const monsteraZ = SHELF_Z + 0.3
  const monsteraPot = new THREE.Mesh(new THREE.CylinderGeometry(0.58, 0.42, 0.78, 12), potMat3)
  monsteraPot.position.set(monsteraX, 0.39, monsteraZ)
  monsteraPot.castShadow = true
  sceneObj.add(monsteraPot)
  const monsteraSoil = new THREE.Mesh(new THREE.CylinderGeometry(0.56, 0.56, 0.05, 12), soilMat)
  monsteraSoil.position.set(monsteraX, 0.78, monsteraZ)
  sceneObj.add(monsteraSoil)
  for (let mIdx = 0; mIdx < 10; mIdx++) {
    const angle = (mIdx / 10) * Math.PI * 2 + 0.4
    const radius = (0.30 + (mIdx % 3) * 0.12)
    const height = 1.1 + (mIdx % 5) * 0.28
    const mLeaf = new THREE.Mesh(new THREE.SphereGeometry(0.38 + (mIdx % 3) * 0.08, 8, 6), mIdx % 2 === 0 ? leafA : leafB)
    mLeaf.scale.set(0.75, 0.58, 0.75)
    mLeaf.position.set(monsteraX + Math.cos(angle) * radius, 0.78 + height, monsteraZ + Math.sin(angle) * radius)
    mLeaf.castShadow = true
    sceneObj.add(mLeaf)
  }

  // Medium round plant — right side of shelf (behind snake plant)
  const roundX = 9.2
  const roundZ = SHELF_Z + 0.5
  const roundPot = new THREE.Mesh(new THREE.CylinderGeometry(0.44, 0.32, 0.60, 12), potMat2)
  roundPot.position.set(roundX, 0.30, roundZ)
  roundPot.castShadow = true
  sceneObj.add(roundPot)
  const roundSoil = new THREE.Mesh(new THREE.CylinderGeometry(0.42, 0.42, 0.05, 12), soilMat)
  roundSoil.position.set(roundX, 0.60, roundZ)
  sceneObj.add(roundSoil)
  for (let rIdx = 0; rIdx < 7; rIdx++) {
    const angle = (rIdx / 7) * Math.PI * 2 + 0.2
    const radius = 0.22 + (rIdx % 3) * 0.09
    const height = 0.72 + (rIdx % 4) * 0.18
    const rLeaf = new THREE.Mesh(new THREE.SphereGeometry(0.28 + (rIdx % 2) * 0.07, 7, 5), rIdx % 2 === 0 ? leafA : leafB)
    rLeaf.scale.set(0.8, 0.62, 0.8)
    rLeaf.position.set(roundX + Math.cos(angle) * radius, 0.60 + height, roundZ + Math.sin(angle) * radius)
    rLeaf.castShadow = true
    sceneObj.add(rLeaf)
  }

  // Small pot — left side of shelf, lower (adds layering)
  const smallPotX = -8.4
  const smallPotZ = SHELF_Z + 0.25
  const smallPot = new THREE.Mesh(new THREE.CylinderGeometry(0.22, 0.16, 0.30, 10), potMat2)
  smallPot.position.set(smallPotX, 0.15, smallPotZ)
  sceneObj.add(smallPot)
  const smallSoil = new THREE.Mesh(new THREE.CylinderGeometry(0.21, 0.21, 0.04, 10), soilMat)
  smallSoil.position.set(smallPotX, 0.30, smallPotZ)
  sceneObj.add(smallSoil)
  for (let sIdx = 0; sIdx < 5; sIdx++) {
    const angle = (sIdx / 5) * Math.PI * 2
    const sLeaf = new THREE.Mesh(new THREE.SphereGeometry(0.12, 6, 4), sIdx % 2 === 0 ? leafA : leafB)
    sLeaf.scale.set(0.7, 0.55, 0.7)
    sLeaf.position.set(smallPotX + Math.cos(angle) * 0.14, 0.40 + sIdx * 0.06, smallPotZ + Math.sin(angle) * 0.14)
    sceneObj.add(sLeaf)
  }

  // Small pot on desk
  const deskPot = new THREE.Mesh(new THREE.CylinderGeometry(0.17, 0.13, 0.22, 9), potMat2)
  deskPot.position.set(8.2, 2.315, 3.1)
  sceneObj.add(deskPot)
  for (let deskLeafI = 0; deskLeafI < 4; deskLeafI++) {
    const angle = (deskLeafI / 4) * Math.PI * 2
    const deskLeaf = new THREE.Mesh(new THREE.SphereGeometry(0.1, 6, 4), leafB)
    deskLeaf.scale.set(0.7, 1.3, 0.7)
    deskLeaf.position.set(8.2 + Math.cos(angle) * 0.14, 2.52, 3.1 + Math.sin(angle) * 0.14)
    sceneObj.add(deskLeaf)
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
  const mug = new THREE.Mesh(
    new THREE.CylinderGeometry(0.18, 0.15, 0.35, 10),
    new THREE.MeshPhongMaterial({ color: 0xd4956a, shininess: 60 }),
  )
  mug.position.set(8.5, 2.44, 1.5)
  mug.castShadow = true
  sceneObj.add(mug)

  // Stacked books
  const b1 = new THREE.Mesh(new THREE.BoxGeometry(1.0, 0.18, 0.7), new THREE.MeshPhongMaterial({ color: 0x8e7cc3 }))
  b1.position.set(11.5, 2.35, 1.8)
  b1.castShadow = true
  sceneObj.add(b1)
  const b2 = new THREE.Mesh(new THREE.BoxGeometry(0.9, 0.18, 0.65), new THREE.MeshPhongMaterial({ color: 0xe8a87c }))
  b2.position.set(11.5, 2.53, 1.8)
  b2.rotation.y = 0.15
  b2.castShadow = true
  sceneObj.add(b2)

  // Desk lamp — brass MCM style
  const metalMat = new THREE.MeshPhongMaterial({ color: 0x9a7c38, shininess: 110 })
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
  const bulb = new THREE.Mesh(
    new THREE.SphereGeometry(0.07, 8, 6),
    new THREE.MeshBasicMaterial({ color: 0xffeeaa }),
  )
  bulb.position.set(9.6, 3.4, 2.9)
  sceneObj.add(bulb)
  const lampLight = new THREE.PointLight(0xffd966, 3.0, 8)
  lampLight.position.set(9.6, 3.35, 2.9)
  lampLight.castShadow = true
  lampLight.shadow.mapSize.set(512, 512)
  sceneObj.add(lampLight)

  // Lava lamp on desk — orange/red blobs, right-back corner
  const LX = 12.2
  const LY = 2.26   // desk surface
  const LZ = 3.1
  const metalShiny = new THREE.MeshPhongMaterial({ color: 0xaaaaaa, shininess: 140 })
  const glassMat   = new THREE.MeshPhongMaterial({ color: 0xffffff, transparent: true, opacity: 0.28, shininess: 120 })
  const blobMat    = new THREE.MeshPhongMaterial({ color: 0xff4c1a, shininess: 60 })

  const llBase = new THREE.Mesh(new THREE.CylinderGeometry(0.16, 0.20, 0.20, 14), metalShiny)
  llBase.position.set(LX, LY + 0.10, LZ)
  sceneObj.add(llBase)

  const llGlass = new THREE.Mesh(new THREE.CylinderGeometry(0.13, 0.13, 1.0, 18), glassMat)
  llGlass.position.set(LX, LY + 0.20 + 0.50, LZ)
  sceneObj.add(llGlass)

  const llCap = new THREE.Mesh(new THREE.CylinderGeometry(0.16, 0.12, 0.16, 14), metalShiny)
  llCap.position.set(LX, LY + 0.20 + 1.0 + 0.08, LZ)
  sceneObj.add(llCap)

  ;([
    [0.04,  0.28, -0.02, 0.10, 0.9, 1.4, 0.9],
    [-0.03, 0.62,  0.03, 0.08, 1.0, 0.9, 1.0],
    [0.00,  0.90,  0.00, 0.07, 0.8, 1.2, 0.8],
  ] as [number, number, number, number, number, number, number][]).forEach(([bx, by, bz, r, sx, sy, sz]) => {
    const blob = new THREE.Mesh(new THREE.SphereGeometry(r, 10, 8), blobMat)
    blob.scale.set(sx, sy, sz)
    blob.position.set(LX + bx, LY + 0.20 + by, LZ + bz)
    sceneObj.add(blob)
  })

  const llLight = new THREE.PointLight(0xff4400, 1.6, 6)
  llLight.position.set(LX, LY + 0.70, LZ)
  sceneObj.add(llLight)
}

// ── Seating (MCM sofa, armchair, coffee table, side table) ───────────────────

function buildSeating(sceneObj: THREE.Scene): void {
  const woodMat  = new THREE.MeshPhongMaterial({ color: 0x3d1c02, shininess: 40 })
  const legMat   = new THREE.MeshPhongMaterial({ color: 0x2a1200, shininess: 80 })
  const sofaMat  = new THREE.MeshLambertMaterial({ color: 0xb84a22 }) // burnt orange
  const chairMat = new THREE.MeshLambertMaterial({ color: 0x3d6e8a }) // slate teal

  const SOFA_X = 0.5
  const SOFA_Z = 7.6
  const SEAT_H = 0.44   // height of seat cushion

  // ── Three-seater sofa facing the bookshelf ──────────────────────────────────
  const SOFA_W = 5.4    // width
  const SOFA_D = 2.2    // depth

  const sofaSeat = new THREE.Mesh(new THREE.BoxGeometry(SOFA_W, SEAT_H, SOFA_D), sofaMat)
  sofaSeat.position.set(SOFA_X, SEAT_H / 2 + 0.22, SOFA_Z)
  sofaSeat.castShadow = true
  sceneObj.add(sofaSeat)

  // Backrest
  const sofaBack = new THREE.Mesh(new THREE.BoxGeometry(SOFA_W, 0.96, 0.28), sofaMat)
  sofaBack.position.set(SOFA_X, SEAT_H + 0.22 + 0.50, SOFA_Z + SOFA_D / 2 - 0.14)
  sofaBack.rotation.x = 0.10
  sofaBack.castShadow = true
  sceneObj.add(sofaBack)

  // Armrests
  ;([-(SOFA_W / 2 + 0.14), SOFA_W / 2 + 0.14] as const).forEach((dx) => {
    const armrest = new THREE.Mesh(new THREE.BoxGeometry(0.28, 0.62, SOFA_D), sofaMat)
    armrest.position.set(SOFA_X + dx, SEAT_H / 2 + 0.26, SOFA_Z)
    sceneObj.add(armrest)
  })

  // Low walnut plinth base
  const sofaBase = new THREE.Mesh(new THREE.BoxGeometry(SOFA_W, 0.10, SOFA_D), woodMat)
  sofaBase.position.set(SOFA_X, 0.05, SOFA_Z)
  sceneObj.add(sofaBase)

  // Tapered legs (4)
  ;([[-SOFA_W / 2 + 0.3, -SOFA_D / 2 + 0.3], [SOFA_W / 2 - 0.3, -SOFA_D / 2 + 0.3],
    [-SOFA_W / 2 + 0.3,  SOFA_D / 2 - 0.3], [SOFA_W / 2 - 0.3,  SOFA_D / 2 - 0.3]] as [number, number][]).forEach(
    ([dx, dz]) => {
      const leg = new THREE.Mesh(new THREE.CylinderGeometry(0.052, 0.032, 0.22, 6), legMat)
      leg.position.set(SOFA_X + dx, 0.11, SOFA_Z + dz)
      sceneObj.add(leg)
    },
  )

  // Throw pillows on sofa
  const pillowMat = new THREE.MeshLambertMaterial({ color: 0xe8c86a }) // mustard
  ;([1.6, -1.6] as const).forEach((dx) => {
    const pillow = new THREE.Mesh(new THREE.BoxGeometry(0.62, 0.34, 0.20), pillowMat)
    pillow.position.set(SOFA_X + dx, SEAT_H + 0.22 + 0.18, SOFA_Z + SOFA_D / 2 - 0.22)
    pillow.rotation.x = 0.08
    sceneObj.add(pillow)
  })

  // ── MCM armchair — left of sofa, angled toward center ──────────────────────
  const CHAIR_X = -5.4
  const CHAIR_Z = 6.2
  const CHAIR_W = 2.2   // width
  const CHAIR_D = 1.95  // depth
  const chairGroup = new THREE.Group()
  chairGroup.position.set(CHAIR_X, 0, CHAIR_Z)
  chairGroup.rotation.y = -0.42

  const chairSeat = new THREE.Mesh(new THREE.BoxGeometry(CHAIR_W, 0.42, CHAIR_D), chairMat)
  chairSeat.position.set(0, SEAT_H / 2 + 0.22, 0)
  chairSeat.castShadow = true
  chairGroup.add(chairSeat)

  const chairBack = new THREE.Mesh(new THREE.BoxGeometry(CHAIR_W, 0.90, 0.24), chairMat)
  chairBack.position.set(0, SEAT_H + 0.22 + 0.48, CHAIR_D / 2 - 0.12)
  chairBack.rotation.x = 0.12
  chairGroup.add(chairBack)

  ;([-(CHAIR_W / 2 + 0.11), CHAIR_W / 2 + 0.11] as const).forEach((dx) => {
    const armrest = new THREE.Mesh(new THREE.BoxGeometry(0.22, 0.55, CHAIR_D), chairMat)
    armrest.position.set(dx, SEAT_H / 2 + 0.22, 0)
    chairGroup.add(armrest)
  })

  const chairBase = new THREE.Mesh(new THREE.BoxGeometry(CHAIR_W, 0.10, CHAIR_D), woodMat)
  chairBase.position.set(0, 0.05, 0)
  chairGroup.add(chairBase)

  ;([[-CHAIR_W / 2 + 0.28, -CHAIR_D / 2 + 0.28], [CHAIR_W / 2 - 0.28, -CHAIR_D / 2 + 0.28],
    [-CHAIR_W / 2 + 0.28,  CHAIR_D / 2 - 0.28], [CHAIR_W / 2 - 0.28,  CHAIR_D / 2 - 0.28]] as [number, number][]).forEach(
    ([dx, dz]) => {
      const leg = new THREE.Mesh(new THREE.CylinderGeometry(0.042, 0.026, 0.22, 6), legMat)
      leg.position.set(dx, 0.11, dz)
      chairGroup.add(leg)
    },
  )

  sceneObj.add(chairGroup)

  // ── Round coffee table with tripod legs (MCM icon) ──────────────────────────
  const CT_X = 0.5
  const CT_Z = 5.0
  const coffeeMat = new THREE.MeshPhongMaterial({ color: 0x5c3210, shininess: 55 })

  const ctTop = new THREE.Mesh(new THREE.CylinderGeometry(1.05, 1.05, 0.06, 28), coffeeMat)
  ctTop.position.set(CT_X, 0.44, CT_Z)
  ctTop.castShadow = true
  ctTop.receiveShadow = true
  sceneObj.add(ctTop)

  for (let legIdx = 0; legIdx < 3; legIdx++) {
    const angle = (legIdx / 3) * Math.PI * 2
    const ctLeg = new THREE.Mesh(new THREE.CylinderGeometry(0.025, 0.018, 0.46, 6), legMat)
    ctLeg.position.set(CT_X + Math.cos(angle) * 0.72, 0.23, CT_Z + Math.sin(angle) * 0.72)
    ctLeg.rotation.z =  Math.cos(angle) * 0.20
    ctLeg.rotation.x =  Math.sin(angle) * 0.20
    sceneObj.add(ctLeg)
  }

  // Book on coffee table
  const ctBook = new THREE.Mesh(
    new THREE.BoxGeometry(0.65, 0.07, 0.45),
    new THREE.MeshPhongMaterial({ color: 0x6b3a4e }),
  )
  ctBook.position.set(CT_X - 0.22, 0.485, CT_Z + 0.08)
  ctBook.rotation.y = 0.22
  sceneObj.add(ctBook)

  // Decorative bowl on coffee table
  const bowl = new THREE.Mesh(
    new THREE.CylinderGeometry(0.16, 0.10, 0.09, 18),
    new THREE.MeshPhongMaterial({ color: 0xd4a030, shininess: 100 }),
  )
  bowl.position.set(CT_X + 0.38, 0.485, CT_Z - 0.22)
  sceneObj.add(bowl)

  // ── Side table next to armchair ─────────────────────────────────────────────
  const ST_X = -7.4
  const ST_Z = 7.0
  const stTop = new THREE.Mesh(new THREE.CylinderGeometry(0.40, 0.40, 0.05, 18), coffeeMat)
  stTop.position.set(ST_X, 0.65, ST_Z)
  stTop.castShadow = true
  sceneObj.add(stTop)
  const stStem = new THREE.Mesh(new THREE.CylinderGeometry(0.055, 0.055, 0.58, 8), legMat)
  stStem.position.set(ST_X, 0.325, ST_Z)
  sceneObj.add(stStem)
  const stBase = new THREE.Mesh(new THREE.CylinderGeometry(0.28, 0.33, 0.05, 18), coffeeMat)
  stBase.position.set(ST_X, 0.025, ST_Z)
  sceneObj.add(stBase)
}

// ── Decorations ───────────────────────────────────────────────────────────────

function buildDecorations(sceneObj: THREE.Scene): void {

  // ── Candles on shelf ────────────────────────────────────────────────────────
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
  addCandle( 3.2, 8.15, SHELF_Z - 0.05)
  addCandle( 5.8, 4.28, SHELF_Z - 0.10)

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

  // ── Table lamp on side table (next to armchair) ─────────────────────────────
  const brassLampMat = new THREE.MeshPhongMaterial({ color: 0x9a7c38, shininess: 110 })
  const shadeMat     = new THREE.MeshPhongMaterial({ color: 0xe0c880, shininess: 20, side: THREE.DoubleSide })
  const ST_X = -7.4
  const ST_Z = 7.0

  const tlBase = new THREE.Mesh(new THREE.CylinderGeometry(0.18, 0.22, 0.06, 12), brassLampMat)
  tlBase.position.set(ST_X, 0.70, ST_Z)
  sceneObj.add(tlBase)
  const tlStem = new THREE.Mesh(new THREE.CylinderGeometry(0.028, 0.028, 0.88, 8), brassLampMat)
  tlStem.position.set(ST_X, 1.14, ST_Z)
  sceneObj.add(tlStem)
  const tlShade = new THREE.Mesh(new THREE.ConeGeometry(0.30, 0.38, 14, 1, true), shadeMat)
  tlShade.rotation.x = Math.PI
  tlShade.position.set(ST_X, 1.67, ST_Z)
  sceneObj.add(tlShade)
  const tlBulb = new THREE.Mesh(
    new THREE.SphereGeometry(0.05, 8, 6),
    new THREE.MeshBasicMaterial({ color: 0xffeeaa }),
  )
  tlBulb.position.set(ST_X, 1.60, ST_Z)
  sceneObj.add(tlBulb)
  const tlLight = new THREE.PointLight(0xffd966, 2.6, 9)
  tlLight.position.set(ST_X, 1.58, ST_Z)
  sceneObj.add(tlLight)

  // ── Sputnik pendant chandelier — MCM icon, center of room ──────────────────
  const sputnikMat = new THREE.MeshPhongMaterial({ color: 0xc8b08a, shininess: 130 })

  const sputnikWire = new THREE.Mesh(
    new THREE.CylinderGeometry(0.012, 0.012, 1.6, 5),
    new THREE.MeshBasicMaterial({ color: 0x222222 }),
  )
  sputnikWire.position.set(1.0, 11.2, 1.5)
  sceneObj.add(sputnikWire)

  const sputnikBody = new THREE.Mesh(new THREE.SphereGeometry(0.2, 12, 8), sputnikMat)
  sputnikBody.position.set(1.0, 10.4, 1.5)
  sceneObj.add(sputnikBody)

  const spokeDirections: [number, number, number][] = [
    [1,0,0],[-1,0,0],[0,1,0],[0,-1,0],[0,0,1],[0,0,-1],
    [0.7,0.7,0],[-0.7,0.7,0],[0.7,-0.7,0],[-0.7,-0.7,0],
    [0.7,0,0.7],[0.7,0,-0.7],[-0.7,0,0.7],[-0.7,0,-0.7],
  ]
  spokeDirections.forEach(([sx, sy, sz]) => {
    const spokeLen = 0.95
    const spoke = new THREE.Mesh(new THREE.CylinderGeometry(0.012, 0.012, spokeLen, 5), sputnikMat)
    spoke.position.set(1.0 + sx * spokeLen / 2, 10.4 + sy * spokeLen / 2, 1.5 + sz * spokeLen / 2)
    const dir = new THREE.Vector3(sx, sy, sz).normalize()
    spoke.quaternion.setFromUnitVectors(new THREE.Vector3(0, 1, 0), dir)
    sceneObj.add(spoke)
    const tipBulb = new THREE.Mesh(
      new THREE.SphereGeometry(0.045, 6, 4),
      new THREE.MeshBasicMaterial({ color: 0xfff2aa }),
    )
    tipBulb.position.set(1.0 + sx * spokeLen, 10.4 + sy * spokeLen, 1.5 + sz * spokeLen)
    sceneObj.add(tipBulb)
  })
  const sputnikLight = new THREE.PointLight(0xffeebb, 2.2, 18)
  sputnikLight.position.set(1.0, 10.4, 1.5)
  sceneObj.add(sputnikLight)

  // ── String lights along back wall — warm ambiance ───────────────────────────
  const stringBulbMat = new THREE.MeshBasicMaterial({ color: 0xffe8aa })
  const wireMat = new THREE.MeshBasicMaterial({ color: 0x221100 })

  for (let bulbI = 0; bulbI < 18; bulbI++) {
    const t = bulbI / 17
    const bulbX = -8 + t * 16
    const droop = Math.sin(t * Math.PI) * 0.48
    const bulb = new THREE.Mesh(new THREE.SphereGeometry(0.055, 6, 4), stringBulbMat)
    bulb.position.set(bulbX, 11.4 - droop, -7.0)
    sceneObj.add(bulb)
    if (bulbI < 17) {
      const wire = new THREE.Mesh(new THREE.CylinderGeometry(0.008, 0.008, (16 / 17) * 1.05, 4), wireMat)
      wire.rotation.z = Math.PI / 2
      wire.position.set(bulbX + 8 / 17, 11.4 - Math.sin((t + 0.5 / 17) * Math.PI) * 0.48, -7.0)
      sceneObj.add(wire)
    }
  }
  const stringLight = new THREE.PointLight(0xffe0aa, 0.55, 14)
  stringLight.position.set(0, 11.4, -7.0)
  sceneObj.add(stringLight)

  // ── Paintings ───────────────────────────────────────────────────────────────

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

    const canvas = new THREE.Mesh(
      new THREE.PlaneGeometry(pw, ph),
      new THREE.MeshBasicMaterial({ color: bgColor }),
    )
    canvas.position.z = 0.063
    group.add(canvas)

    shapes.forEach((sh) => {
      const geo = sh.type === 'circle'
        ? new THREE.CircleGeometry(sh.w / 2, 14)
        : new THREE.PlaneGeometry(sh.w, sh.h)
      const mesh = new THREE.Mesh(geo, new THREE.MeshBasicMaterial({ color: sh.color }))
      mesh.position.set(sh.x, sh.y, 0.065)
      group.add(mesh)
    })

    sceneObj.add(group)
  }

  // Back wall
  addPainting(-9,  8.5, -7.9, 0, 2.4, 1.6, 0x1a3a1a, [
    { type: 'rect',   color: 0x0d220d, x:  0,    y: -0.25, w: 2.4,  h: 0.55 },
    { type: 'circle', color: 0x2a6e1a, x: -0.5,  y:  0.1,  w: 0.9,  h: 0.9 },
    { type: 'circle', color: 0x3d8c28, x:  0.4,  y:  0.2,  w: 0.65, h: 0.65 },
    { type: 'circle', color: 0x1e4c10, x:  0.0,  y: -0.05, w: 0.5,  h: 0.5 },
  ], 0x6b3a10)

  addPainting( 0,   7.5, -7.9, 0, 3.0, 2.0, 0xb5411a, [
    { type: 'rect', color: 0xd4601e, x: 0, y:  0.55, w: 2.7, h: 0.75 },
    { type: 'rect', color: 0x8a1c08, x: 0, y: -0.45, w: 2.7, h: 0.65 },
    { type: 'rect', color: 0xc94c14, x: 0, y:  0.05, w: 2.7, h: 0.35 },
  ], 0x2a1200)

  addPainting( 5.5, 8.0, -7.9, 0, 2.8, 1.9, 0x7fa8b0, [
    { type: 'circle', color: 0xd97c28, x: -0.3, y:  0.3,  w: 0.9,  h: 0.9 },
    { type: 'rect',   color: 0x5c8aa0, x:  0,   y: -0.2,  w: 2.8,  h: 0.7 },
    { type: 'circle', color: 0xf5d080, x: -0.3, y:  0.3,  w: 0.55, h: 0.55 },
  ], 0x2a1200)

  addPainting(-2,   6.2, -7.9, 0, 1.4, 1.4, 0x1a0d2e, [
    { type: 'circle', color: 0xffffff, x: -0.3, y:  0.2,  w: 0.15, h: 0.15 },
    { type: 'circle', color: 0xffffff, x:  0.2, y:  0.4,  w: 0.08, h: 0.08 },
    { type: 'circle', color: 0xffffff, x:  0.4, y: -0.1,  w: 0.12, h: 0.12 },
    { type: 'circle', color: 0xaaccff, x: -0.1, y: -0.3,  w: 0.4,  h: 0.4 },
    { type: 'circle', color: 0x88aaee, x:  0.1, y: -0.3,  w: 0.28, h: 0.28 },
  ], 0x4a3010)

  addPainting( 8.5, 5.5, -7.9, 0, 1.5, 2.0, 0x0d3d3a, [
    { type: 'circle', color: 0x1a7a6e, x:  0.1,  y:  0.4, w: 1.1,  h: 1.1 },
    { type: 'rect',   color: 0x0a2e2c, x:  0.0,  y: -0.5, w: 1.5,  h: 0.7 },
    { type: 'circle', color: 0xd4a030, x:  0.35, y:  0.5, w: 0.3,  h: 0.3 },
  ], 0x3d2008)

  addPainting(-7.0, 5.0, -7.9, 0, 1.6, 1.2, 0x2a2012, [
    { type: 'rect',   color: 0xc89820, x: -0.2, y:  0.15, w: 0.9,  h: 0.75 },
    { type: 'rect',   color: 0x3a2c10, x:  0.4, y: -0.15, w: 0.55, h: 0.6 },
    { type: 'circle', color: 0xe0b030, x: -0.4, y: -0.25, w: 0.35, h: 0.35 },
  ], 0x5c3a10)

  // Right wall
  addPainting(13.88, 6.5,  1.5, -Math.PI / 2, 1.6, 2.8, 0x2a1a0a, [
    { type: 'rect',   color: 0x7a4520, x: 0, y:  0.6,  w: 1.6, h: 0.4 },
    { type: 'circle', color: 0xd4956a, x: 0, y:  0.1,  w: 0.6, h: 0.6 },
    { type: 'rect',   color: 0x4a2a08, x: 0, y: -0.8,  w: 1.6, h: 1.2 },
  ], 0x5a3010)

  addPainting(13.88, 7.5,  3.0, -Math.PI / 2, 1.8, 2.6, 0xf5f0e0, [
    { type: 'rect', color: 0x1a3a80, x: -0.45, y:  0.6,  w: 0.82, h: 0.85 },
    { type: 'rect', color: 0xc82020, x:  0.45, y: -0.5,  w: 0.82, h: 0.85 },
    { type: 'rect', color: 0xe8b820, x: -0.45, y: -0.5,  w: 0.82, h: 0.6  },
    { type: 'rect', color: 0x111111, x:  0,    y:  0,    w: 1.8,  h: 0.06 },
    { type: 'rect', color: 0x111111, x: -0.01, y:  0,    w: 0.06, h: 2.6  },
  ], 0x1a1208)

  addPainting(13.88, 9.5, -4.5, -Math.PI / 2, 2.2, 1.5, 0x2255aa, [
    { type: 'rect',   color: 0x1a4488, x:  0,   y: -0.1,  w: 2.2,  h: 0.8 },
    { type: 'circle', color: 0xffffff, x:  0.3, y:  0.3,  w: 0.35, h: 0.35 },
    { type: 'rect',   color: 0xffa040, x: -0.4, y: -0.2,  w: 0.8,  h: 0.12 },
  ], 0x2a1200)

  // Left wall
  addPainting(-13.88, 10.2, 3.5, Math.PI / 2, 2.0, 1.4, 0x3a1a2a, [
    { type: 'circle', color: 0xcc44aa, x: -0.3, y:  0.1,  w: 0.7,  h: 0.7 },
    { type: 'circle', color: 0x8822cc, x:  0.3, y: -0.1,  w: 0.5,  h: 0.5 },
    { type: 'rect',   color: 0x220a30, x:  0,   y:  0,    w: 2.0,  h: 0.2 },
  ], 0x8b5c2a)

  addPainting(-13.88, 5.5, 5.5, Math.PI / 2, 1.2, 2.2, 0xf0e8d8, [
    { type: 'rect',   color: 0x2a3a5c, x:  0,   y:  0.5,  w: 1.0,  h: 0.85 },
    { type: 'circle', color: 0xc84020, x: -0.1, y: -0.3,  w: 0.65, h: 0.65 },
    { type: 'rect',   color: 0xe8c030, x:  0.3, y: -0.6,  w: 0.5,  h: 0.38 },
  ], 0x4a2c08)
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

      const spineMap = volume.spineUrl
        ? loader.load(coverUrl(volume.spineUrl)!)
        : createSpineTexture(collection.manga.edition, collection.manga.title, volume.number, colorInt)
      const spineMat = new THREE.MeshLambertMaterial({ map: spineMap })
      const backMat = volume.backCoverUrl
        ? new THREE.MeshLambertMaterial({ map: loader.load(coverUrl(volume.backCoverUrl)!) })
        : new THREE.MeshLambertMaterial({ color: colorInt })

      // BoxGeometry face order: +x (cover), -x (pages), +y (top), -y (bottom), +z (spine→camera), -z (back)
      const mesh = new THREE.Mesh(geom, [volCoverFaceMat, pagesMat, topMat, topMat, spineMat, backMat])

      const spineZ = SPINE_FACE_Z - BOOK_DEPTH / 2
      mesh.position.set(curX + BOOK_THICK / 2, SHELF_ROW_BOTTOMS[row] + BOOK_H / 2, spineZ)
      mesh.castShadow = true

      originalZ.set(mesh, spineZ)
      bookMap.set(mesh, {
        mangaTitle:   collection.manga.title,
        number:       volume.number,
        coverUrl:     volume.coverUrl ?? collection.manga.coverUrl ?? null,
        spineUrl:     volume.spineUrl,
        backCoverUrl: volume.backCoverUrl,
        edition:      collection.manga.edition,
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

  // Very dim ambient — lamps do the work
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
  buildSeating(scene)
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
        <BaseLoader size="lg" class="mb-3" />
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
            <p class="text-xl font-bold mb-3">Tome {{ selectedBook.number }}</p>
            <button class="btn btn-primary btn-sm gap-2 w-full" @click="show3d = true">
              <Box class="h-4 w-4" />
              Voir en 3D
            </button>
          </div>
        </div>
      </div>
    </Transition>

    <Volume3DViewer
      :open="show3d"
      :volume="selectedBook
        ? { number: selectedBook.number, coverUrl: selectedBook.coverUrl, spineUrl: selectedBook.spineUrl, backCoverUrl: selectedBook.backCoverUrl }
        : null"
      :manga="selectedBook
        ? { title: selectedBook.mangaTitle, edition: selectedBook.edition, coverUrl: selectedBook.coverUrl }
        : null"
      @close="show3d = false"
    />
  </div>
</template>
