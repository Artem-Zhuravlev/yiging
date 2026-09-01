import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import HomePage from './HomePage.vue'
import { fetchHexagram } from '../../entities/hexagram/api'
import {
  fetchConsultations,
  fetchDueReminders,
  setReflectionReminder,
} from '../../entities/consultation/api'
import { fetchStatistics } from '../../entities/statistics/api'
import type { Hexagram } from '../../entities/hexagram/model'
import type { ConsultationListItem, DueReminder } from '../../entities/consultation/model'
import { liveMessage } from '../../shared/lib/announce'

vi.mock('../../entities/hexagram/api', () => ({
  fetchHexagram: vi.fn(),
}))
vi.mock('../../entities/consultation/api', () => ({
  fetchConsultations: vi.fn(),
  fetchDueReminders: vi.fn(),
  setReflectionReminder: vi.fn(),
}))
vi.mock('../../entities/statistics/api', () => ({
  fetchStatistics: vi.fn(),
}))

function recentItem(id: string, question: string): ConsultationListItem {
  return {
    id,
    question,
    method: 'three_coins',
    primaryHexagram: { kingWenNumber: 1, chineseName: '乾', pinyin: 'Qián' },
    changingLinePositions: [],
    resultingHexagram: { kingWenNumber: 2, chineseName: '坤', pinyin: 'Kūn' },
    createdAt: '2026-08-14T10:00:00+00:00',
    tags: [],
    favorite: false,
  }
}

const stubs = { RouterLink: { template: '<a><slot /></a>' } }

const sampleHexagram: Hexagram = {
  kingWenNumber: 29,
  chineseName: '坎',
  pinyin: 'Kǎn',
  symbol: '䷜',
  lines: Array.from({ length: 6 }, (_, i) => ({ position: i + 1, polarity: 'yang' as const })),
  upperTrigram: { id: 'Kan', name: 'Kan', chineseName: '坎', pinyin: 'Kǎn', symbol: '☵' },
  lowerTrigram: { id: 'Kan', name: 'Kan', chineseName: '坎', pinyin: 'Kǎn', symbol: '☵' },
  judgment: null,
  image: null,
  lineStatements: null,
  relationships: {
    nuclear: { kingWenNumber: 29, chineseName: '坎', pinyin: 'Kǎn' },
    reversed: { kingWenNumber: 29, chineseName: '坎', pinyin: 'Kǎn' },
    complement: { kingWenNumber: 30, chineseName: '離', pinyin: 'Lí' },
  },
  favorite: false,
}

