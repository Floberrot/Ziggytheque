import { describe, it, expect, afterEach } from 'vitest'
import { nextTick } from 'vue'
import { mount } from '@vue/test-utils'
import BaseModal from '../BaseModal.vue'

function mountModal(props: Partial<InstanceType<typeof BaseModal>['$props']> = {}, slotContent = '<p>Contenu du dialogue</p>') {
  return mount(BaseModal, {
    props: { open: true, ...props },
    slots: { default: slotContent },
  })
}

function pressEscape(): void {
  window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
}

describe('BaseModal', () => {
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('renders nothing while closed', () => {
    mountModal({ open: false })
    expect(document.body.textContent).not.toContain('Contenu du dialogue')
  })

  it('renders the slot content in a teleported dialog when open', () => {
    mountModal()
    expect(document.body.textContent).toContain('Contenu du dialogue')
    const dialog = document.body.querySelector('[role="dialog"]')
    expect(dialog).not.toBeNull()
    expect(dialog!.getAttribute('aria-modal')).toBe('true')
  })

  it('uses the bottom-sheet layout by default and the centered layout with variant center', () => {
    const sheet = mountModal()
    expect(document.body.querySelector('[role="dialog"]')!.className).toContain('items-end')
    sheet.unmount()
    document.body.innerHTML = ''

    mountModal({ variant: 'center' })
    const dialogClass = document.body.querySelector('[role="dialog"]')!.className
    expect(dialogClass).toContain('items-center')
    expect(dialogClass).not.toContain('items-end')
  })

  it('applies safe-area bottom padding to the sheet panel', () => {
    mountModal()
    const panel = document.body.querySelector('.base-modal-panel')!
    expect(panel.className).toContain('safe-bottom')
  })

  it('emits close when the backdrop is clicked', async () => {
    const wrapper = mountModal()
    const backdrop = document.body.querySelector('[role="dialog"] > div') as HTMLElement
    backdrop.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await nextTick()
    expect(wrapper.emitted('close')).toBeTruthy()
  })

  it('does not emit close on backdrop click when closeOnBackdrop is false', async () => {
    const wrapper = mountModal({ closeOnBackdrop: false })
    const backdrop = document.body.querySelector('[role="dialog"] > div') as HTMLElement
    backdrop.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await nextTick()
    expect(wrapper.emitted('close')).toBeFalsy()
  })

  it('emits close on Escape', async () => {
    const wrapper = mountModal()
    pressEscape()
    await nextTick()
    expect(wrapper.emitted('close')).toBeTruthy()
  })

  it('does not emit close on Escape when closeOnEscape is false', async () => {
    const wrapper = mountModal({ closeOnEscape: false })
    pressEscape()
    await nextTick()
    expect(wrapper.emitted('close')).toBeFalsy()
  })

  it('stops listening for Escape once closed', async () => {
    const wrapper = mountModal()
    await wrapper.setProps({ open: false })
    pressEscape()
    await nextTick()
    expect(wrapper.emitted('close')).toBeFalsy()
  })

  it('keeps Tab focus inside the panel', async () => {
    mountModal({}, '<button id="first">Premier</button><button id="last">Dernier</button>')
    await nextTick()
    const last = document.getElementById('last') as HTMLButtonElement
    last.focus()
    window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab' }))
    await nextTick()
    expect(document.activeElement?.id).toBe('first')
  })
})
