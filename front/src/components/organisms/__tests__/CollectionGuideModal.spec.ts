import { describe, it, expect, afterEach } from 'vitest'
import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import CollectionGuideModal from '../CollectionGuideModal.vue'
import fr from '@/i18n/fr.json'
import en from '@/i18n/en.json'

function mountGuide(open: boolean) {
  const i18n = createI18n({ legacy: false, locale: 'fr', fallbackLocale: 'en', messages: { en, fr } })
  return mount(CollectionGuideModal, {
    props: { open },
    global: { plugins: [i18n] },
  })
}

// The guide is teleported to <body>, so interactions go through real DOM nodes.
async function clickButtonByText(match: string): Promise<void> {
  const button = Array.from(document.body.querySelectorAll('button'))
    .find((candidate) => (candidate.textContent ?? '').includes(match))
  if (!button) throw new Error(`No button containing "${match}"`)
  button.dispatchEvent(new MouseEvent('click', { bubbles: true }))
  await nextTick()
}

describe('CollectionGuideModal', () => {
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('renders nothing while closed', () => {
    mountGuide(false)
    expect(document.body.textContent).not.toContain('Guide & aide')
  })

  it('shows the statuses tab with the full legend by default', () => {
    mountGuide(true)
    const text = document.body.textContent ?? ''
    expect(text).toContain('Guide & aide')
    // Every status of the legend is documented
    expect(text).toContain('Possédé')
    expect(text).toContain('Souhaité')
    expect(text).toContain('Annoncé')
    expect(text).toContain('Non suivi')
    expect(text).toContain('Règles à connaître')
  })

  it('switches to the dashboard tab and explains the calculations', async () => {
    mountGuide(true)
    await clickButtonByText('Tableau de bord')
    const text = document.body.textContent ?? ''
    expect(text).toContain('Valeur possédée')
    expect(text).toContain('Progression de lecture')
  })

  it('switches to the covers tab and lists the search methods', async () => {
    mountGuide(true)
    await clickButtonByText('Couvertures')
    const text = document.body.textContent ?? ''
    expect(text).toContain('Recherche par titre')
    expect(text).toContain('Recherche par ISBN')
    expect(text).toContain('Scan du code-barres')
    // The "what is an ISBN" explainer documents both accepted lengths
    expect(text).toContain('C’est quoi un ISBN ?')
    expect(text).toContain('ISBN-10 — 10 chiffres')
    expect(text).toContain('ISBN-13 — 13 chiffres')
  })

  it('emits close when the footer button is clicked', async () => {
    const wrapper = mountGuide(true)
    await clickButtonByText('J’ai compris')
    expect(wrapper.emitted('close')).toBeTruthy()
  })
})
