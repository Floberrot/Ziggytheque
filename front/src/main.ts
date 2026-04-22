import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { VueQueryPlugin } from '@tanstack/vue-query'
import { createI18n } from 'vue-i18n'
import App from './App.vue'
import router from './router'
import './assets/main.css'
import en from './i18n/en.json'
import fr from './i18n/fr.json'

const i18n = createI18n({
  legacy: false,
  locale: localStorage.getItem('locale') ?? 'fr',
  fallbackLocale: 'en',
  messages: { en, fr },
})

const pinia = createPinia()

const app = createApp(App)
app.use(pinia)
app.use(router)
app.use(VueQueryPlugin)
app.use(i18n)
app.mount('#app')
