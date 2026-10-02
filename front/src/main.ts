import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { MutationCache, QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { createI18n } from 'vue-i18n'
import App from './App.vue'
import router from './router'
import './assets/main.css'
import en from './i18n/en.json'
import fr from './i18n/fr.json'
import { useUiStore } from './stores/useUiStore'

const i18n = createI18n({
  legacy: false,
  locale: localStorage.getItem('locale') ?? 'fr',
  fallbackLocale: 'en',
  messages: { en, fr },
})

const pinia = createPinia()

// A mutation that does not handle its own failure still tells the user it failed.
const queryClient = new QueryClient({
  mutationCache: new MutationCache({
    onError: (_error, _variables, _context, mutation) => {
      if (mutation.options.onError) return
      useUiStore(pinia).addToast(i18n.global.t('common.actionFailed'), 'error')
    },
  }),
})

const app = createApp(App)
app.use(pinia)
app.use(router)
app.use(VueQueryPlugin, { queryClient })
app.use(i18n)
app.mount('#app')
