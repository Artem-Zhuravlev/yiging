import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import ConsultationHistoryPage from './ConsultationHistoryPage.vue'
import { fetchConsultations } from '../../entities/consultation/api'
import type { Consultation } from '../../entities/consultation/model'

vi.mock('../../entities/consultation/api', () => ({
  fetchConsultations: vi.fn(),
}))

const stubs = { RouterLink: { template: '<a><slot /></a>' } }

function sample(id: string, question: string): Consultation {
  return {
    id,
    question,
    method: 'three_coins',
    primaryHexagram: { kingWenNumber: 1, chineseName: '乾', pinyin: 'Qián' },
    changingLinePositions: [],
    resultingHexagram: { kingWenNumber: 1, chineseName: '乾', pinyin: 'Qián' },
    createdAt: '2026-08-14T10:00:00+00:00',
    notes: [],
    tags: [],
  }
}

describe('ConsultationHistoryPage', () => {
  it('renders every consultation, linking to its detail page', async () => {
    vi.mocked(fetchConsultations).mockResolvedValue([
      sample('1', 'First?'),
      sample('2', 'Second?'),
    ])

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.findAll('a')).toHaveLength(2)
    expect(wrapper.text()).toContain('First?')
    expect(wrapper.text()).toContain('Second?')
  })

  it('shows an empty state when there are no consultations', async () => {
    vi.mocked(fetchConsultations).mockResolvedValue([])

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('No consultations yet')
  })

  it('shows an error message when the fetch fails', async () => {
    vi.mocked(fetchConsultations).mockRejectedValue(new Error('network down'))

    const wrapper = mount(ConsultationHistoryPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('network down')
  })
})
