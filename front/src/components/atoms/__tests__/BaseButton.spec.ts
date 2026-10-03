import { describe, it, expect, vi } from 'vitest'
import { defineComponent, h, nextTick, ref } from 'vue'
import { mount } from '@vue/test-utils'
import { createI18n } from 'vue-i18n'
import BaseButton from '../BaseButton.vue'
import fr from '@/i18n/fr.json'
import en from '@/i18n/en.json'

function i18n() {
  return createI18n({ legacy: false, locale: 'fr', fallbackLocale: 'en', messages: { en, fr } })
}

function mountButton(
  props: { loading?: boolean; disabled?: boolean; type?: 'button' | 'submit' | 'reset' } = {},
  options: { withIcon?: boolean; onClick?: (event: MouseEvent) => void } = {},
) {
  return mount(BaseButton, {
    props: { ...props, onClick: options.onClick },
    attrs: { class: 'btn btn-primary' },
    slots: {
      default: () => 'Enregistrer',
      ...(options.withIcon ? { icon: () => h('svg', { class: 'test-icon' }) } : {}),
    },
    global: { plugins: [i18n()] },
  })
}

describe('BaseButton', () => {
  it('renders a plain button with its classes, label and icon while idle', () => {
    const wrapper = mountButton({}, { withIcon: true })

    expect(wrapper.element.tagName).toBe('BUTTON')
    expect(wrapper.attributes('type')).toBe('button')
    expect(wrapper.classes()).toEqual(expect.arrayContaining(['btn', 'btn-primary']))
    expect(wrapper.text()).toContain('Enregistrer')
    expect(wrapper.find('.test-icon').exists()).toBe(true)
    expect(wrapper.find('[role="status"]').exists()).toBe(false)
    expect(wrapper.attributes('aria-busy')).toBeUndefined()
  })

  it('emits click while idle', async () => {
    const onClick = vi.fn()
    const wrapper = mountButton({}, { onClick })

    await wrapper.trigger('click')

    expect(onClick).toHaveBeenCalledTimes(1)
  })

  it('shows the loader in place of its icon and keeps its label while loading', () => {
    const wrapper = mountButton({ loading: true }, { withIcon: true })

    expect(wrapper.find('[role="status"]').exists()).toBe(true)
    expect(wrapper.find('.test-icon').exists()).toBe(false)
    expect(wrapper.text()).toContain('Enregistrer')
  })

  it('puts the loader before the label when it has no icon', () => {
    const wrapper = mountButton({ loading: true })

    const button = wrapper.element as HTMLElement
    expect(button.firstElementChild?.getAttribute('role')).toBe('status')
    expect(wrapper.text()).toContain('Enregistrer')
  })

  it('is busy, not disabled, while loading — it keeps its colours and the focus', () => {
    const wrapper = mountButton({ loading: true, disabled: true })

    expect(wrapper.attributes('aria-busy')).toBe('true')
    expect(wrapper.attributes('aria-disabled')).toBe('true')
    expect(wrapper.attributes('disabled')).toBeUndefined()
  })

  it('ignores clicks while loading', async () => {
    const onClick = vi.fn()
    const wrapper = mountButton({ loading: true }, { onClick })

    await wrapper.trigger('click')

    expect(onClick).not.toHaveBeenCalled()
  })

  it('is natively disabled when disabled and idle', () => {
    const wrapper = mountButton({ disabled: true })

    expect(wrapper.attributes('disabled')).toBeDefined()
    expect(wrapper.attributes('aria-busy')).toBeUndefined()
  })

  it('submits its form while idle but not a second time while loading', async () => {
    const onSubmit = vi.fn()
    const loading = ref(false)
    const LoginForm = defineComponent({
      setup() {
        return () =>
          h(
            'form',
            {
              onSubmit: (event: Event) => {
                event.preventDefault()
                onSubmit()
              },
            },
            [
              h(
                BaseButton,
                { type: 'submit', loading: loading.value },
                { default: () => 'Se connecter' },
              ),
            ],
          )
      },
    })
    // Attached: a detached form cannot navigate, so jsdom would never submit it.
    const wrapper = mount(LoginForm, { attachTo: document.body, global: { plugins: [i18n()] } })

    await wrapper.find('button').trigger('click')
    expect(onSubmit).toHaveBeenCalledTimes(1)

    loading.value = true
    await nextTick()
    await wrapper.find('button').trigger('click')

    expect(onSubmit).toHaveBeenCalledTimes(1)
    wrapper.unmount()
  })
})
