import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import SharedConsultationPage from './SharedConsultationPage.vue'
import { fetchConsultation } from '../../entities/consultation/api'
import { fetchHexagram } from '../../entities/hexagram/api'
import { ApiError } from '../../shared/api/http'
import type { ConsultationDetail } from '../../entities/consultation/model'
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
    symbol: '䷀',
    lines: Array.from({ length: 6 }, (_, i) => ({ position: i + 1, polarity: 'yang' as const })),
    upperTrigram: { id: 'Qian', name: 'Qian', chineseName: '乾', pinyin: 'Qián', symbol: '☰' },
    lowerTrigram: { id: 'Qian', name: 'Qian', chineseName: '乾', pinyin: 'Qián', symbol: '☰' },
    judgment: null,
    image: null,
    lineStatements: null,
    relationships: {
      nuclear: { kingWenNumber, chineseName: '乾', pinyin: 'Qián' },
      reversed: { kingWenNumber, chineseName: '乾', pinyin: 'Qián' },
      complement: { kingWenNumber, chineseName: '乾', pinyin: 'Qián' },
    },
    favorite: false,
  }
}

const sampleConsultation: ConsultationDetail = {
  id: 'abc-123',
  question: 'Should I take the offer?',
  method: 'three_coins',
  primaryHexagram: { kingWenNumber: 1, chineseName: '乾', pinyin: 'Qián' },
  changingLinePositions: [1],
  resultingHexagram: { kingWenNumber: 44, chineseName: '姤', pinyin: 'Gòu' },
  createdAt: '2026-08-14T10:00:00+00:00',
  notes: [{ label: 'before', text: 'Nervous.', createdAt: '2026-08-14T09:00:00+00:00' }],
  tags: ['career'],
  context: 'Some context.',
  whatHappenedBefore: null,
  whatUserWantsToUnderstand: null,
  backgroundInformation: null,
  initialInterpretation: null,
  outcome: {
    whatActuallyHappened: 'Took it.',
    outcome: null,
    reflection: null,
    recordedAt: '2026-08-20T09:00:00+00:00',
    interpretationLens: null,
    interpretationSummary: null,
  },
  followUpTo: { id: 'private-1', question: 'A private earlier question' },
  followUps: [{ id: 'private-2', question: 'A private follow-up question' }],
  favorite: true,
  repeats: {
    primaryHexagram: [{ id: 'private-3', question: 'A private repeated question' }],
    resultingHexagram: [],
    changingLines: [],
  },
}

describe('SharedConsultationPage', () => {
  it('renders question, hexagrams, changing lines, notes, tags, context, and outcome', async () => {
    vi.mocked(fetchConsultation).mockResolvedValue(sampleConsultation)
    vi.mocked(fetchHexagram).mockImplementation((n: number) => Promise.resolve(sampleHexagram(n)))

    const wrapper = mount(SharedConsultationPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('Should I take the offer?')
    expect(wrapper.text()).toContain('Changing lines: 1')
    expect(wrapper.text()).toContain('Nervous.')
    expect(wrapper.text()).toContain('career')
    expect(wrapper.text()).toContain('Some context.')
    expect(wrapper.text()).toContain('Took it.')
  })

  it('never renders followUpTo, followUps, or repeats data', async () => {
    vi.mocked(fetchConsultation).mockResolvedValue(sampleConsultation)
    vi.mocked(fetchHexagram).mockImplementation((n: number) => Promise.resolve(sampleHexagram(n)))

    const wrapper = mount(SharedConsultationPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).not.toContain('A private earlier question')
    expect(wrapper.text()).not.toContain('A private follow-up question')
    expect(wrapper.text()).not.toContain('A private repeated question')
  })

  it('renders no form, button, or mutating control', async () => {
    vi.mocked(fetchConsultation).mockResolvedValue(sampleConsultation)
    vi.mocked(fetchHexagram).mockImplementation((n: number) => Promise.resolve(sampleHexagram(n)))

    const wrapper = mount(SharedConsultationPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.findAll('form')).toHaveLength(0)
    expect(wrapper.findAll('button')).toHaveLength(0)
    expect(wrapper.text()).not.toContain('Favorited')
    expect(wrapper.text()).not.toContain('Get Interpretation')
  })

  it('shows a not-found state on a 404', async () => {
    vi.mocked(fetchConsultation).mockRejectedValue(new ApiError(404, 'Not Found'))

    const wrapper = mount(SharedConsultationPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('Consultation not found.')
  })

  it('shows a generic error state for other failures', async () => {
    vi.mocked(fetchConsultation).mockRejectedValue(new Error('network down'))

    const wrapper = mount(SharedConsultationPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('network down')
  })
})
