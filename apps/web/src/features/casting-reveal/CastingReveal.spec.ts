import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import CastingReveal from './CastingReveal.vue'
import { fetchHexagram } from '../../entities/hexagram/api'
import type { Consultation } from '../../entities/consultation/model'
import type { Hexagram } from '../../entities/hexagram/model'

const push = vi.fn()

vi.mock('vue-router', () => ({ useRouter: () => ({ push }) }))
vi.mock('../../entities/hexagram/api', () => ({ fetchHexagram: vi.fn() }))

const consultation = {
  id: 'con-1',
  question: 'Q?',
  method: 'three_coins',
  primaryHexagram: { kingWenNumber: 1, chineseName: '乾', pinyin: 'Qián' },
  changingLinePositions: [1, 3],
  resultingHexagram: { kingWenNumber: 2, chineseName: '坤', pinyin: 'Kūn' },
  createdAt: '2026-08-14T10:00:00+00:00',
  notes: [],
  tags: [],
  context: null,
  whatHappenedBefore: null,
  whatUserWantsToUnderstand: null,
  backgroundInformation: null,
  initialInterpretation: null,
  outcome: null,
  followUpTo: null,
  followUps: [],
  favorite: false,
} as Consultation

const hexagram = {
  kingWenNumber: 1,
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
    nuclear: { kingWenNumber: 1, chineseName: '乾', pinyin: 'Qián' },
    reversed: { kingWenNumber: 1, chineseName: '乾', pinyin: 'Qián' },
    complement: { kingWenNumber: 2, chineseName: '坤', pinyin: 'Kūn' },
  },
  favorite: false,
} as Hexagram

describe('CastingReveal', () => {
  beforeEach(() => {
    push.mockClear()
    vi.mocked(fetchHexagram).mockReset()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('reveals all six lines then the hexagram name, then auto-navigates', async () => {
    vi.useFakeTimers()
    vi.mocked(fetchHexagram).mockResolvedValue(hexagram)

    const wrapper = mount(CastingReveal, { props: { consultation } })
    await flushPromises() // fetchHexagram resolves

    await vi.advanceTimersByTimeAsync(1000) // coins → lines
    await vi.advanceTimersByTimeAsync(350 * 7) // six lines + the trailing step

    expect(wrapper.findAll('.reveal-line')).toHaveLength(6)
    expect(wrapper.text()).toContain('1. 乾')
    expect(wrapper.text()).toContain('Continue')

    await vi.advanceTimersByTimeAsync(1600) // hold → navigate
    expect(push).toHaveBeenCalledWith('/consultations/con-1')
  })

  it('navigates immediately when Skip is clicked during the coin phase', async () => {
    vi.mocked(fetchHexagram).mockReturnValue(new Promise(() => {}))

    const wrapper = mount(CastingReveal, { props: { consultation } })
    await flushPromises()

    await wrapper.find('button').trigger('click') // the Skip button (only button in coin phase)

    expect(push).toHaveBeenCalledWith('/consultations/con-1')
  })

  it('navigates to the detail page even if fetching the hexagram fails', async () => {
    vi.mocked(fetchHexagram).mockRejectedValue(new Error('offline'))

    mount(CastingReveal, { props: { consultation } })
    await flushPromises()

    expect(push).toHaveBeenCalledWith('/consultations/con-1')
  })

  it('does not navigate after being unmounted mid-animation', async () => {
    vi.useFakeTimers()
    vi.mocked(fetchHexagram).mockResolvedValue(hexagram)

    const wrapper = mount(CastingReveal, { props: { consultation } })
    await flushPromises()

    wrapper.unmount()
    await vi.advanceTimersByTimeAsync(10000)

    expect(push).not.toHaveBeenCalled()
  })
})
