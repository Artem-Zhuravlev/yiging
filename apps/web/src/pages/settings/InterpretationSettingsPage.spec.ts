import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import InterpretationSettingsPage from './InterpretationSettingsPage.vue'
import {
  fetchInterpretationProfile,
  updateInterpretationProfile,
} from '../../entities/interpretation-profile/api'
import type { InterpretationProfile } from '../../entities/interpretation-profile/model'

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

    expect((wrapper.find('#tone').element as HTMLSelectElement).value).toBe('poetic')
    expect((wrapper.find('#length').element as HTMLSelectElement).value).toBe('brief')
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

    await wrapper.find('#tone').setValue('formal')
    await wrapper.find('#length').setValue('detailed')
    await wrapper.find('#notes').setValue('Prefer directness.')
    await wrapper.find('form').trigger('submit')
    await flushPromises()

    expect(updateInterpretationProfile).toHaveBeenCalledWith({
      tone: 'formal',
      length: 'detailed',
      notes: 'Prefer directness.',
    })
    expect((wrapper.find('#tone').element as HTMLSelectElement).value).toBe('formal')
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
