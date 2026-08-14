import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import ConsultationPage from './ConsultationPage.vue'
import { fetchConsultation } from '../../entities/consultation/api'
import { fetchHexagram } from '../../entities/hexagram/api'
import { ApiError } from '../../shared/api/http'
import type { Consultation } from '../../entities/consultation/model'
import type { Hexagram } from '../../entities/hexagram/model'

vi.mock('../../entities/consultation/api', () => ({
  fetchConsultation: vi.fn(),
}))

vi.mock('../../entities/hexagram/api', () => ({
  fetchHexagram: vi.fn(),
}))

vi.mock('vue-router', () => ({
  useRoute: () => ({ params: { id: 'abc-123' } }),
}))

const stubs = { RouterLink: { template: '<a><slot /></a>' } }

function sampleHexagram(kingWenNumber: number): Hexagram {
  return {
    kingWenNumber,
    chineseName: '乾',
    pinyin: 'Qián',
    lines: Array.from({ length: 6 }, (_, i) => ({
      position: i + 1,
      polarity: 'yang' as const,
    })),
    upperTrigram: { id: 'Qian', name: 'Qian', chineseName: '乾', pinyin: 'Qián', symbol: '☰' },
    lowerTrigram: { id: 'Qian', name: 'Qian', chineseName: '乾', pinyin: 'Qián', symbol: '☰' },
    judgment: null,
    image: null,
    lineStatements: null,
  }
}

const sampleConsultation: Consultation = {
  id: 'abc-123',
  question: 'Should I take the offer?',
  method: 'three_coins',
  primaryHexagram: { kingWenNumber: 1, chineseName: '乾', pinyin: 'Qián' },
  changingLinePositions: [1],
  resultingHexagram: { kingWenNumber: 44, chineseName: '姤', pinyin: 'Gòu' },
  createdAt: '2026-08-14T10:00:00+00:00',
  notes: [{ label: 'before', text: 'Nervous.', createdAt: '2026-08-14T09:00:00+00:00' }],
  tags: ['career'],
}

describe('ConsultationPage', () => {
  it('renders the consultation with both hexagram diagrams and marks changing lines', async () => {
    vi.mocked(fetchConsultation).mockResolvedValue(sampleConsultation)
    vi.mocked(fetchHexagram).mockImplementation((n: number) => Promise.resolve(sampleHexagram(n)))

    const wrapper = mount(ConsultationPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('Should I take the offer?')
    expect(wrapper.text()).toContain('Nervous.')
    expect(wrapper.text()).toContain('career')
    expect(wrapper.find('[data-position="1"][data-changing="true"]').exists()).toBe(true)
  })

  it('shows "No changing lines" when there are none', async () => {
    vi.mocked(fetchConsultation).mockResolvedValue({
      ...sampleConsultation,
      changingLinePositions: [],
    })
    vi.mocked(fetchHexagram).mockImplementation((n: number) => Promise.resolve(sampleHexagram(n)))

    const wrapper = mount(ConsultationPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('No changing lines')
  })

  it('shows a not-found state on a 404', async () => {
    vi.mocked(fetchConsultation).mockRejectedValue(new ApiError(404, 'Not Found'))

    const wrapper = mount(ConsultationPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text().toLowerCase()).toContain('not found')
  })

  it('shows a generic error state for other failures', async () => {
    vi.mocked(fetchConsultation).mockRejectedValue(new Error('network down'))

    const wrapper = mount(ConsultationPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('network down')
  })
})
