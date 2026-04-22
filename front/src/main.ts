import { createApp } from 'vue'
import { createPinia } from 'pinia'
import piniaPluginPersist from 'pinia-plugin-persistedstate'
import { VueQueryPlugin } from '@tanstack/vue-query'
import { createI18n } from 'vue-i18n'
import App from './App.vue'
import router from './router'
import './assets/main.css'
import en from './i18n/en.json'
import fr from './i18n/fr.json'
import AIcon from '@/components/atoms/AIcon.vue'
import AButton from '@/components/atoms/AButton.vue'
import AIconButton from '@/components/atoms/AIconButton.vue'
import AInput from '@/components/atoms/AInput.vue'
import ATextarea from '@/components/atoms/ATextarea.vue'
import ASelect from '@/components/atoms/ASelect.vue'
import ABadge from '@/components/atoms/ABadge.vue'
import AChip from '@/components/atoms/AChip.vue'
import ASpinner from '@/components/atoms/ASpinner.vue'
import ASkeleton from '@/components/atoms/ASkeleton.vue'
import ADivider from '@/components/atoms/ADivider.vue'
import AEmptyState from '@/components/atoms/AEmptyState.vue'
import ASwitch from '@/components/atoms/ASwitch.vue'
import AProgressBar from '@/components/atoms/AProgressBar.vue'
import AProgressRing from '@/components/atoms/AProgressRing.vue'
import ATag from '@/components/atoms/ATag.vue'
import AAvatar from '@/components/atoms/AAvatar.vue'
import AFade from '@/components/atoms/AFade.vue'
import ASlideUp from '@/components/atoms/ASlideUp.vue'

const i18n = createI18n({
  legacy: false,
  locale: localStorage.getItem('locale') ?? 'fr',
  fallbackLocale: 'en',
  messages: { en, fr },
})

const pinia = createPinia()
pinia.use(piniaPluginPersist)

const app = createApp(App)

// Register common atoms globally
app.component('AIcon', AIcon)
app.component('AButton', AButton)
app.component('AIconButton', AIconButton)
app.component('AInput', AInput)
app.component('ATextarea', ATextarea)
app.component('ASelect', ASelect)
app.component('ABadge', ABadge)
app.component('AChip', AChip)
app.component('ASpinner', ASpinner)
app.component('ASkeleton', ASkeleton)
app.component('ADivider', ADivider)
app.component('AEmptyState', AEmptyState)
app.component('ASwitch', ASwitch)
app.component('AProgressBar', AProgressBar)
app.component('AProgressRing', AProgressRing)
app.component('ATag', ATag)
app.component('AAvatar', AAvatar)
app.component('AFade', AFade)
app.component('ASlideUp', ASlideUp)

app.use(pinia)
app.use(router)
app.use(VueQueryPlugin)
app.use(i18n)
app.mount('#app')
