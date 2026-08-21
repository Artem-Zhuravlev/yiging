import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import HomePage from './HomePage.vue'
import { fetchHexagram } from '../../entities/hexagram/api'
import type { Hexagram } from '../../entities/hexagram/model'

vi.mock('../../entities/hexagram/api', () => ({
  fetchHexagram: vi.fn(),
}))

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
})
