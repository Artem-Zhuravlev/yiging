import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import HexagramListPage from './HexagramListPage.vue'
import { fetchHexagrams } from '../../entities/hexagram/api'
import type { Hexagram } from '../../entities/hexagram/model'

vi.mock('../../entities/hexagram/api', () => ({
  fetchHexagrams: vi.fn(),
}))

const stubs = { RouterLink: { template: '<a><slot /></a>' } }

function sampleHexagram(kingWenNumber: number): Hexagram {
  return {
    kingWenNumber,
    chineseName: '乾',
    pinyin: 'Qián',
    symbol: '䷀',
    lines: Array.from({ length: 6 }, (_, i) => ({
      position: i + 1,
      polarity: 'yang' as const,
    })),
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
  }
}

describe('HexagramListPage', () => {
  it('links to the Visual Editor', () => {
    vi.mocked(fetchHexagrams).mockReturnValue(new Promise(() => {}))

    const wrapper = mount(HexagramListPage, { global: { stubs } })

    expect(wrapper.findAll('a').some((a) => a.text() === 'Visual Editor')).toBe(true)
  })

  it('shows a loading state before the fetch resolves', () => {
    vi.mocked(fetchHexagrams).mockReturnValue(new Promise(() => {}))

    const wrapper = mount(HexagramListPage, { global: { stubs } })

    expect(wrapper.text()).toContain('Loading')
  })

  it('renders every fetched hexagram once loaded', async () => {
    vi.mocked(fetchHexagrams).mockResolvedValue([sampleHexagram(1), sampleHexagram(2)])

    const wrapper = mount(HexagramListPage, { global: { stubs } })
    await flushPromises()

    const links = wrapper.findAll('ul a')
    expect(links).toHaveLength(2)
    expect(wrapper.text()).toContain('乾')
  })

  it('shows an error message when the fetch fails', async () => {
    vi.mocked(fetchHexagrams).mockRejectedValue(new Error('network down'))

    const wrapper = mount(HexagramListPage, { global: { stubs } })
    await flushPromises()

    expect(wrapper.text()).toContain('network down')
  })
})
