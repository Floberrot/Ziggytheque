import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { MutationCache, QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import App from './App.vue'
import router from './router'
import './assets/main.css'
import { i18n } from './i18n'
import { useUiStore } from './stores/useUiStore'

// Screen readers and the browser read the page in the language the user picked.
document.documentElement.lang = i18n.global.locale.value

const pinia = createPinia()

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      // Going back to a page (or to the tab) within 30 s shows what is loaded instead
      // of fetching it again; every mutation invalidates what it changes anyway.
      staleTime: 30_000,
    },
  },
  // A mutation that does not handle its own failure still tells the user it failed.
  mutationCache: new MutationCache({
    onError: (_error, _variables, _context, mutation) => {
      if (mutation.options.onError) return
      useUiStore(pinia).addToast(i18n.global.t('common.actionFailed'), 'error')
    },
  }),
})

// A deploy replaces the hashed bundles: a tab opened before it fails to load a lazy
// page or component. Reload once to pick up the new build (not twice in a row).
const CHUNK_RELOAD_KEY = 'chunk-reload-at'
window.addEventListener('vite:preloadError', (event) => {
  let lastReloadAt = 0
  try {
    lastReloadAt = Number(sessionStorage.getItem(CHUNK_RELOAD_KEY) ?? 0)
  } catch {
    // Storage unavailable: still reload once.
  }
  if (Date.now() - lastReloadAt < 10_000) return
  event.preventDefault()
  try {
    sessionStorage.setItem(CHUNK_RELOAD_KEY, String(Date.now()))
  } catch {
    // Storage unavailable.
  }
  window.location.reload()
})

const app = createApp(App)
app.use(pinia)
app.use(router)
app.use(VueQueryPlugin, { queryClient })
app.use(i18n)
app.mount('#app')
