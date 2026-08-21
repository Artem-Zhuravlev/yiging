import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises, VueWrapper } from '@vue/test-utils'
import InterpretationSettingsPage from './InterpretationSettingsPage.vue'
import {
  fetchInterpretationProfile,
  updateInterpretationProfile,
} from '../../entities/interpretation-profile/api'
import type { InterpretationProfile } from '../../entities/interpretation-profile/model'

// PrimeVue's Select has no native <select> to target; findComponent()'s WrapperLike return type
// doesn't expose .props()/.setValue() without narrowing away from the untyped default first.
function selectValue(wrapper: ReturnType<typeof mount>, selector: string): string {
  const component = wrapper.findComponent(selector) as VueWrapper
  return (component.props() as { modelValue: string }).modelValue
}

async function setSelectValue(wrapper: ReturnType<typeof mount>, selector: string, value: string): Promise<void> {
  await wrapper.findComponent(selector).setValue(value)
}

vi.mock('../../entities/interpretation-profile/api', () => ({
  fetchInterpretationProfile: vi.fn(),
  updateInterpretationProfile: vi.fn(),
}))

const sample: InterpretationProfile = { tone: 'neutral', length: 'standard', notes: null }

describe('InterpretationSettingsPage', () => {
  it('shows a loading state before the fetch resolves', () => {
    vi.mocked(fetchInterpretationProfile).mockReturnValue(new Promise(() => {}))

    const wrapper = mount(InterpretationSettingsPage)

    expect(wrapper.text()).toContain('Loading')
  })

  it('pre-fills the form from the loaded profile', async () => {
    vi.mocked(fetchInterpretationProfile).mockResolvedValue({
      tone: 'poetic',
      length: 'brief',
      notes: 'Be vivid.',
    })

    const wrapper = mount(InterpretationSettingsPage)
    await flushPromises()

    expect(selectValue(wrapper, '#tone')).toBe('poetic')
    expect(selectValue(wrapper, '#length')).toBe('brief')
    expect((wrapper.find('#notes').element as HTMLTextAreaElement).value).toBe('Be vivid.')
  })

  it('saves the form and shows the updated profile', async () => {
    vi.mocked(fetchInterpretationProfile).mockResolvedValue(sample)
    vi.mocked(updateInterpretationProfile).mockResolvedValue({
      tone: 'formal',
      length: 'detailed',
      notes: 'Prefer directness.',
    })

    const wrapper = mount(InterpretationSettingsPage)
    await flushPromises()

    await setSelectValue(wrapper, '#tone', 'formal')
    await setSelectValue(wrapper, '#length', 'detailed')
    await wrapper.find('#notes').setValue('Prefer directness.')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(updateInterpretationProfile).toHaveBeenCalledWith({
      tone: 'formal',
      length: 'detailed',
      notes: 'Prefer directness.',
    })
    expect(selectValue(wrapper, '#tone')).toBe('formal')
  })

  it('sends null for blank notes', async () => {
    vi.mocked(fetchInterpretationProfile).mockResolvedValue(sample)
    vi.mocked(updateInterpretationProfile).mockResolvedValue(sample)

    const wrapper = mount(InterpretationSettingsPage)
    await flushPromises()

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(updateInterpretationProfile).toHaveBeenCalledWith({
      tone: 'neutral',
      length: 'standard',
      notes: null,
    })
  })

  it('shows an inline error when saving fails', async () => {
    vi.mocked(fetchInterpretationProfile).mockResolvedValue(sample)
    vi.mocked(updateInterpretationProfile).mockRejectedValue(new Error('save failed'))

    const wrapper = mount(InterpretationSettingsPage)
    await flushPromises()

    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('save failed')
  })

  it('shows an error message when the fetch fails', async () => {
    vi.mocked(fetchInterpretationProfile).mockRejectedValue(new Error('network down'))

    const wrapper = mount(InterpretationSettingsPage)
    await flushPromises()

    expect(wrapper.text()).toContain('network down')
  })
})