describe('HomePage', () => {
  beforeEach(() => {
    liveMessage.value = ''
    vi.mocked(fetchConsultations).mockReset().mockResolvedValue({ items: [], nextCursor: null })
    vi.mocked(fetchDueReminders).mockReset().mockResolvedValue([])
    vi.mocked(setReflectionReminder).mockReset().mockResolvedValue({ remindAt: '2099-01-01T00:00:00+00:00' })
    vi.mocked(fetchStatistics).mockReset().mockResolvedValue({
      totalConsultations: 0,
      hexagramFrequency: [],
      yinYangRatio: { yin: 0, yang: 0 },
      tagFrequency: [],
    })
  })

  it('renders the project title and core navigation links', () => {
    vi.mocked(fetchHexagram).mockReturnValue(new Promise(() => {}))

    const wrapper = mount(HomePage, { global: { stubs } })

    expect(wrapper.text()).toContain('Yijing')
    expect(wrapper.text()).toContain('Cast a new consultation')
    expect(wrapper.text()).toContain('View history')
  })

  it('shows a loading state before the hexagram of the day resolves', () => {
    vi.mocked(fetchHexagram).mockReturnValue(new Promise(() => {}))

    const wrapper = mount(HomePage, { global: { stubs } })

    expect(wrapper.text()).toContain('Loading hexagram of the day')
  })

  it('renders the fetched hexagram of the day, linking to its detail page', async () => {
    vi.mocked(fetchHexagram).mockResolvedValue(sampleHexagram)

    const wrapper = mount(HomePage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('Hexagram of the Day')
    expect(wrapper.text()).toContain('29. 坎')
    expect(wrapper.text()).toContain('Kǎn')
    const link = wrapper.findAll('a').find((a) => a.text().includes('29. 坎'))
    expect(link?.attributes('to')).toBe('/hexagrams/29')
  })

  it('shows an inline error without breaking the rest of the home page', async () => {
    vi.mocked(fetchHexagram).mockRejectedValue(new Error('network down'))

    const wrapper = mount(HomePage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('network down')
    expect(wrapper.text()).toContain('Cast a new consultation')
  })

  it('shows recent consultations and a total-cast line when there is history', async () => {
    vi.mocked(fetchHexagram).mockResolvedValue(sampleHexagram)
    vi.mocked(fetchConsultations).mockResolvedValue({
      items: [recentItem('a', 'First question?'), recentItem('b', 'Second question?')],
      nextCursor: null,
    })
    vi.mocked(fetchStatistics).mockResolvedValue({
      totalConsultations: 12,
      hexagramFrequency: [],
      yinYangRatio: { yin: 30, yang: 42 },
      tagFrequency: [],
    })

    const wrapper = mount(HomePage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('Recent')
    const firstLink = wrapper.findAll('a').find((a) => a.text().includes('First question?'))
    expect(firstLink?.attributes('to')).toBe('/consultations/a')
    expect(wrapper.findAll('a').some((a) => a.text() === 'View all')).toBe(true)
    const castLine = wrapper.findAll('a').find((a) => a.text() === '12 consultations cast')
    expect(castLine?.attributes('to')).toBe('/statistics')
  })

  it('keeps the count line and hexagram of the day when the recent fetch fails', async () => {
    vi.mocked(fetchHexagram).mockResolvedValue(sampleHexagram)
    vi.mocked(fetchConsultations).mockRejectedValue(new Error('recent boom'))
    vi.mocked(fetchStatistics).mockResolvedValue({
      totalConsultations: 3,
      hexagramFrequency: [],
      yinYangRatio: { yin: 9, yang: 9 },
      tagFrequency: [],
    })

    const wrapper = mount(HomePage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).not.toContain('Recent')
    expect(wrapper.text()).toContain('3 consultations cast')
    expect(wrapper.text()).toContain('Hexagram of the Day')
  })

  it('is the plain splash (no dashboard sections) on an empty history', async () => {
    vi.mocked(fetchHexagram).mockResolvedValue(sampleHexagram)

    const wrapper = mount(HomePage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).not.toContain('Recent')
    expect(wrapper.text()).not.toContain('consultations cast')
    expect(wrapper.text()).toContain('Cast a new consultation')
    expect(wrapper.text()).toContain('Hexagram of the Day')
  })

  it('shows the "Due for reflection" section and snoozes a reminder', async () => {
    vi.mocked(fetchHexagram).mockResolvedValue(sampleHexagram)
    const due: DueReminder = {
      id: 'r1',
      question: 'Did the move help?',
      primaryHexagram: { kingWenNumber: 1, chineseName: '乾', pinyin: 'Qián' },
      resultingHexagram: { kingWenNumber: 2, chineseName: '坤', pinyin: 'Kūn' },
      remindAt: '2026-08-01T00:00:00+00:00',
      createdAt: '2026-07-01T00:00:00+00:00',
    }
    vi.mocked(fetchDueReminders).mockResolvedValue([due])

    const wrapper = mount(HomePage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('Due for reflection')
    const link = wrapper.findAll('a').find((a) => a.text().includes('Did the move help?'))
    expect(link?.attributes('to')).toBe('/consultations/r1')

    const snoozeButton = wrapper.findAll('button').find((b) => b.text().includes('Snooze'))!
    await snoozeButton.trigger('click')
    await flushPromises()

    expect(setReflectionReminder).toHaveBeenCalledWith('r1', expect.any(String))
    expect(wrapper.text()).not.toContain('Did the move help?')
  })

  it('hides the "Due for reflection" section when the reminders fetch fails', async () => {
    vi.mocked(fetchHexagram).mockResolvedValue(sampleHexagram)
    vi.mocked(fetchDueReminders).mockRejectedValue(new Error('reminders boom'))

    const wrapper = mount(HomePage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).not.toContain('Due for reflection')
    expect(wrapper.text()).toContain('Hexagram of the Day')
  })

  it('announces the load transition in the live region', async () => {
    vi.mocked(fetchHexagram).mockResolvedValue(sampleHexagram)

    mount(HomePage, { global: { stubs } })
    await flushPromises()

    expect(liveMessage.value).toBe('Content loaded')
  })

  it('announces a load failure in the live region', async () => {
    vi.mocked(fetchHexagram).mockRejectedValue(new Error('network down'))

    mount(HomePage, { global: { stubs } })
    await flushPromises()

    expect(liveMessage.value).toBe('Failed to load content')
  })
})
