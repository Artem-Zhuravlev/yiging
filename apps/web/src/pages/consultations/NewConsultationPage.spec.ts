import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import NewConsultationPage from './NewConsultationPage.vue'
import { createConsultation } from '../../entities/consultation/api'
import { ApiError } from '../../shared/api/http'
import type { Consultation, NewConsultationRequest } from '../../entities/consultation/model'

const push = vi.fn()

vi.mock('../../entities/consultation/api', () => ({
  createConsultation: vi.fn(),
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push }),
}))

const sample: Consultation = {
  id: 'new-id',
  question: 'Test?',
  method: 'three_coins',
  primaryHexagram: { kingWenNumber: 1, chineseName: '乾', pinyin: 'Qián' },
  changingLinePositions: [],
  resultingHexagram: { kingWenNumber: 1, chineseName: '乾', pinyin: 'Qián' },
  createdAt: '2026-08-14T10:00:00+00:00',
  notes: [],
  tags: [],
}

describe('NewConsultationPage', () => {
  beforeEach(() => {
    push.mockClear()
    vi.mocked(createConsultation).mockClear()
  })

  it('submits a three_coins request and navigates to the new consultation', async () => {
    vi.mocked(createConsultation).mockResolvedValue(sample)

    const wrapper = mount(NewConsultationPage)
    await wrapper.find('#question').setValue('Test?')
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(createConsultation).toHaveBeenCalledWith({ question: 'Test?', method: 'three_coins' })
    expect(push).toHaveBeenCalledWith('/consultations/new-id')
  })

  it('submits a manual request with exactly 6 lines when manual is selected', async () => {
    vi.mocked(createConsultation).mockResolvedValue(sample)

    const wrapper = mount(NewConsultationPage)
    await wrapper.find('#question').setValue('Test?')
    await wrapper.find('input[type="radio"][value="manual"]').setValue()
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(createConsultation).toHaveBeenCalledTimes(1)
    const request = vi.mocked(createConsultation).mock.calls[0]![0] as NewConsultationRequest
    expect(request.method).toBe('manual')
    if (request.method === 'manual') {
      expect(request.lines).toHaveLength(6)
      expect(request.lines.every((line) => line.polarity === 'yang' && !line.changing)).toBe(true)
    }
  })

  it('reflects a changed line into the submitted manual payload', async () => {
    vi.mocked(createConsultation).mockResolvedValue(sample)

    const wrapper = mount(NewConsultationPage)
    await wrapper.find('#question').setValue('Test?')
    await wrapper.find('input[type="radio"][value="manual"]').setValue()
    await wrapper.find('[data-position="1"] input[type="radio"][value="yin"]').setValue()
    await wrapper.find('[data-position="1"] input[type="checkbox"]').setValue(true)
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    const request = vi.mocked(createConsultation).mock.calls[0]![0] as NewConsultationRequest
    if (request.method === 'manual') {
      expect(request.lines[0]).toEqual({ polarity: 'yin', changing: true })
    }
  })

  it('shows the API error message inline on a 422 without navigating', async () => {
    vi.mocked(createConsultation).mockRejectedValue(new ApiError(422, 'Question is required.'))

    const wrapper = mount(NewConsultationPage)
    await wrapper.find('form').trigger('submit.prevent')
    await flushPromises()

    expect(wrapper.text()).toContain('Question is required.')
    expect(push).not.toHaveBeenCalled()
  })
})
