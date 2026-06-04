import { describe, it, expect, afterEach, beforeEach } from 'vitest'
import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import { createPinia, setActivePinia } from 'pinia'
import ShareModal from '../ShareModal.vue'
import fr from '@/i18n/fr.json'
import en from '@/i18n/en.json'
import type { Stats } from '@/types'

const stats: Stats = {
  totalMangas: 12,
  totalOwned: 80,
  totalRead: 40,
  totalWishlist: 5,
  ownedValue: 0,
  wishlistValue: 0,
  totalValue: 0,
  genreBreakdown: {},
  readingStatusBreakdown: {},
  topAuthors: [],
  averageRating: null,
  ratedCount: 0,
  monthlyAdditions: [],
  recentAdditions: [],
}

function mountModal(props: { open: boolean; url: string | null; loading: boolean }) {
  const i18n = createI18n({ legacy: false, locale: 'fr', fallbackLocale: 'en', messages: { en, fr } })
  return mount(ShareModal, {
    props: { ...props, stats },
    global: { plugins: [i18n] },
  })
}

describe('ShareModal', () => {
  beforeEach(() => setActivePinia(createPinia()))
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('renders nothing while closed', () => {
    mountModal({ open: false, url: null, loading: false })
    expect(document.body.textContent).not.toContain('Partager ma bibliothèque')
  })

  it('shows a loading state while the link is generated', () => {
    mountModal({ open: true, url: null, loading: true })
    expect(document.body.textContent).toContain('Création du lien')
  })

  it('shows the preview numbers and the generated link', () => {
    mountModal({ open: true, url: 'https://www.ziggytheque.fr/share/abc', loading: false })
    const text = document.body.textContent ?? ''
    expect(text).toContain('12') // totalMangas
    expect(text).toContain('80') // totalOwned
    expect(text).toContain('50%') // reading progress 40/80
    const input = document.body.querySelector('input') as HTMLInputElement
    expect(input.value).toBe('https://www.ziggytheque.fr/share/abc')
  })

  it('emits close when the close button is clicked', async () => {
    const wrapper = mountModal({ open: true, url: 'https://x/share/abc', loading: false })
    const closeButton = document.body.querySelector('button[aria-label="Fermer"]') as HTMLButtonElement
    closeButton.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await nextTick()
    expect(wrapper.emitted('close')).toBeTruthy()
  })
})
