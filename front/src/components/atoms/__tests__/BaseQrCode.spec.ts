import { describe, it, expect, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

const { toDataURL } = vi.hoisted(() => ({ toDataURL: vi.fn() }))
vi.mock('qrcode', () => ({ toDataURL }))

import BaseQrCode from '../BaseQrCode.vue'

describe('BaseQrCode', () => {
  beforeEach(() => {
    toDataURL.mockReset()
    toDataURL.mockResolvedValue('data:image/png;base64,QR')
  })

  it('draws the code once the QR library is loaded', async () => {
    const wrapper = mount(BaseQrCode, { props: { value: 'https://ziggy.test/scan/abc', size: 180 } })

    await vi.waitFor(() => expect(wrapper.find('img').exists()).toBe(true))
    expect(wrapper.find('img').attributes('src')).toBe('data:image/png;base64,QR')
    expect(wrapper.find('img').attributes('width')).toBe('180')
    expect(toDataURL).toHaveBeenCalledWith('https://ziggy.test/scan/abc', { width: 180 })
  })

  it('draws it again when the value changes', async () => {
    const wrapper = mount(BaseQrCode, { props: { value: 'first' } })
    await vi.waitFor(() => expect(toDataURL).toHaveBeenCalledTimes(1))

    await wrapper.setProps({ value: 'second', size: 120 })

    await vi.waitFor(() => expect(toDataURL).toHaveBeenLastCalledWith('second', { width: 120 }))
  })

  it('draws nothing without a value', async () => {
    const wrapper = mount(BaseQrCode, { props: { value: '' } })
    await flushPromises()

    expect(wrapper.find('img').exists()).toBe(false)
    expect(toDataURL).not.toHaveBeenCalled()
  })

  it('draws nothing when the code cannot be generated', async () => {
    toDataURL.mockRejectedValue(new Error('too long for a QR code'))
    const wrapper = mount(BaseQrCode, { props: { value: 'x'.repeat(5000) } })

    await vi.waitFor(() => expect(toDataURL).toHaveBeenCalled())
    await flushPromises()
    expect(wrapper.find('img').exists()).toBe(false)
  })
})
