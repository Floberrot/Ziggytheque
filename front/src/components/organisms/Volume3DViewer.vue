<script setup lang="ts">
import { ref, watch, onUnmounted, nextTick } from 'vue'
import * as THREE from 'three'
import { OrbitControls } from 'three/addons/controls/OrbitControls.js'
import { X } from 'lucide-vue-next'
import { coverUrl } from '@/utils/coverUrl'
import { seedColorInt, createSpineTexture, createBackTexture, VIEWER_BOOK } from '@/utils/bookTextures'

interface ViewerVolume {
  number: number
  coverUrl: string | null
  spineUrl: string | null
  backCoverUrl: string | null
}
interface ViewerManga {
  title: string
  edition: string | null
  coverUrl: string | null
}

const props = defineProps<{
  open: boolean
  volume: ViewerVolume | null
  manga: ViewerManga | null
}>()
const emit = defineEmits<{ close: [] }>()

const containerRef = ref<HTMLDivElement>()
const canvasRef = ref<HTMLCanvasElement>()

let renderer: THREE.WebGLRenderer | null = null
let scene: THREE.Scene | null = null
let camera: THREE.PerspectiveCamera | null = null
let controls: OrbitControls | null = null
let animFrameId = 0
let isReady = false
const disposables: { dispose(): void }[] = []

function containerSize(): { width: number; height: number } {
  const el = containerRef.value
  return el
    ? { width: el.clientWidth, height: el.clientHeight }
    : { width: window.innerWidth, height: window.innerHeight }
}

function disposeScene(): void {
  if (animFrameId) cancelAnimationFrame(animFrameId)
  animFrameId = 0
  controls?.dispose()
  for (const d of disposables) d.dispose()
  disposables.length = 0
  renderer?.dispose()
  renderer = null
  scene = null
  camera = null
  controls = null
  isReady = false
}

// Covers served through the same-origin /proxy/cover endpoint don't taint the
// WebGL canvas; direct cross-origin hosts may, exactly as on the 3D shelf.
function loadTexture(loader: THREE.TextureLoader, url: string | null): THREE.Texture | null {
  const resolved = coverUrl(url)
  if (!resolved) return null
  const texture = loader.load(resolved)
  texture.colorSpace = THREE.SRGBColorSpace
  disposables.push(texture)
  return texture
}

function buildBook(sceneObj: THREE.Scene, volume: ViewerVolume, manga: ViewerManga): void {
  const loader = new THREE.TextureLoader()
  const colorInt = seedColorInt(`${manga.title}-${volume.number}`)

  const pagesMat = new THREE.MeshStandardMaterial({ color: 0xf0ead8, roughness: 0.95 })
  const topMat = new THREE.MeshStandardMaterial({ color: 0xe8e0cc, roughness: 0.9 })

  const coverTexture = loadTexture(loader, volume.coverUrl ?? manga.coverUrl)
  const coverMat = coverTexture
    ? new THREE.MeshStandardMaterial({ map: coverTexture, roughness: 0.45, metalness: 0.04 })
    : new THREE.MeshStandardMaterial({ color: colorInt, roughness: 0.6 })

  const spineTexture = volume.spineUrl ? loadTexture(loader, volume.spineUrl) : null
  const spineMat = new THREE.MeshStandardMaterial({
    map: spineTexture ?? createSpineTexture(manga.edition, manga.title, volume.number, colorInt),
    roughness: 0.55,
  })

  const backTexture = volume.backCoverUrl ? loadTexture(loader, volume.backCoverUrl) : null
  const backMat = new THREE.MeshStandardMaterial({
    map: backTexture ?? createBackTexture(colorInt),
    roughness: 0.6,
  })

  const { width, height, depth } = VIEWER_BOOK
  const geometry = new THREE.BoxGeometry(width, height, depth)
  disposables.push(geometry)

  // BoxGeometry face order: +x, -x, +y, -y, +z, -z.
  // Cover faces the camera (+z); spine on the left edge (-x); fore-edge pages (+x).
  const materials = [pagesMat, spineMat, topMat, topMat, coverMat, backMat]
  materials.forEach((material) => disposables.push(material))

  sceneObj.add(new THREE.Mesh(geometry, materials))
}

function initScene(): void {
  if (!canvasRef.value || !props.volume || !props.manga || isReady) return
  disposeScene()

  const { width, height } = containerSize()

  renderer = new THREE.WebGLRenderer({ canvas: canvasRef.value, antialias: true, alpha: true })
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2))
  renderer.setSize(width, height)

  scene = new THREE.Scene()

  camera = new THREE.PerspectiveCamera(38, width / height, 0.1, 100)
  camera.position.set(0.5, 0.18, 2.5)

  scene.add(new THREE.AmbientLight(0xffffff, 0.9))
  const keyLight = new THREE.DirectionalLight(0xffffff, 1.7)
  keyLight.position.set(2.5, 3, 4)
  scene.add(keyLight)
  const fillLight = new THREE.DirectionalLight(0xbcd2ff, 0.5)
  fillLight.position.set(-3, 1, 2.5)
  scene.add(fillLight)
  const rimLight = new THREE.DirectionalLight(0xffffff, 0.65)
  rimLight.position.set(-1.5, 2, -4)
  scene.add(rimLight)

  buildBook(scene, props.volume, props.manga)

  controls = new OrbitControls(camera, renderer.domElement)
  controls.enableDamping = true
  controls.dampingFactor = 0.09
  controls.enablePan = false
  controls.minDistance = 1.4
  controls.maxDistance = 5
  controls.target.set(0, 0, 0)
  controls.update()

  isReady = true

  const animate = (): void => {
    animFrameId = requestAnimationFrame(animate)
    controls!.update()
    renderer!.render(scene!, camera!)
  }
  animate()
}

function onResize(): void {
  if (!renderer || !camera) return
  const { width, height } = containerSize()
  camera.aspect = width / height
  camera.updateProjectionMatrix()
  renderer.setSize(width, height)
}

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') emit('close')
}

watch(
  () => props.open,
  async (open) => {
    if (open) {
      await nextTick()
      window.addEventListener('resize', onResize)
      window.addEventListener('keydown', onKeydown)
      initScene()
    } else {
      window.removeEventListener('resize', onResize)
      window.removeEventListener('keydown', onKeydown)
      disposeScene()
    }
  },
)

onUnmounted(() => {
  window.removeEventListener('resize', onResize)
  window.removeEventListener('keydown', onKeydown)
  disposeScene()
})
</script>

<template>
  <Teleport to="body">
    <Transition name="viewer-fade">
      <div
        v-if="open && volume && manga"
        class="fixed inset-0 z-[70] flex flex-col bg-black/85 backdrop-blur-md"
      >
        <div class="flex items-center justify-between gap-3 px-5 py-3 text-white">
          <div class="min-w-0">
            <p class="truncate text-[11px] uppercase tracking-wider text-white/45">{{ manga.title }}</p>
            <p class="font-bold leading-tight">Tome {{ volume.number }} · rendu 3D</p>
          </div>
          <button
            class="btn btn-sm btn-circle btn-ghost text-white/80 hover:text-white"
            aria-label="Fermer"
            @click="emit('close')"
          >
            <X class="h-5 w-5" />
          </button>
        </div>

        <div ref="containerRef" class="relative min-h-0 flex-1">
          <canvas ref="canvasRef" class="absolute inset-0 h-full w-full" />
        </div>

        <p class="select-none py-3 text-center text-xs text-white/40">
          Glisse pour tourner le tome · molette pour zoomer
        </p>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.viewer-fade-enter-active,
.viewer-fade-leave-active {
  transition: opacity 0.2s ease;
}
.viewer-fade-enter-from,
.viewer-fade-leave-to {
  opacity: 0;
}
</style>
